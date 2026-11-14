# ADR-0001: API contract assumptions

## Context

Step 5 of TASK-01 requires implementing reference APIs "in `04-API-CONTRACT.md`"
and declares *"Done when: a Pest test asserts the envelope, field names and
pagination for each endpoint."*

`04-API-CONTRACT.md` was not provided in the task handoff.

Two options:

1. **Block Step 5** until the doc arrives — no timeline, and Step 6/7 are
   also blocked; whole sprint stalls.
2. **Infer the contract** from the only concrete evidence available (the
   Step 7 sample response) and **isolate every guess** so correction is one
   file, not a refactor.

I am taking option 2.

## Decision

Implement reference APIs with inferred conventions, isolate guesses in one
file, and document each guess with confidence scoring.

### Evidence used

Only the **Step 7 response sample** in the task doc provides concrete evidence:

| Observation | Consequence |
|---|---|
| Envelope: `{ ResultCode, Message, Data }` | Confirmed by Step 5 text too |
| `ResultCode` is per-endpoint (`5000` list, `4000` playback) | Success code is a parameter, not a constant |
| `Message` always present, `null` on success | Always emit the key |
| `Data` is an object wrapper, not `Data: [...]` | Envelope wrapper, not raw array |
| Field names PascalCase (`ContentId`, `ClientFileName`) | Mirror the style |
| `Id` not `ID` | Use `Id` |
| Legacy naming is inconsistent (`Contentpath`, `UploadeDateTime`) | Field names must be validated against fixtures, not inferred by rule |

### Guesses — where they live

Every guess is centralized in `app/Legacy/Codes.php` (values) and the
`*Resource` classes (field names). Changing a guess = editing one file, no
controller or test rewrite.

| Guess | Value | Confidence | Evidence |
|---|---|---|---|
| `SUCCESS_LIST` | `5000` | High | Step 5 text |
| `SUCCESS_PLAYBACK` | `4000` | High | Step 7 sample |
| `ERROR_SERVER` | `5001` | Low | Invented |
| `ERROR_NOT_FOUND` | `4004` | Low | Invented |
| `ERROR_VALIDATION` | `4001` | Low | Invented |
| Request params `pageIndex`, `rowNumber` | as named | High | Step 5 text |
| Response keys `Items`, `TotalCount`, `PageIndex`, `RowNumber` | PascalCase | Medium | Inferred from param names |
| `pageIndex` is 1-indexed | `1` | Medium | Common in Angular-era APIs |
| `rowNumber` default | `20` | Low | Arbitrary |
| `rowNumber` max | `500` | Low | Safety cap |
| Every resource field in PascalCase | `ContentId` → `ContentId` style | Medium | Extrapolated from Step 7 |
| `Id` casing | `Id` | High | Confirmed by Step 7 |
| `CreatedAt` / `UpdatedAt` exposed | **No** | Low | Not in Step 7 sample |
| HTTP status on success | `200` | High | HTTP convention |
| HTTP status on validation error | `422` | Medium | Laravel default |

Every resource field has an inline `// TODO(contract)` comment.

### Correction path when fixtures arrive

Step 9 captures legacy responses into `tests/Fixtures/legacy/api/`. When
they land:

1. A fixture-diff Pest test is added asserting our response matches captured
   fixtures (keys, casing, ordering, nulls).
2. Every divergence is fixed in `app/Legacy/Codes.php` or the affected
   `*Resource`.
3. `TODO(contract)` comments that are resolved get removed.

No controller change, no test rewrite beyond un-todoing.

## Consequences

**Positive:**
- Step 5 ships without blocking.
- Envelope + pagination + search + sort mechanics are correct regardless
  of field names.
- Every guess has documented evidence.

**Negative:**
- Field names may not match legacy. The Angular client cannot go live until
  Step 9 fixtures confirm the contract.
- `pageIndex` base (1 vs 0) and `rowNumber` default (20) are unverified.

**Neutral:**
- HTTP-status decisions may need to flip to "always 200 with ResultCode"
  once fixtures show what the client expects.

## Alternatives considered

1. **Block Step 5 until contract arrives.** Rejected — no timeline; stalls
   the sprint.
2. **Use Laravel defaults (snake_case), ignore legacy.** Rejected — the
   legacy client is the whole point. Compatibility is the deliverable.
3. **Guess without an ADR.** Rejected — future maintainer has no way to
   distinguish derived choices from invented ones.

## Review trigger

Superseded when **either**:
- `04-API-CONTRACT.md` is provided by the reviewer, **or**
- Step 9 fixtures are captured.

At that point, ADR `0002-api-contract-confirmed.md` will list corrections.