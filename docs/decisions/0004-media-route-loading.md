# ADR-0004: Media route loading strategy

- **Status:** Accepted
- **Date:** 2026-11-10
- **Deciders:** signage team
- **Consulted:** —
- **Informed:** future maintainers

## Context

Media is served from `GET /media/files/{contentId}/{filename}` — a static
file response. It has no session, no CSRF, no cookies, no view data.

Three approaches were on the table for loading `routes/media.php`:

| # | Approach | Where |
|---|---|---|
| A | `require base_path('routes/media.php')` inside `then:` in `bootstrap/app.php` | Inline |
| B | `Route::group([], base_path('routes/media.php'))` inside `then:` | Inline, framework API |
| C | Dedicated `MediaRouteServiceProvider` that calls `Route::middleware([])->group(...)` | Provider |
| D | Register in `routes/web.php` with `->withoutMiddleware([...])` | Wrong layer |

Option D was rejected immediately — `withoutMiddleware()` requires listing
every middleware you want to skip by FQCN. When a new middleware is added
to the `web` group later, the media route silently inherits it. Brittle.

## Decision

**Option C — dedicated `MediaRouteServiceProvider`.**

```php
// app/Providers/MediaRouteServiceProvider.php
class MediaRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware([])->group(base_path('routes/media.php'));
    }
}
```

Registered in `bootstrap/providers.php`.

## Rationale

- **Media is its own concern.** Static files, no session, no CSRF, no cookies.
  Grouping its loading logic keeps the concern together with the file.
- **Grows cleanly.** When thumbnails, streaming, or range requests arrive,
  they belong in `routes/media.php` and inherit the same zero-middleware
  context without touching `bootstrap/app.php`.
- **Evolvable to named group.** When a second no-middleware file appears
  (uploads, webhooks), promote to a `stateless` middleware group inside
  the same provider — one file, one registration.
- **Framework API, not procedural.** `Route::middleware([])->group(...)` is
  public Route facade API. `require` treats `bootstrap/app.php` as a PHP
  script, not Laravel config.

## Consequences

**Positive:**
- Media concern is discoverable: `ls app/Providers | grep Media` and
  `ls routes | grep media`.
- Zero risk of accidental `web` middleware inheritance.
- Adding media routes touches one file (`routes/media.php`), never
  `bootstrap/app.php`.

**Negative:**
- Route discovery now spans **four** places: `bootstrap/app.php` (`web`,
  `api`, `console`, `/up`) + `MediaRouteServiceProvider` (media).
  A contributor adding a new route must know which file to edit.
- The `routesAreCached()` guard is essential — without it, `route:cache`
  double-registers on the next boot.

**Mitigation:** documented in `docs/project-structure.md` under
"Where do routes live?".

## Alternatives rejected

| Option | Why rejected |
|---|---|
| A — `require` inline | Works, but treats `bootstrap/app.php` as procedural PHP; harder to grep for "who loads media" |
| B — `Route::group([])` inline | Correct framework API, but still one-off inline in bootstrap; doesn't scale |
| D — `web.php` + `withoutMiddleware` | Brittle — silently inherits any new `web` middleware |

## Review trigger

Superseded when **either**:
- A second no-middleware route file appears → promote to a named `stateless`
  middleware group inside the provider, and revisit this ADR,
- **or** the project moves to Laravel 12+ where the ServiceProvider route
  loading pattern changes materially.