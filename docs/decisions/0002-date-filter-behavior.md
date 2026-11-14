# ADR-0002: Date-window filter behavior (fixed vs preserved)

- **Status:** Accepted (revisitable)
- **Date:** 2026-11-10
- **Deciders:** signage team
- **Consulted:** Ehsan Tork (task owner) — awaiting answer

## Context

TASK-01 Step 6, rule 6:

> Filter out `contents.deleted = true` and, if agreed, out-of-window
> `from_date`/`to_date`.
>
> Important: the legacy filter has an operator-precedence bug. Decide with
> the reviewer whether to preserve or fix it, and write a test that
> documents the choice.

The legacy filter is reported to have an operator-precedence bug. Common
shape: `WHERE deleted = false AND from <= now OR to >= now`, which due to
AND-before-OR precedence becomes
`(deleted = false AND from <= now) OR (to >= now)` — anything with a future
`to_date` bypasses the deleted filter.

Reviewer unavailable before Step 6 starts. Need a default choice.

## Decision

**Fix the bug.** The resolver uses correct parentheses:

```sql
WHERE deleted = false
  AND (from_date IS NULL OR from_date <= :today)
  AND (to_date   IS NULL OR to_date   >= :today)
```

## Rationale

- Task explicitly says "Filter out `contents.deleted = true`" — mandatory.
  Preserving the bug lets deleted content leak through when `to_date` is
  in the future, contradicting the mandatory rule.
- Read-only migration: showing fewer items is a smaller risk than showing
  content the admin believed deleted.
- Fix is localized: two where-clauses in `PlaylistResolver`, plus one test.

## Consequences

**Positive:** deleted content hidden in every path; date window predictable.

**Negative:** if the legacy client visibly depends on the buggy behavior,
the migrated client will show fewer items. Low probability.

**Neutral:** toggleable. To preserve the bug, remove the `contents.deleted`
filter in the three resolver methods and replace the two date `where(...)`
clauses with a single `whereRaw('from_date <= ? OR to_date >= ?')` (no
parentheses).

## Test that documents the choice

`PlaylistResolverTest`:
`it('excludes deleted content even when to_date is in the future (fixed precedence, ADR-0002)')`

Fails immediately if the resolver regresses to buggy behavior.

## Review trigger

Superseded when the reviewer answers "preserve or fix." If preserve, flip
the test to assert the buggy behavior and unparenthesize the date clauses.