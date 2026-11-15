# ADR-0007: Public (unauthenticated) broadcast channel for `playlist.refresh`

- **Status:** Accepted
- **Date:** 2026-11-15
- **Deciders:** signage team
- **Consulted:** Ehsan Tork (task owner) — pending PO review
- **Informed:** future maintainers

## Context

Reverb can serve three channel shapes:

| Shape | Who can subscribe | Auth mechanism |
|---|---|---|
| Public | Anyone who knows the channel name | None |
| Private | Authenticated users | `/broadcasting/auth` with session |
| Presence | Authenticated + presence tracking | Same + presence metadata |

The player needs to receive a `PlaylistRefresh` event so it can reload.
Which channel shape is appropriate?

## Decision

**Public channel `playlist.refresh`.** No authentication, no per-user
authorization, no per-monitor scoping at the channel level.

Refresh targeting (global vs specific monitor) is done **inside the event
payload**, and the player filters on `payload.ip`:

```js
case 'PlaylistRefresh': {
    const payload = JSON.parse(data.data || '{}');
    if (payload.ip === null || payload.ip === MONITOR_IP) {
        boot();
    }
    break;
}
```

## Rationale

- **What a subscriber gains:** knowing that "some monitor" reloaded its
  playlist. That's it. No content IDs, no URLs, no credentials — the
  event's `broadcastWith()` payload is `{ ip: "10.0.0.1" }` (or `{ ip: null }`).
- **What they can do with it:** trigger a reload of a playlist they can
  already fetch from `/api/v1/playback/monitor-contents`. No privilege
  escalation, no state mutation.
- **The real security concern is not subscription — it's triggering.**
  The `POST /api/v1/playback/refresh` endpoint is what an attacker would
  want to abuse (spam reloads, DoS the player). That's mitigated with
  `throttle:10,1` (10 per minute per IP). Channel auth wouldn't change
  that vector at all.
- **Private channel requires a session.** Signage players are anonymous
  kiosks. They have no login, no cookie, no session. `/broadcasting/auth`
  would 401 them immediately.

## Consequences

**Positive:**
- Zero auth complexity on the player side. One WebSocket connect, one
  subscribe, done.
- Any monitor can receive refresh events regardless of network topology
  (no need for monitor-specific auth tokens).
- Adding a new monitor requires no config change — it just connects.

**Negative:**
- Anyone who knows the channel name can subscribe. In practice, the
  channel name is `playlist.refresh` — trivially guessable.
- An attacker who subscribes learns which monitor IPs are triggering
  reloads (`broadcastWith()` includes `ip`). For signage deployments,
  monitor IPs are usually internal-network addresses — low value.

**Neutral:** if the future adds per-monitor private channels
(`private-monitor.10.0.0.1`), this ADR is superseded. The player would
need an auth token per monitor. Not worth it until the PO requests it.

## Alternatives rejected

| Option | Why rejected |
|---|---|
| Private channel | Requires session auth — signage players are anonymous |
| Presence channel | Same auth barrier + presence tracking not needed |
| Per-monitor channels (`monitor.10.0.0.1`) | More channels, more subscribe logic, same payload — no security gain without private-channel auth on top |
| Polling (`/api/v1/playback/version` every N seconds) | Works, but 5-30s lag between admin action and player update; Reverb is a strict improvement |

## Review trigger

Superseded when **either**:
- The PO requires "only authorized admins can trigger refreshes" → add
  an auth token to `POST /api/v1/playback/refresh`. That's a **separate**
  concern from channel auth — it's about who can broadcast, not who can
  listen. Update this ADR to split the two axes.
- **or** the player needs to receive sensitive events in the future
  (e.g. admin commands, remote config) → promote to private channels with
  per-monitor auth tokens.