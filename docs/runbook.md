# Runbook — signage-laravel

> Operational procedures: start the stack, import data, run tests,
> demo the app. For architecture and conventions, see
> `docs/project-structure.md`.

---

## Prerequisites

- `docker-compose` (hyphen form) installed on the host
- Project cloned at any path
- Sample CSVs at `storage/app/legacy/export/` (if you want to import —
  see [Generating sample CSVs](#generating-sample-csvs))

---

## Quick start (clean machine → working app)

```bash
# 1. Start the stack
docker-compose up -d

# 2. Wait for containers to be healthy
docker-compose ps
# expect: app / nginx / pgsql / redis all (healthy)

# 3. Prepare the database
docker-compose exec app php artisan migrate:fresh

# 4. Verify the app responds
curl -i http://localhost/up
# expect: HTTP/1.1 200 OK
#         Application up

# 5. (Optional) Import sample data
docker-compose exec app php artisan signage:import-legacy

# 6. Verify the import landed
docker-compose exec app php artisan tinker --execute '
    foreach (["cities","offices","departments","monitors","contents","monitor_contents","application_settings"] as $t) {
        echo str_pad($t, 24) . DB::table($t)->count() . "\n";
    }
'
# expect: 2 / 3 / 5 / 4 / 3 / 6 / 1
```

That's the whole setup. From here, the app is running at `http://localhost`.

---

## Generating sample CSVs

The generator script lives outside this repo (at `../MTN/` relative to project root). It writes one `<table>.csv` per importable table into `storage/app/legacy/export/`.

```bash
# From the parent directory that contains both the project and MTN/
../MTN/sample-csvs.sh
```

Or with a custom target:

```bash
../MTN/sample-csvs.sh /tmp/my-export
```

The CSVs are a valid FK graph: 2 cities → 3 offices → 5 departments → 4 monitors → 3 contents → 6 monitor_contents → 1 application_settings row.

**The target directory is wiped** if it already contains files — the script refuses otherwise. Never point it at a directory containing real data.

---

## Import command — `signage:import-legacy`

Imports legacy CSV exports into the database. Preserves IDs, resets Postgres sequences after, and is idempotent (re-running with the same CSVs produces identical DB state).

### Flags

| Flag | Default | Effect |
|---|---|---|
| `--path=<dir>` | `storage/app/legacy/export` | Directory containing `<table>.csv` files |
| `--dry-run` | off | Parse + validate + count only. **No writes.** |
| `--truncate` | off | `TRUNCATE ... RESTART IDENTITY CASCADE` before import. **DEV ONLY** — env-gated, prompts for confirmation |
| `--only=t1,t2` | all | Restrict to named tables. Order in `TableSpecs::all()` preserved (parents before children) |
| `--no-interaction` | off | Skip the truncate confirmation prompt (used by scripts/CI) |

### Exit codes

- `0` — success
- `1` — any failure (bad path, missing file, unknown column, missing required field, FK violation, `--truncate` gate failure, unknown table in `--only`)

### Output

```
Path:   /var/www/html/storage/app/legacy/export
Mode:   write
Tables: cities, application_settings, offices, departments, monitors, contents, monitor_contents

  cities                 +2  ~0  =0
  application_settings   +1  ~0  =0
  offices                +3  ~0  =0
  departments            +5  ~0  =0
  monitors               +4  ~0  =0
  contents               +3  ~0  =0
  monitor_contents       +6  ~0  =0

Sequences reset: cities, application_settings, offices, departments, monitors, contents, monitor_contents
Totals: inserted=24  updated=0  skipped=0
```

- `+N` = rows inserted (new)
- `~N` = rows updated (already existed, columns changed)
- `=N` = rows skipped (already existed, nothing changed — currently always 0; reserved for future use)

**Second run** with the same CSVs: `+0 ~24 =0` — nothing new, everything updated in place.

### Common invocations

```bash
# Preview — parses and validates, no writes
docker-compose exec app php artisan signage:import-legacy --dry-run

# Default import
docker-compose exec app php artisan signage:import-legacy

# Rebuild a single table (dev only, destructive)
docker-compose exec app php artisan signage:import-legacy --truncate --only=cities --no-interaction

# Import only the reference-data tables
docker-compose exec app php artisan signage:import-legacy --only=cities,application_settings,offices,departments

# Import from a non-default directory
docker-compose exec app php artisan signage:import-legacy --path=/var/www/html/storage/app/legacy/real-export
```

### `--truncate` — safety notes

**`--truncate` is destructive and DEV-ONLY.** It runs:

```sql
TRUNCATE TABLE "<table>" RESTART IDENTITY CASCADE
```

- **CASCADE also wipes dependent tables.** Truncating `cities` also truncates `offices` (FK → cities), then `departments` (FK → offices), then `monitors`, then `monitor_contents`. Even if `--only=cities` restricts what gets re-imported, the truncate itself cascades to children.
- **Two guards:** (1) the environment must be `local` or `testing`, (2) an interactive confirmation prompt appears unless `--no-interaction` is set.
- **No undo.** `RESTART IDENTITY` also resets the id sequence, so re-import starts at id 1.

Never run `--truncate` against a database that holds data you care about.

### Error catalogue

| Error | Cause | Fix |
|---|---|---|
| `Import directory not found: <path>` | `--path` doesn't exist | Create it, or pass a valid path |
| `CSV file not found: <path>` | A table's CSV is missing | Provide the file, or exclude the table via `--only` |
| `CSV file is empty: <path>` | Zero-byte file | Delete it, or add a header + at least one row |
| `Unknown column(s) in <file> header: <cols>` | CSV has a column not in the DB table | Rename the column, or add it to the migration |
| `Row N of <file> has X fields, expected Y` | Malformed CSV row (field count mismatch) | Fix the row in the CSV |
| `Row N of <file> is missing required column "<col>"` | Required field is empty | Fill the value, or change `requiredCols` in `TableSpecs.php` |
| `SQLSTATE[23502] ... null value in column "<col>" ... violates not-null constraint` | `NOT NULL` column, empty CSV cell | Make the column `->nullable()` in the migration, `migrate:fresh`, retry |
| `SQLSTATE[23503] ... foreign key violation` | Child imported before parent (e.g. `--only=offices` without `cities`) | Include the parent table in `--only`, or import everything |
| `Unknown table(s): <names>` | `--only` has a name not in `TableSpecs` | Use a name from `TableSpecs::all()` |
| `--truncate is only allowed in local/testing environments` | `APP_ENV` is not local/testing | Only use `--truncate` in dev |

After fixing any of the DB-level errors above, run `docker-compose exec app php artisan migrate:fresh` first — the previous run may have committed partial rows (import is non-transactional across tables).

### Manual test checklist

Verifies every flag works. Run from `signage-laravel/`.

```bash
# --- 0. Baseline --------------------------------------------------------------
docker-compose exec app php artisan migrate:fresh

# --- 1. Help shows all flags --------------------------------------------------
docker-compose exec app php artisan signage:import-legacy --help

# --- 2. --path (valid) --------------------------------------------------------
docker-compose exec app php artisan signage:import-legacy --dry-run

# --- 3. --path (invalid) ------------------------------------------------------
docker-compose exec app php artisan signage:import-legacy --dry-run \
  --path=/tmp/does-not-exist
# expect: exit 1, "Import directory not found"

# --- 4. --dry-run (no writes) -------------------------------------------------
docker-compose exec app php artisan signage:import-legacy --dry-run
docker-compose exec app php artisan tinker --execute 'echo App\Models\City::count() . "\n";'
# expect: 0

# --- 5. Real import -----------------------------------------------------------
docker-compose exec app php artisan signage:import-legacy
# expect: +24 ~0 =0

# --- 6. Idempotency -----------------------------------------------------------
docker-compose exec app php artisan signage:import-legacy
# expect: +0 ~24 =0

# --- 7. --only ----------------------------------------------------------------
docker-compose exec app php artisan migrate:fresh
docker-compose exec app php artisan signage:import-legacy --only=cities
# expect: +2 ~0 =0

docker-compose exec app php artisan signage:import-legacy --only=unknown
# expect: exit 1, "Unknown table(s): unknown"

# --- 8. --truncate (interactive no) ------------------------------------------
docker-compose exec app php artisan migrate:fresh
docker-compose exec app php artisan signage:import-legacy
docker-compose exec app php artisan signage:import-legacy --truncate
# press Enter (default = no)
# expect: "Import aborted.", exit 1

# --- 9. --truncate --no-interaction (bypasses prompt) ------------------------
docker-compose exec app php artisan signage:import-legacy --truncate --no-interaction
# expect: no prompt, truncate + import

# --- 10. --truncate outside local/testing -------------------------------------
docker-compose exec -e APP_ENV=production app php artisan signage:import-legacy --truncate
# expect: exit 1, "--truncate is only allowed in local/testing"

# --- 11. --dry-run --truncate (dry-run wins) ---------------------------------
docker-compose exec app php artisan signage:import-legacy --dry-run --truncate --no-interaction
# expect: no truncate, no writes

# --- 12. Sequence reset -------------------------------------------------------
docker-compose exec app php artisan migrate:fresh
docker-compose exec app php artisan signage:import-legacy
docker-compose exec app php artisan tinker --execute '
    $c = App\Models\City::create(["name" => "Post-import"]);
    echo $c->id . "\n";   // must be 3, not 1
'

# --- 13. Reset to canonical state --------------------------------------------
docker-compose exec app php artisan migrate:fresh
docker-compose exec app php artisan signage:import-legacy
```

Expected counts after step 13: `cities=2, offices=3, departments=5, monitors=4, contents=3, monitor_contents=6, application_settings=1`.

### When to use which flag combination

| Scenario | Command |
|---|---|
| First import from a new export | `--dry-run` first, then plain run |
| Repeated import during development | plain run (idempotent) |
| Rebuild a single table | `--truncate --only=<table> --no-interaction` |
| Debug a single failing table | `--only=<table> --dry-run` |
| Scripted CI | `--no-interaction` (add `--truncate` only if the pipeline regenerates the DB) |
| After `migrate:fresh` on a dev box | plain run (no `--truncate` — DB is already empty) |

---

## Reference APIs

Read-only endpoints serving reference data. All responses use the legacy
envelope `{ ResultCode, Message, Data }`. See ADR `0001` for the field-name
assumptions.

### Endpoints

| Method | Path | Paginated | Search | Sort |
|---|---|---|---|---|
| GET | `/api/v1/cities` | ✓ | ✓ | ✓ |
| GET | `/api/v1/cities/list` | — | ✓ | ✓ |
| GET | `/api/v1/offices` | ✓ | ✓ | ✓ |
| GET | `/api/v1/offices/list` | — | ✓ | ✓ |
| GET | `/api/v1/departments` | ✓ | ✓ | ✓ |
| GET | `/api/v1/departments/list` | — | ✓ | ✓ |
| GET | `/api/v1/monitors` | ✓ | ✓ | ✓ |
| GET | `/api/v1/monitors/list` | — | ✓ | ✓ |

### Query parameters

| Param | Default | Bounds | Applies to |
|---|---|---|---|
| `pageIndex` | `1` | `>=1` | paginated endpoints |
| `rowNumber` | `20` | `1..500` | paginated endpoints |
| `search` | — | `<=255 chars` | all endpoints |
| `sortBy` | `id` (fallback) | PascalCase field name | all endpoints |
| `sortDirection` | `asc` | `asc`/`desc` | all endpoints |

- **Search**: case-insensitive `LIKE` (`ILIKE` on Postgres) across each
  resource's searchable columns.
- **Sort**: unknown `sortBy` values silently fall back to `id asc` — no 422.
  Rationale: never break a request over a stale sort key.
- **Sort columns** per resource are declared in `sortableColumns()` in each
  controller. `TODO(contract)`: confirm against Step 9 fixtures.

### Response shape

```json
{
  "ResultCode": 5000,
  "Message": null,
  "Data": {
    "Items": [
      { "Id": 1, "Name": "Tehran" }
    ],
    "TotalCount": 2,
    "PageIndex": 1,
    "RowNumber": 20
  }
}
```

`/list` endpoints return `Data` as a flat array (no pagination wrapper).

### Example curls

```bash
# Paginated
curl -s 'http://localhost/api/v1/cities?pageIndex=1&rowNumber=2' | jq

# Flat list
curl -s 'http://localhost/api/v1/cities/list' | jq

# Search
curl -s 'http://localhost/api/v1/monitors?search=10.0.0' | jq

# Sort
curl -s 'http://localhost/api/v1/cities?sortBy=Name&sortDirection=desc' | jq

# Combined
curl -s 'http://localhost/api/v1/departments?search=IT&sortBy=DepartmentName&pageIndex=1&rowNumber=10' | jq

# Validation errors (expect 422)
curl -s -o /dev/null -w '%{http_code}\n' 'http://localhost/api/v1/cities?pageIndex=-1'
curl -s -o /dev/null -w '%{http_code}\n' 'http://localhost/api/v1/cities?rowNumber=9999'
```

### Contract assumptions

Every field name and ResultCode is **a guess** until Step 9 fixtures arrive.
See `docs/decisions/0001-api-contract-assumptions.md` for the evidence and
confidence level of each guess. When fixtures land, corrections go into
`app/Legacy/Codes.php` and the affected `*Resource` — no controller change.

### Files

| File | Role |
|---|---|
| `app/Legacy/Codes.php` | Constants — result codes, envelope keys, pagination keys |
| `app/Legacy/Envelope.php` | Response builder — `paginated()`, `list()`, `error()` |
| `app/Http/Requests/PaginatedRequest.php` | Shared query-param validation |
| `app/Http/Controllers/Api/V1/Concerns/HandlesPaginatedList.php` | Trait — index + list logic |
| `app/Http/Controllers/Api/V1/*Controller.php` | One per resource (4) |
| `app/Http/Resources/*Resource.php` | Field mapping (4) |
| `routes/api.php` | `/v1` route group |
| `tests/Feature/Api/V1/*Test.php` | Envelope, pagination, search, sort (4 files) |

-----

## Running tests

```bash
# Full suite
docker-compose exec app php artisan test

# Just the import tests
docker-compose exec app php artisan test --filter=ImportLegacySignage
docker-compose exec app php artisan test --filter=CsvReader

# Single file
docker-compose exec app php artisan test tests/Unit/Services/Import/CsvReaderTest.php
```

### Formatting check

```bash
docker-compose exec app ./vendor/bin/pint --test
```

To auto-fix:

```bash
docker-compose exec app ./vendor/bin/pint
```

### PSR-4 autoload health

Any time you add a class under `app/`:

```bash
docker-compose exec app composer dump-autoload
docker-compose exec app composer dump-autoload 2>&1 | grep -iE "comply|skipping" || echo "Clean"
```

If the second command prints anything other than `Clean`, a directory name and a namespace don't match — see `docs/project-structure.md#psr-4-critical--causes-silent-failures`.

---

## Demo (Step 10)

Not yet available. Will cover:

1. `docker-compose up -d`
2. `php artisan migrate:fresh`
3. `php artisan signage:import-legacy`
4. Open `/monitor` in a browser
5. Show playlist, daily message, video
6. Change one row in the DB → show refresh
7. Show the doctor report + test suite

Fill this in after Steps 5–9 ship.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `docker-compose ps` shows a container restarting | `docker-compose logs <service>` — usually a bad env var |
| `Target class [...] does not exist` at runtime | `docker-compose exec app composer dump-autoload` — see PSR-4 section above |
| `Connection refused` from the app | `pgsql` or `redis` container not healthy — `docker-compose ps` |
| Import fails with FK violation | Parent table missing — include it in `--only`, or drop `--only` entirely |
| Import fails with NOT NULL | A column needs `->nullable()` in the migration. Fix, `migrate:fresh`, retry |
| `/up` returns 502 | nginx can't resolve `app:9000` — `docker-compose restart nginx`, or check `docker-compose logs app` |
| `php artisan migrate:fresh` fails on FK | Migration order — check `database/migrations/*` timestamps; children must come after parents |

---

## Files referenced

| File | Purpose |
|---|---|
| `app/Console/Commands/ImportLegacySignage.php` | Import command |
| `app/Services/Import/TableSpecs.php` | Tables to import, in order |
| `app/Services/Import/TableImporter.php` | Per-table work |
| `app/Services/Import/CsvReader.php` | CSV parser |
| `tests/Feature/Console/ImportLegacySignageTest.php` | Flag + error-path tests |
| `tests/Unit/Services/Import/CsvReaderTest.php` | Parser tests |
| `docs/project-structure.md` | Architecture reference |

## Playback & Media

The playback endpoint serves the resolved playlist in legacy PascalCase
JSON. Media serves the underlying files.

### Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/playback/monitor-contents` | Playlist for the calling IP |
| GET | `/api/Content/MonitorContents` | Legacy alias (old Angular client hardcodes it) |
| GET | `/media/files/{contentId}/{filename}` | Static media file |

### Playback — query parameters

| Param | Applies | Notes |
|---|---|---|
| `view` | Both playback endpoints | Optional. `"undefined"` is treated as absent. |
| `ip` | Both playback endpoints | **Dev-only** — only honored in `local`/`development`/`testing`. See ADR-0005. |

### Playback — response shape

```json
{
  "ResultCode": 4000,
  "Message": null,
  "Data": {
    "ShareContents": null,
    "MonitorContents": [
      {
        "ContentId": 1,
        "ContentName": "welcome.jpg",
        "UserId": null,
        "UserName": null,
        "ContentType": 1,
        "Contentpath": "/media/files/1/welcome.jpg",
        "ClientFileName": "original_welcome.jpg",
        "FromDate": null,
        "ToDate": null,
        "ContentTypeText": "عکس",
        "UploadeDateTime": "2026-01-01T00:00:00",
        "UploadDate": "1404/10/11",
        "UploadTime": "12:00",
        "DisplayTime": 10,
        "Active": true,
        "UrlUserName": null,
        "UrlPassword": null,
        "IsAutoLogin": null,
        "Url": null
      }
    ],
    "DailyMessage": "Welcome to IT",
    "IsMonitorContent": true,
    "ByPassAllContent": false,
    "MonitorDateTime": {
      "TodayDate": "1404/08/16",
      "PersianWeekDayText": "شنبه",
      "WeekDayText": "Saturday",
      "Full": "1404/08/16-شنبه",
      "PersianMonth": 8,
      "PersianYear": 1404,
      "PersianDay": 16,
      "Month": 11,
      "Year": 2025,
      "Day": 7
    }
  }
}
```

### Paths

- **Default** — `MonitorContents` populated, `ShareContents: null`, `IsMonitorContent: true`
- **Bypass** — `ShareContents` populated, `MonitorContents: null`, `IsMonitorContent: false`, `DailyMessage: ""`
- **View** — `MonitorContents` populated from `view_contents ∩ monitor_contents`
- **Unknown IP** — `MonitorContents: []` (empty array, not null), `DailyMessage: ""`

### Legacy field typos

These are intentional — the legacy client hardcodes them. Do not rename:

| Field | Not |
|---|---|
| `Contentpath` (lowercase p) | `ContentPath` |
| `UploadeDateTime` (extra e) | `UploadedDateTime` |
| `ByPassAllContent` (capital P) | `BypassAllContent` |

A rename must ship alongside a client update in the same PR.

### Example curls

```bash
# Playlist for monitor at 10.0.0.1
curl -s 'http://localhost/api/v1/playback/monitor-contents?ip=10.0.0.1' | jq

# With view
curl -s 'http://localhost/api/v1/playback/monitor-contents?ip=10.0.0.1&view=morning' | jq

# Legacy alias (old client path)
curl -s 'http://localhost/api/Content/MonitorContents?ip=10.0.0.1' | jq

# Unknown IP — empty playlist, ResultCode 4000
curl -s 'http://localhost/api/v1/playback/monitor-contents?ip=10.99.99.99' | jq '.Data.MonitorContents'
```

### Media route

Files served from `storage/app/media/files/{contentId}/{filename}`.

**Directory layout:**

```
storage/app/media/
└── files/
    ├── 1/
    │   └── welcome.jpg          ← must match contents.content_name exactly
    ├── 2/
    │   └── intro.mp4
    └── 3/
        └── notice.jpg
```

Filename must match `contents.content_name` exactly (case-sensitive) —
this is a path-traversal guard.

**Copy a real file to test:**

```bash
mkdir -p storage/app/media/files/1
cp /path/to/any.jpg storage/app/media/files/1/welcome.jpg
curl -i 'http://localhost/media/files/1/welcome.jpg'
```

**Error responses (all `404`):**

| Case | Log level |
|---|---|
| Unknown contentId | info |
| Content `deleted=true` | info |
| Filename mismatch | info |
| File missing on disk | warning |
| Path traversal attempt (`../` in filename) | — (rejected by route/controller) |
| Non-numeric contentId | — (rejected by `whereNumber` route constraint) |

### Persian dates

Backed by **Verta** (`hekmatinasser/verta`) wrapped in
`App\Services\Support\PersianDate`. Swap the library by editing one file —
no consumer changes.

**Output fields:**

| Where | Field | Example |
|---|---|---|
| Content item | `UploadeDateTime` | `2026-01-01T00:00:00` (Gregorian ISO) |
| Content item | `UploadDate` | `1404/10/11` (Jalali) |
| Content item | `UploadTime` | `12:00` |
| `MonitorDateTime` | `TodayDate` | `1404/08/16` |
| `MonitorDateTime` | `PersianWeekDayText` | `شنبه` |
| `MonitorDateTime` | `WeekDayText` | `Saturday` |
| `MonitorDateTime` | `Full` | `1404/08/16-شنبه` |
| `MonitorDateTime` | `PersianYear` / `Month` / `Day` | `1404` / `8` / `16` |
| `MonitorDateTime` | `Year` / `Month` / `Day` | `2025` / `11` / `7` (Gregorian) |

### Files

| File | Role |
|---|---|
| `app/Http/Controllers/Api/V1/PlaybackController.php` | Playback endpoint |
| `app/Http/Controllers/MediaController.php` | Media file serving |
| `app/Http/Requests/PlaybackRequest.php` | Query-param validation |
| `app/Services/Playback/PlaybackTransformer.php` | Playlist → legacy JSON |
| `app/Services/Support/PersianDate.php` | Jalali + Gregorian formatting |
| `app/Providers/MediaRouteServiceProvider.php` | Loads `routes/media.php` |
| `routes/media.php` | Media route registration |
| `config/filesystems.php` | `media` disk definition |