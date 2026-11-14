# ADR-0005: `?ip=` override scoping — inline env check, not config

- **Status:** Accepted
- **Date:** 2026-11-10
- **Deciders:** signage team
- **Consulted:** —
- **Informed:** future maintainers

## Context

The playback endpoint `GET /api/v1/playback/monitor-contents?view=...`
resolves the playlist by the request's source IP. During development and
testing, curl-ing from the host produces `127.0.0.1`, which matches no
monitor row in the fixture DB. A `?ip=` query parameter overrides the
source IP for exactly this case.

The parameter exists for dev/test convenience only. In production, using
`?ip=` would allow any client to claim any monitor's IP and read another
monitor's playlist — an impersonation vector.

Step 6 introduced a config key, `signage.expand_loopback_variants`, that
also gates environment-specific behavior (loopback variant matching).
Question arose: should `?ip=` reuse the same config key?

## Decision

**No.** The `?ip=` override stays gated by an **inline**
`app()->environment(['local', 'development', 'testing'])` check in
`PlaybackController::resolveIp()`. It is **not** moved to a config key,
and it is **not** coupled to `signage.expand_loopback_variants`.

```php
private function resolveIp(PlaybackRequest $request): string
{
    $ip = (string) $request->ip();

    if (app()->environment(['local', 'development', 'testing'])) {
        $override = $request->input('ip');
        if (is_string($override) && $override !== '') {
            $ip = $override;
        }
    }

    return $ip;
}
```

## Rationale

The two behaviors have **different risk profiles**:

| Behavior | Leak risk | Reversibility |
|---|---|---|
| `expand_loopback_variants` | Low — same monitor, different address forms | Config flip |
| `?ip=` override | **High — arbitrary IP impersonation** | Deliberate env change |

Coupling them under one flag means:

1. Turning on loopback variants for a staging demo (via `SIGNAGE_EXPAND_LOOPBACK=true`)
   also enables IP spoofing in that environment.
2. Someone reading `expand_loopback_variants=true` in a config file has no
   indication that `?ip=` is also enabled.
3. A `config:cache` build in `local` deployed to `production` would carry
   the flag over, silently permitting override in prod.

The inline `app()->environment()` check has none of these problems:

- Cannot be set via `.env` — requires actually re-provisioning `APP_ENV`
  (which is a deliberate, auditable infrastructure change).
- `config:cache` does not affect `app()->environment()` — it reads the
  `APP_ENV` binding at request time.
- Anyone reading `PlaybackController` sees the override gate adjacent to
  the code it protects.

## Consequences

**Positive:**
- Impersonation surface is impossible to enable accidentally via `.env`.
- Two independent environment-dependent behaviors do not share a switch.
- Reading `resolveIp()` tells you exactly what the gate is; no config
  indirection.

**Negative:**
- Minor inconsistency: one env-dependent behavior uses config
  (`expand_loopback_variants`), the other uses inline
  (`?ip=`). A future contributor might expect symmetry.
- Testing the override path in a Pest test requires `$this->app['env']`
  mutation **before** app boot, not `config(['...'])` — because there's no
  config key to override. This is intentional (see Rationale #3).

**Neutral:** if the override ever needs to be genuinely configurable
(e.g. a QA environment that accepts spoofed IPs by design), add a
**separate** `signage.allow_ip_override` config key — never reuse
`expand_loopback_variants`. And guard against the `config:cache` risk by
documenting "never cache config in `local` for deploy to prod."

## Alternatives rejected

| Option | Why rejected |
|---|---|
| Reuse `expand_loopback_variants` | Couples a low-risk enum tweak with a high-risk impersonation vector |
| New `signage.allow_ip_override` config key now | Adds a `.env`-settable switch for an impersonation vulnerability — exactly what we're trying to prevent |
| Query-param API key check | Larger surface; not required by spec; dev-only is the current scope |
| Special-case `127.0.0.1` source only | Doesn't help — `curl` already produces `127.0.0.1`; the problem is the *target* IP, not the *source* |

## Review trigger

Superseded when **either**:
- A QA environment genuinely needs `?ip=` override enabled → add
  `signage.allow_ip_override` (separate from `expand_loopback_variants`),
  update this ADR with the config:cache mitigation,
- **or** the playback endpoint is re-architected to no longer be dev/debuggable
  via curl (e.g. a dedicated dev-only debug header), removing the need for
  the query parameter entirely.