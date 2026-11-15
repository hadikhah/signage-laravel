# ADR-0008: Fixture capture strategy — golden files until legacy data arrives

- **Status:** Accepted
- **Date:** 2026-11-15
- **Deciders:** signage team
- **Consulted:** pending delivery of legacy export
- **Informed:** future maintainers

## Context

Step 9 requires "fixtures captured from the legacy app for each playlist
branch" and "a structural diff test: same keys, same casing, same enum
values for the covered fields."

The legacy Angular app is not available in this environment. We cannot
run it, cannot capture its responses, cannot validate our field names
against real evidence.

Three options:

| Option | Effect |
|---|---|
| A — Block Step 9 until legacy fixtures arrive | No timeline; blocks Steps 9 + 10 |
| B — Skip the structural diff test | No contract regression protection at all |
| C — Record **our current best-guess shape** as fixture files, test against them, mark for replacement | Contract is locked to a known state; divergence flagged by test; replacement is a file swap |

We take option C.

## Decision

**Golden-file fixtures based on the Step 7 sample.** Each fixture file is:

1. Named after the endpoint + branch (`playback-default.json`, `playback-share.json`, ...).
2. Populated with the shape we believe the legacy client expects — either from the Step 7 sample (playback) or extrapolated from PascalCase convention (reference APIs).
3. **Marked as `TODO(contract)`** — when PO delivers real legacy responses, these files are replaced wholesale.
4. Asserted **structurally** (keys, casing, types, enums) — values that are dynamic (ids, dates, timestamps) are not compared.

The fixture files are `tests/Fixtures/legacy/api/*.json`. The test file is
`tests/Feature/Contract/PlaybackContractTest.php` and
`tests/Feature/Contract/ReferenceApiContractTest.php`.

## Rationale

- **Locks the current state.** If a future contributor renames `Contentpath`
  to `ContentPath` (fixing the "typo"), the contract test fails immediately.
  They can't accidentally break the legacy contract.
- **Records the guess.** The fixture IS the documented guess. Reviewer
  opens `playback-default.json` and sees the exact shape we're committing to.
- **Cheap to replace.** When real legacy responses arrive:
  1. Dump the legacy response to a file
  2. `cp legacy-response.json tests/Fixtures/legacy/api/playback-default.json`
  3. Run test
  4. Fix whatever the test complains about (renamed fields, missing keys, wrong types)
  5. Update `TODO(contract)` comments — remove resolved ones

  One file, one test run, done.
- **No false confidence.** The structural test asserts our contract against
  *our recorded expectations*, not against nothing. When the real fixtures
  arrive, they either match (win) or diverge (the test tells us exactly
  what to fix).

## Consequences

**Positive:**
- Field names, casing, enum values, and PascalCase conventions are locked.
  Renaming anything → test fails.
- Per-branch coverage: default / share / view / base / empty are all
  independently asserted.
- The `TODO(contract)` markers in fixtures are visible when a reviewer
  opens them.
- Removing a field → test fails with the fixture's diff.

**Negative:**
- **The fixtures are our guesses, not legacy truth.** They lock OUR
  interpretation, which may be wrong. When the real legacy client hits
  our API, it may disagree. But this is not worse than the current state
  (no contract test); it's strictly better.
- Changing a legitimate behavior (e.g. adding `UpdatedAt` field) requires
  updating the fixture too. That's intentional — the contract is
  versioned, not implicitly extended.

**Neutral:** The fixture files are committed JSON. They're not
"generated" — they're source. Editing them is editing the contract.

## Alternatives rejected

| Option | Why rejected |
|---|---|
| A — Block on legacy fixtures | No timeline, blocks 2 steps |
| B — No structural test | Zero regression protection — a field rename ships silently |
| **Contract tests that snapshot our current output** | Would just assert "output matches output" — no independent record of intent |

## Review trigger

Superseded when **either**:
- Legacy Angular app output is captured and dropped into
  `tests/Fixtures/legacy/api/` (per-branch replacement, one commit),
- **or** PO confirms field names in writing (add the confirmation as a
  comment at the top of each fixture file).