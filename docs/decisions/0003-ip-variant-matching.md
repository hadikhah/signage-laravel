# ADR-0003: IP lookup strategy — normalize-on-write vs query-with-variants

- **Status:** Accepted
- **Date:** 2026-11-10
- **Deciders:** signage team
- **Consulted:** —
- **Informed:** future maintainers

## Context

Monitors are looked up by IP (`PlaylistResolver::forMonitor($ip)`).
Postgres `inet` treats loopback as three distinct values:

| Stored | Meaning |
|---|---|
| `127.0.0.1` | IPv4 loopback |
| `::1` | IPv6 loopback |
| `::ffff:127.0.0.1` | IPv4-mapped IPv6 loopback |

`WHERE ip_address = '127.0.0.1'` misses rows stored as `::1`, and vice
versa. Two clients on the same machine, one dialing IPv4 and one dialing
IPv6, get different lookup results against the same monitor.

The spec explicitly calls for "`127.0.0.1` → `::1` compatibility in
local/dev" — so this is a known requirement, not a corner case.

Three possible strategies:

1. **Normalize on write.** Enforce a single canonical form (e.g. always
   IPv4 for loopback, always lowercase for IPv6). Requires a migration and
   a data rewrite. Future-proof but blocks Step 6 on schema work.
2. **Query with variants.** Build an array of every form the DB might
   contain for a given input and use `WHERE ip_address IN (...)`. No
   migration. Slightly heavier query for loopback only.
3. **Hybrid.** Normalize new rows + query with variants for old rows.
   Two rules to maintain; not obviously better than (2).

## Decision

**Option 2 — query with variants.**

Implemented in `App\Services\Playlist\IpNormalizer`:

- `normalize($ip)` — returns one canonical form for the input
  (`::ffff:127.0.0.1` → `127.0.0.1`, uppercase → lowercase)
- `variants($ip)` — returns **every** DB-storable form the input should
  match. Loopback returns 3 forms; everything else returns 1.
- `isLocal($ip)` — loopback check for debug paths

`PlaylistResolver::findMonitor()` uses `whereIn('ip_address', $variants)`,
never `where('ip_address', ...)`.

## Rationale

- **No schema change, no data migration** — Step 6 ships without blocking
  on a schema task.
- **Reversible** — the moment we decide to normalize-on-write, we add a
  migration and `variants()` returns a single form. One file changes.
- **Local + production safe** — production IPs (e.g. `10.0.0.1`) return a
  single-element variants array, so the `whereIn` is equivalent to `where`
  for the common case. No overhead outside loopback.
- **Idempotent** — `variants()` is pure; called twice returns the same array.

## Consequences

**Positive:**
- `127.0.0.1`, `::1`, and `::ffff:127.0.0.1` all resolve to the same
  monitor — matching legacy behavior.
- No downtime, no data rewrite.
- If a monitor is stored with `::ffff:10.0.0.1` and a client connects via
  `10.0.0.1`, lookup still fails — but that's a corner case outside the
  spec's loopback-compat mandate. Documented as a known limit.

**Negative:**
- The DB is not "clean" — the same physical monitor's loopback address can
  be stored three ways. Acceptable for a read-only slice; a future
  normalize-on-write migration would address it.
- `findMonitor()` is slightly more complex than `where('ip_address', $ip)`.

**Neutral:**
- `IpNormalizer` is a standalone service — reusable in Step 7's media route
  or any future IP-based lookup.

## Tests that document the choice

`tests/Unit/Services/Playlist/IpNormalizerTest.php`:
- `it('normalizes IPv4-mapped IPv6 to IPv4')` — canonical form
- `it('provides loopback variants for 127.0.0.1')` — 3-form array
- `it('provides a single variant for non-loopback IPv4')` — 1-form array

`tests/Unit/Services/Playlist/PlaylistResolverTest.php`:
- `it('resolves a monitor stored as ::1 when the request comes from 127.0.0.1')` —
  end-to-end proof that the variant strategy works.

## Review trigger

Superseded when:
- **either** a normalize-on-write migration lands (variants() shrinks to 1),
- **or** a client reports lookup failure for a non-loopback mixed-form IP
  (requires extending `variants()` to match IPv4-mapped IPv6 for all addresses,
  not just loopback).