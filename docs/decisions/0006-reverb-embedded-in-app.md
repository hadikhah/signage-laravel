# ADR-0006: Reverb embedded in `laravel.test` vs separate service

- **Status:** Accepted
- **Date:** 2026-11-15
- **Deciders:** signage team
- **Consulted:** —
- **Informed:** future maintainers

## Context

Reverb (Laravel's Pusher-compatible WebSocket server) needs to run as a
long-lived process. Two deployment shapes were available:

| Option | Shape |
|---|---|
| A — Separate container | `reverb` service in compose, own image, own lifecycle |
| B — Supervisor program | `[program:reverb]` in `docker/8.4/supervisord.conf`, inside `laravel.test` |
| C — Inline `command:` on a service | new `reverb` service with `command: php artisan reverb:start` (no supervisor) |

## Decision

**Option B — Supervisor program inside `laravel.test`.**

```ini
[program:reverb]
command=php artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/html
user=sail
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
```

Port `8080` published on `app` in `compose.yaml`. nginx proxies `/apps?/`
to `laravel.test:8080`.

## Rationale

- **Fewer moving parts.** One container, one image, one build. A separate
  `reverb` service would need to share the same image and the same bind
  mount — 90% of the config is duplicated for zero isolation benefit.
- **Shared filesystem.** Reverb reads `.env`, `config/`, `bootstrap/cache`
  from the same bind mount as php-fpm. No sync issues when you edit `.env`
  or run `config:cache`.
- **Portfolio consistent.** Vite and Reverb are both "processes inside
  `laravel.test`" in the current architecture. Introducing a third
  container shape only for Reverb adds a pattern the team has to learn.
- **Rebuild cycle.** `docker-compose up -d --build` already rebuilds the
  image; adding Reverb to `supervisord.conf` (which is bind-mounted) means
  `docker-compose restart app` picks up changes without a rebuild.

## Consequences

**Positive:**
- Single image, single container, single log stream (`docker-compose logs app`
  shows both php-fpm and Reverb lines).
- No duplicated bind mounts, no `.env` sync between two containers.
- Supervisor handles autostart + autorestart + process group signals.

**Negative:**
- Restarting Reverb alone (`supervisorctl restart reverb`) is possible but
  not as discoverable as `docker-compose restart reverb`.
- If Reverb crashes hard enough to bring supervisord down (unlikely — the
  master doesn't die when children do), it takes php-fpm with it. Ruled out
  by supervisord's design.
- Logs interleave. `docker-compose logs app | grep reverb` works but isn't
  as clean as a dedicated service.

**Neutral:** The nginx upstream was `reverb:8080` from the earlier stubbed
config; changed to `laravel.test:8080` to match this decision. Recorded
in the `docker/nginx/default.conf` diff.

## Alternatives rejected

| Option | Why rejected |
|---|---|
| A — Separate container | Duplicate image, duplicate bind mount, no isolation benefit |
| C — `command:` on a service without supervisor | Works, but then Vite-style `[program:*]` and service-style `command:` coexist — two patterns for the same class of problem |

## Review trigger

Superseded when **either**:
- Reverb needs to scale horizontally (`REVERB_SCALING_ENABLED=true` with
  Redis pub/sub across multiple Reverb nodes) — then a dedicated container
  is worth the isolation,
- **or** the dev stack drops `supervisord.conf` in favor of `command:` for
  every process, at which point the consistency argument flips.