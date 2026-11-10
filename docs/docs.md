# Project structure — signage-laravel

> Reference doc for the current shape of the codebase and where future
> code lands. Update this file (not the code) when structure changes.

---

## Table of contents

1. [Status](#status)
2. [Stack](#stack)
3. [Repository layout](#repository-layout)
4. [Architectural layers](#architectural-layers)
5. [Conventions](#conventions)
6. [Database conventions](#database-conventions)
7. [Import subsystem](#import-subsystem)
8. [Environment & compose](#environment--compose)
9. [Pending / blocked](#pending--blocked)
10. [Where future steps add code](#where-future-steps-add-code)
11. [How to keep this doc current](#how-to-keep-this-doc-current)

---

## Status

| Step | Day | Status | Notes |
|---|---|---|---|
| 1 — Project skeleton | 1 | ✅ Done | nginx + php-fpm, `/up` healthcheck, Postgres 17, Redis 7 |
| 2 — Reference schema | 2 | ✅ Done | cities, offices, departments, monitors, application_settings |
| 3 — Content schema | 3 | ⚠️ Partial | contents + monitor_contents landed; other content tables blocked on missing spec (see [Pending](#pending--blocked)) |
| 4 — Import command | 4 | ✅ Done | `signage:import-legacy` with dry-run, truncate, only, idempotent |
| 5 — Reference APIs | 5 | ⏸️ Blocked | waiting on `04-API-CONTRACT.md` |
| 6 — Playlist resolver | 6–7 | ⏸️ Blocked | needs Step 3 completion + `05-DATA-MODEL.md` |
| 7 — Playback + media | 8 | ⏸️ Pending | depends on Step 6 |
| 8 — Player | 9 | ⏸️ Pending | depends on Step 7 |
| 9 — Tests + doctor | 10 | ⏸️ Pending | |
| 10 — Demo + handover | 10 | ⏸️ Pending | |

**Scope note:** this is a **read-only slice**. No writes, no uploads, no admin UI. Only imports (via CLI) and reads (via API).

---

## Stack

| Layer | Choice | Notes |
|---|---|---|
| Runtime | PHP 8.4 (FPM) | not CLI `artisan serve` — see `docker/8.4/Dockerfile` |
| Framework | Laravel 11+ | |
| Web server | nginx (stable-alpine) | proxies `/` → `app:9000` via FastCGI |
| Database | PostgreSQL 17 | **reserved word quirks** — see [DB conventions](#database-conventions) |
| Cache | Redis 7 | |
| Container orchestration | `docker-compose` (**hyphen**, not `docker compose`) | |
| Test framework | Pest | |
| Formatter | Pint | `composer lint` |

---

## Repository layout

```
signage-laravel/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── ImportLegacySignage.php         ← Step 4
│   ├── Enums/
│   │   └── ContentType.php                     ← Step 3
│   ├── Http/
│   │   ├── Controllers/                        ← Step 5+
│   │   ├── Middleware/
│   │   ├── Requests/                           ← Step 5+
│   │   └── Resources/                          ← Step 5+
│   ├── Models/
│   │   ├── ApplicationSetting.php              ← Step 2
│   │   ├── BaseContent.php                     ← Step 3 (stub)
│   │   ├── BaseMonitor.php                     ← Step 3 (stub)
│   │   ├── City.php                            ← Step 2
│   │   ├── Content.php                         ← Step 3
│   │   ├── ContentUrlUserPassword.php          ← Step 3
│   │   ├── Department.php                      ← Step 2
│   │   ├── Monitor.php                         ← Step 2
│   │   ├── MonitorContent.php                  ← Step 3
│   │   ├── MonitorView.php                     ← Step 3 (stub)
│   │   ├── MonitorViewMonitor.php              ← Step 3 (stub)
│   │   ├── Office.php                          ← Step 2
│   │   ├── ShareContent.php                    ← Step 3 (stub)
│   │   ├── ShareMonitor.php                    ← Step 3 (stub)
│   │   ├── User.php                            ← Laravel default
│   │   ├── View.php                            ← Step 3 (stub)
│   │   └── ViewContent.php                     ← Step 3 (stub)
│   ├── Providers/
│   └── Services/
│       ├── Import/                             ← Step 4
│       │   ├── CsvReader.php
│       │   ├── CsvRow.php
│       │   ├── ImportContext.php
│       │   ├── TableImporter.php
│       │   ├── TableSpec.php
│       │   └── TableSpecs.php
│       └── Support/
│           └── PersianDate.php                 ← Step 7 (placeholder)
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docker/
│   ├── 8.4/
│   │   ├── Dockerfile
│   │   ├── supervisord.conf
│   │   ├── start-container
│   │   ├── php.ini
│   │   └── www.conf
│   └── nginx/
│       └── default.conf
├── docs/
│   ├── project-structure.md                    ← this file
│   ├── questions.md                            ← open questions for PO
│   ├── day-1/  day-2/  day-3/  ...             ← per-day reports / notes / blog ideas
│   └── decisions/                              ← ADRs (Step 6+)
├── public/
├── resources/
│   └── views/
│       └── player/                             ← Step 8
├── routes/
│   ├── api.php                                 ← Step 5+
│   ├── console.php
│   ├── web.php
│   └── up.php                                  ← Step 1 (health endpoint)
├── storage/
│   └── app/
│       └── legacy/
│           └── export/                         ← gitignored, sample CSVs
├── tests/
│   ├── Feature/
│   ├── Unit/
│   │   └── Services/Import/
│   │       └── CsvReaderTest.php               ← Step 4
│   ├── Pest.php
│   └── TestCase.php
├── .env.example
├── composer.json
├── compose.yaml
├── package.json
└── pint.json
```

Legend: `← Step N` = first introduced by that step. `(stub)` = migration + model only, schema pending spec.

---

## Architectural layers

Code is organized by **concern**, not by **feature** (mostly):

```
┌──────────────────────────────────────────────────────────────┐
│ Console/Commands/                                            │
│   Thin orchestrators. Parse flags, call services,             │
│   format output. No business logic.                          │
└───────────────────────────┬──────────────────────────────────┘
                            │
┌───────────────────────────▼──────────────────────────────────┐
│ Services/                                                    │
│   All reusable business logic. Testable in isolation.        │
│   Subfolder per domain (Import/, Playlist/, Api/, ...).      │
└───────────────────────────┬──────────────────────────────────┘
                            │
┌───────────────────────────▼──────────────────────────────────┐
│ Models / Enums                                               │
│   Eloquent models, relationships, casts, enums.              │
│   No business logic — no queries in controllers/commands.    │
└───────────────────────────┬──────────────────────────────────┘
                            │
┌───────────────────────────▼──────────────────────────────────┐
│ Migrations / Factories                                       │
│   Schema definition and test-data generation.                │
└──────────────────────────────────────────────────────────────┘
```

**Rules:**

1. **Commands orchestrate, Services do work, Models are data.**
2. Controllers (Step 5+) follow the same rule: parse request → call a service → wrap in envelope → return. No DB access in controllers.
3. If two callers would use the same logic, extract to `Services/`.
4. A `Services/` class must be usable from a test, a command, an HTTP controller, and a queue job — no framework coupling beyond what it needs.

---

## Conventions

### PSR-4 (critical — causes silent failures)

**Folder name MUST equal namespace tail, character for character.**

```
app/Services/Import/Foo.php  →  namespace App\Services\Import;
app/Services/Imports/Foo.php →  namespace App\Services\Imports;   // NOT App\Services\Import
```

Mismatch → `composer dump-autoload` prints:
```
Class App\Services\Import\Foo located in ./app/Services/Imports/Foo.php
does not comply with psr-4 autoloading standard. Skipping.
```
→ the class then does not exist at runtime → `BindingResolutionException` far from the cause.

**Always run after adding new classes:**

```bash
docker-compose exec app composer dump-autoload
```

Sanity check that catches PSR-4 issues before they become runtime bugs:

```bash
docker-compose exec app composer dump-autoload 2>&1 \
  | grep -iE "comply|skipping" \
  || echo "Clean"
```

### File / class naming

| Type | Pattern | Example |
|---|---|---|
| Command | `<Verb><Noun>` | `ImportLegacySignage` |
| Service (action) | `<Noun><Verb>er` | `CsvReader`, `TableImporter` |
| Service (resolver) | `<Noun>Resolver` | `PlaylistResolver` (Step 6) |
| Service (helper) | `<Noun>` | `PersianDate`, `LegacyEnvelope` |
| Value object | `<Noun>` | `CsvRow`, `TableSpec` |
| Enum | `<Noun>` | `ContentType` |
| Controller | `<Noun>Controller` | `CityController` |
| Resource | `<Noun>Resource` | `CityResource` |
| Form request | `<Action>Request` | `PaginatedRequest` |
| Model | Singular, PascalCase | `City`, `MonitorContent` |

### Tests

- Pest for all tests (no raw PHPUnit unless required by a package)
- Feature tests: `tests/Feature/` — end-to-end (command, HTTP, migrations)
- Unit tests: `tests/Unit/` — a single class in isolation
- Shared helpers in `tests/Pest.php` (`writeCsv`, `writeTableCsv`)
- Every Step adds at least one test asserting the "Done when" criteria

### Envelope (Step 5+)

- Centralized in `app/Services/Api/LegacyEnvelope.php` (once Step 5 begins)
- **Every** API controller response goes through it — no hand-built `{ ResultCode, Message, Data }` in a controller
- Field names are **PascalCase** for legacy client compatibility: `ResultCode`, `Message`, `Data`, `ContentId`, `ClientFileName` — not snake_case

### Env variables

- **Committed to `.env.example`**: anything a new developer might need to change (DB creds, ports, Xdebug mode)
- **Comment in `.env.example`, unset by default**: optional knobs (`SAIL_IMAGE`, `HEALTH_*`, `VITE_PORT`)
- **Never committed**: `SAIL_BUILD_PROXY`, `SAIL_BUILD_NETWORK`, `WWWUSER`, `WWWGROUP` — local-only
- **Never in `.env.example`**: `SUPERVISOR_PHP_COMMAND`, `SUPERVISOR_PHP_USER` — Dockerfile `ENV`, not Compose vars

---

## Database conventions

### Column naming

- Primary key: `id` (`$table->id()` — `bigserial` on Postgres)
- Timestamps: `created_at`, `updated_at` (`$table->timestamps()`)
- Foreign keys: `<singular>_id` (`city_id`, `department_id`, `parent_id`)
- Booleans: `is_*` or verb (`is_auto_login`, `deleted`, `active`) — not `is_deleted` if the spec says `deleted`
- Enums as integers when spec dictates (`content_type` 1..5)

### Foreign keys

- Explicit `->constrained(...)` on every FK
- **Postgres does not auto-index FK columns.** Add `$table->index('fk_column')` (or a composite leading with the FK column) explicitly.
- `onDelete` policy per relationship, never default:
  - Cascading deletes → `->cascadeOnDelete()`
  - Parent delete blocks child delete → `->restrictOnDelete()`
  - Child survives parent delete → `->nullOnDelete()` (must be `->nullable()` first)

### Nullable columns

- Empty CSV cell → `NULL` in DB is the contract. If a legacy source can omit a value, the column is `->nullable()`.
- `->default(false)` on a `NOT NULL` column **does not help the importer** — it only fires when the column is omitted from the INSERT, and the importer passes `NULL` explicitly. Make the column nullable; do not rely on defaults to mask import semantics.
- Defaults are for application-created rows, not for imports.

### Reserved words

- `order` (Postgres reserved) — `contents.order` is a real column. Laravel quotes identifiers automatically, so Eloquent works. **Raw SQL and DB tools need `"order"`** with double quotes.
- `views` (SQL reserved) — table name. Same treatment. Model class is `App\Models\View` and shadows `Illuminate\Support\Facades\View` at use-site; alias when both are needed.
- `lock` — not reserved, but non-standard. Keep as spec.

### Idempotency

Every import path uses `upsert` by `id`. Re-running any import command must produce identical DB state.

---

## Import subsystem

**Source of truth:** `app/Services/Import/TableSpecs.php` (ordered list of what gets imported).

### The pattern

```
ImportLegacySignage (command)          ── orchestrates
    └── TableSpecs::all()              ── what to import, in order
    └── TableImporter (per table)      ── does the work
        └── CsvReader                  ── parses the file
        └── ImportContext              ── accumulates counters
```

| Class | Responsibility |
|---|---|
| `TableSpec` | One table's config: name, csvFile, columns, requiredCols, uniqueBy |
| `TableSpecs` | Static list of `TableSpec` in FK-safe order |
| `CsvReader` | Reads a file into `header[] + CsvRow[]`; validates shape |
| `CsvRow` | One parsed row + its line number for error messages |
| `TableImporter` | Per-table upsert; truncate gate; row validation |
| `ImportContext` | Mutable counters + orphan/error collectors per run |

### CLI flags

| Flag | Effect |
|---|---|
| `--path=<dir>` | Directory override (default: `storage/app/legacy/export`) |
| `--dry-run` | Parse + count only; no DB writes |
| `--truncate` | `TRUNCATE ... RESTART IDENTITY CASCADE` before import. **DEV ONLY** — env-gated + interactive confirmation |
| `--only=t1,t2` | Subset of tables, still respecting parent-first order |

### Rules

- **Preserve `id`** from the CSV (`upsert` key is `id`)
- **Idempotent** — running twice produces the same DB state
- **Sequence reset** — after import, Postgres `id` sequences are `setval`'d to `MAX(id) + 1`
- **Header validated** against live DB schema; unknown columns fail immediately with the file name
- **Row-level errors** include the physical CSV line number
- **Parents before children** — determined by `TableSpecs::all()` order, not by CSV inspection

### How to add a new importable table

1. Migration + model + factory
2. Add a `TableSpec` entry to `TableSpecs::all()` — **in FK-safe position**
3. Add a case to `seedExportDir()` helper in `tests/Feature/Console/ImportLegacySignageTest.php`
4. Add a test that asserts the table is imported with the right count
5. Nothing else changes — the command reads `TableSpecs` dynamically

---

## Environment & compose

### Compose invocation

Use **`docker-compose`** (hyphen) — this project does not use `docker compose` (space) and does not use `sail` CLI as the primary entry point.

### Services

| Service | Role | Exposed port |
|---|---|---|
| `app` (or `laravel.test`) | php-fpm on `:9000` | none (internal) |
| `nginx` | HTTP entrypoint | `${APP_PORT:-80}:80` |
| `pgsql` | Postgres 17 | `${FORWARD_DB_PORT:-5432}:5432` |
| `redis` | Redis 7 | `${FORWARD_REDIS_PORT:-6379}:6379` |

### Healthcheck

- `app` → `php -r 'fsockopen(127.0.0.1, 9000)'`
- `nginx` → `wget -q http://127.0.0.1/up`
- `/up` route exercises nginx → php-fpm → Laravel. Defined in `routes/up.php` (not `bootstrap/app.php`'s `health:`)

### Supervisor

`supervisord.conf` runs one program: `php-fpm` via `%(ENV_SUPERVISOR_PHP_COMMAND)s`. Command and user come from `Dockerfile` `ENV`:
- `SUPERVISOR_PHP_COMMAND="/usr/sbin/php-fpm8.4 --nodaemonize"`
- `SUPERVISOR_PHP_USER="root"` (required for php-fpm to bind `:9000`)

Future services (Vite, Reverb, queue) are added as additional `[program:*]` blocks **when the corresponding service exists**, not before (a program that can't start crash-loops).

### Sample CSVs for manual testing

Location: `scripts/` (outside this repo, at `../MTN/` relative to project root — not tracked here).

Generated CSVs are written to `storage/app/legacy/export/` which is gitignored.

---

## Pending / blocked

### External blockers (need answers before Step can finish)

| Blocker | Blocks | Where to ask |
|---|---|---|
| `04-API-CONTRACT.md` | Step 5 — field names, pagination shape, `ResultCode` map | PO / moderator |
| `05-DATA-MODEL.md` | Step 3 completion + Step 6 — `share_*`, `base_*`, `views`, `monitor_views`, `monitor_view_monitors` structure | PO / moderator |

### Known TODOs in code

Search for `TODO(` across `app/` — every marker corresponds to an item in `docs/questions.md`.

Highlights:

- `content_url_user_passwords.user_name` — response field is `UrlUserName`, column is `user_name`; final casing TBD
- `content_url_user_passwords.password` — plaintext vs `encrypted` cast TBD
- `contents.client_file_name` — playback filename vs original upload filename; both exist in response, source per column TBD
- `contents.uploaded_at` — relationship to `created_at` unclear
- `contents.from_date` / `to_date` — date vs timestamp
- `share_contents` / `share_monitors` — pairing mechanism, extra columns
- `base_contents` / `base_monitors` — same as share_*
- `views` — relation to monitors (direct FK vs pivot)
- `monitor_views` / `monitor_view_monitors` — entity vs pivot, or drop entirely
- `monitor_contents.active` — default `false` confirmed (preview-before-live), but PO should confirm
- `PersianDate` — placeholder, implement in Step 7

### Deferred (not blocking, but tracked)

- `application_settings` singleton enforcement — CSV could contain multiple rows; needs decision
- Pest data-model test — writing one consolidated `ModelsAndFactoriesTest` after all tables land
- Insert-vs-update counting optimization — current per-row `exists()` check is fine at fixture scale

---

## Where future steps add code

### Step 5 — Reference APIs

```
app/Http/Controllers/Api/V1/
    CityController.php
    OfficeController.php
    DepartmentController.php
    MonitorController.php
app/Http/Resources/
    CityResource.php
    OfficeResource.php
    DepartmentResource.php
    MonitorResource.php
app/Http/Requests/
    PaginatedRequest.php
app/Services/Api/
    LegacyEnvelope.php
routes/api.php                              (add /v1 group)
tests/Feature/Api/V1/
    CityApiTest.php
    ...
```

### Step 6 — Playlist resolver

```
app/Services/Playlist/
    PlaylistResolver.php
    Playlist.php                            (DTO)
    PlaylistItem.php                        (DTO)
tests/Unit/Services/Playlist/
    PlaylistResolverTest.php
```

### Step 7 — Playback endpoint + media

```
app/Http/Controllers/Api/V1/PlaybackController.php
app/Http/Controllers/MediaController.php
app/Services/Api/PlaybackEnvelope.php       (or extend LegacyEnvelope)
app/Support/Ip.php                          (normalize / isLocal — TODO from Step 3)
routes/api.php                              (add /api/Content/MonitorContents legacy alias)
```

### Step 8 — Player

```
resources/views/player/index.blade.php
public/player/index.js
routes/web.php                              (add /monitor route)
```

### Step 9 — Tests + doctor

```
app/Console/Commands/SignageDoctor.php
tests/Feature/Commands/SignageDoctorTest.php
tests/Fixtures/legacy/export/               (frozen fixture set)
```

### Step 10 — Demo + handover

No new code — fills in `docs/runbook.md`, `docs/legacy-api-notes.md`, `docs/decisions/`.

---

## How to keep this doc current

This document is intentionally **horizontal by concern** (layout, layers, conventions, status, future slots) — not chronological. Adding a new step touches at most four places.

| Change | Update these sections |
|---|---|
| New table / model / migration | Repository layout · DB conventions (if new rule) · Status |
| New service | Repository layout · Architectural layers (if new category) · Step-specific section |
| New command | Repository layout · Import subsystem (or new subsystem section) |
| New API endpoint | Repository layout · Envelope section (if new response type) · "Where future steps add code" |
| New convention | Conventions |
| New blocker | Pending / blocked |
| Step completes | Status · "Where future steps add code" (remove completed block, add next) |
| Step scope changes | Status · Pending / blocked |

**Rule of thumb:** if a future maintainer can't tell from this doc where a new file should go, the doc is stale — update the "Repository layout" tree and the relevant step's "Where future steps add code" block.
