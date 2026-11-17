# ADR-0010: Panel access and upload strategy

- **Status:** Accepted
- **Date:** 2026-11-17
- **Informed:** future maintainers

## Context

PO requested a minimal panel where a monitor can submit content for itself.
Two decisions dominate the design:

1. **Who can access the panel?** — The "self-service" requirement means
   the panel must be gated by the same identity the player uses: IP address.
2. **How are uploaded files stored?** — The reviewer flagged client-claimed
   extensions as untrustworthy. Trust decisions belong to the server, not
   the browser.

The panel is deliberately minimal: upload (image, video, URL), list,
delete. No users, no roles, no rich editing UI.

## Decisions

### Decision 1: IP-gated access, no session auth

The panel resolves the caller's monitor from the request IP using the same
`IpNormalizer` and dev `?ip=` override as `/monitor` and `/api/v1/playback/*`.
If no monitor matches, the panel shows a "No monitor registered" screen and
the API endpoints return 404.

No session, no login, no cookie. `GET /panel` is a stateless Blade render;
`POST /api/v1/panel/contents` and `DELETE /api/v1/panel/contents/{id}` are
stateless JSON endpoints.

**Rationale:**

- **Matches the player's identity model.** The kiosk browser has no user
  concept. Requiring a login would break the "monitor manages itself"
  workflow the PO asked for.
- **Same security boundary as the existing refresh endpoint** (ADR-0009).
  If "IP == identity" is acceptable for trigger-refresh, it's acceptable
  for upload-own-content.
- **No new infrastructure.** No users table additions, no session setup,
  no CSRF for kiosk POSTs, no auth middleware.

**Tradeoffs:**

- **Shared NAT collapses isolation.** Multiple monitors behind one gateway
  share `X-Real-IP`; any of them can manage any of them. Documented for PO.
- **No audit trail.** With no user, `contents.user_id` stays `NULL`.
  Uploads are distinguishable only by `created_at` and `client_file_name`.
- **Public-endpoint exposure.** The API routes are in the `api` middleware
  group (no CSRF). Rate-limited by `throttle:20,60`. For a trusted-network
  deployment this is acceptable; a public panel needs auth + CSRF before
  exposure.

### Decision 2: Server-detected extension, UUID filename

File storage uses `$file->hashName()` — Laravel's helper that combines a
random hash with an extension derived from **server-side MIME detection**
(Symfony's `guessExtension()`). The client-claimed filename is preserved
separately in `contents.client_file_name` for display.

Storage path: `storage/app/media/files/{content_id}/{hash}.{server_ext}`.
Matches `MediaController`'s expected layout and `signage:doctor`'s media
check.

**Rationale:**

- **Client extensions are attacker-controlled.** `evil.php` renamed to
  `evil.jpg` passes extension checks; `mimetypes:` validation on the
  *server-detected* MIME is the real gate. `hashName()` produces a name
  whose extension matches the detected type — a file that fails MIME
  detection never gets a valid extension.
- **UUID names prevent collisions.** Two monitors uploading `banner.jpg`
  in the same second don't overwrite each other.
- **Original name preserved** in `client_file_name` for the panel table
  and legacy Angular admin UI. That's the field the legacy client already
  reads for display.
- **Delete is not blocked by file naming.** Removing the row + file works
  regardless of the file's name.

**Tradeoffs:**

- **Orphan files on transaction rollback.** The file write happens inside
  the DB transaction but is not itself transactional. A `catch` block
  removes the file if the transaction rolls back — but if the process
  crashes between write and rollback, the file remains as an orphan.
  `signage:doctor` reports orphans; a future cleanup cron can sweep them.
- **`hashName()` extension is `jpg`, `png`, etc. — not `jpeg`.** Players
  don't care; the extension is a hint, not the source of truth. `mimetypes:`
  validation runs on `Content-Type`, not the extension.

## Consequences

**Positive:**

- Zero new infrastructure: no users, no sessions, no CSRF for kiosk POSTs.
- Uploads are hygienic by construction: server-detected extension,
  UUID name, original preserved for display.
- The pattern extends cleanly to future "attach existing content"
  endpoints (see ADR-0011 for concurrency).

**Negative:**

- Shared-NAT deployments lose per-monitor isolation (ADR-0009 same issue).
- No audit trail beyond `created_at` and `client_file_name`.
- Credentials in `content_url_user_passwords.password` are stored as
  plaintext. Tracked as `TODO(security)` in the model — requires a
  coordinated migration sequence (TEXT column + encrypted cast + importer
  awareness), not a one-file fix.

**Neutral:**

- Rate limit `throttle:20,60` (20 uploads per hour per IP). Adjustable
  without a schema change.

## Alternatives rejected

| Option | Why rejected |
|---|---|
| Session login per monitor | Contradicts "self-service from a kiosk" — kiosks have no login UI |
| Client-claimed extension | Security hole — extension is attacker-controlled |
| Global UUID without extension | `MediaController` needs an extension to serve correct `Content-Type`; doable but requires a header override |
| Separate panel container | No isolation benefit; duplicates image, bind mount, `.env` sync |
| CSRF-protected form POST | API route convention; kiosks have no browser session to send tokens |

## Review trigger

Superseded when **either**:

- PO requires per-monitor authentication (admin issues token, monitor
  presents it on each request) → add auth middleware, move routes to
  `web` middleware with CSRF,
- **or** deployment moves to a public network → same as above,
- **or** the "attach existing content to another monitor" feature lands
  → refresh this ADR's file-storage section to reflect reuse of existing
  files (not always-new content).