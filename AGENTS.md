# My Catholic Calendar — agent notes

WordPress plugin that displays the Catholic liturgical calendar via the [LitCal API](https://litcal.johnromanodorazio.com/). Thin WP layer over `liturgical-calendar/components` (used unmodified). PHP 8.1+, WP 6.5+, Gutenberg dynamic block.

Status (`0.1.0`): REST API and the `my-catholic-calendar/day` block ship. Not built: admin settings UI, month/grid block, Interactivity API (declared in `package.json`, unused).

## Architecture

Everything talks to `MyCatholicCalendar\Contracts\LitCalGateway`, never to the vendor library or HTTP directly.

```
REST controller ─┐
                 ├─→ CalendarRepository ─→ LitCalGateway ─→ LitCalClient
render.php ──────┘        │                                    ├─ ApiClient (vendor)
   (via my_catholic_calendar())                                │     └─ WpHttpClient
                          └─→ MetadataAllowlist                └─ TransientCache (ours)
```

`CalendarRepository` (allowlist + fetch + 502 mapping) and `DayService` (filter a year down to one day) are shared by REST and the block. When we inject a custom HTTP client, the vendor library ignores its own cache — **all caching is ours**, via WP transients. Do not wire the library's PSR-16 cache.

The browser never calls LitCal.

## Conventions

- **snake_case in PHP** (methods, `CalendarQuery::$calendar_id`). Vendor camelCase (`->yearType()`) stays at the adapter.
- **camelCase in `block.json` / JS** (`calendarId`, `showDate`) — Gutenberg, not a WPCS violation.
- Long arrays `array()`, Yoda (`null === $x`), tabs, WPCS docblocks.
- PSR-4 StudlyCase files in `src/` — `phpcs.xml.dist` exempts `WordPress.Files.FileName.*` there. Keep new classes in `src/`.
- `Contracts/` and value objects stay WordPress-agnostic. WP calls belong in adapters: `WpHttpClient`, `TransientCache`, `Options`, `LitCalClient::create()`, `Plugin`, `Repositories/`, `Rest/`, `Blocks/`.
- `BlockRegistrar` uses `constant( 'MyCatholicCalendar\\MY_CATHOLIC_CALENDAR_PATH' )` for PHPStan. Don't "simplify" to a bare constant without checking `composer run phpstan`.
- Prefix globals with `my_catholic_calendar` / `MyCatholicCalendar` / `MY_CATHOLIC_CALENDAR`. CSS BEM is `mcc-day`.

## Domain (silent-wrong if missed)

- Types: `general` | `nation` | `diocese`. `calendar_id` required except for general. Validate ids against live `/calendars` metadata, don't hardcode.
- `year_type`: `LITURGICAL` (default) vs `CIVIL`. Year range 1970–9999. Past years are immutable → `YEAR_IN_SECONDS`; current/future → `Options::cache_ttl()`.
- Calendar fetch is POST (vendor); `/calendars` is GET. Responses: `litcal`, `settings`, `metadata`, `messages`.
- `color` / `color_lcl` may be an array. Locale controls `*_lcl`. A date can have multiple events (vigil + feast).
- Locales match on the primary language subtag (`en` ⇄ `en_US`) in `MetadataAllowlist`.

## Public contract (treat as additive even pre-1.0)

- REST `my-catholic-calendar/v1`: `/calendar`, `/day`, `/calendars` (public, read-only).
- Block `my-catholic-calendar/day` attributes.
- Hooks: `my_catholic_calendar_services`, `my_catholic_calendar_api_url`.
- Option `my_catholic_calendar_settings` — keys in `Options::defaults()`.
- REST error `my_catholic_calendar_upstream_unavailable` (502, never leak the exception message).

`/day` `date` is validated in the route arg schema (`checkdate()`), not in `get_day()`. Test the `validate_callback` via a mocked `register_rest_route()`, don't call `get_day()` with a bad date (it would roll over).

## Commands

```bash
composer install
composer run phpcs      # WPCS — must be clean
composer run phpstan    # level 6, src/ only
composer run test       # PHPUnit + Brain Monkey, no live WP
npm run build | start
npm run env:start       # wp-env, needs Docker
```

`phpcs`, `phpstan`, and `test` are merge gates. Run all three after PHP changes. `phpcbf` first for formatting. PHPStan does **not** scan `blocks/*/render.php`.

## Tests

Stubs in `tests/stubs/wp-stubs.php` (Brain Monkey fakes functions, not classes). `Fakes/FakeLitCalGateway` is the gateway double.

**Whenever a constructor's dependencies change, grep `tests/` and update every call site.** Green phpcs/phpstan does not mean the tests still instantiate.

## Pitfalls

- Vendor `ApiClient` is a process-wide singleton (`getInstance()` — later config is ignored). `calendar()` goes through it; `metadata()` does not (it uses our `WpHttpClient` directly). If another plugin bundling the same library inits first, calendar fetches can bypass our HTTP adapter. Don't try to re-init at runtime; the real fix is php-scoper.
- `block.json` `date` is unused. `render.php` always uses `current_datetime()`. Setting the attribute does nothing.
- Transient keys must start with `my_catholic_calendar_` (`CalendarQuery::cache_key()`, `LitCalClient::METADATA_KEY`). `TransientCache::set()` does not enforce this; `uninstall.php` and `flush()` only delete that prefix.
- `vendor/` and `build/` are gitignored and **must ship** in the WP.org zip. `.distignore` is an exclude-list; the release workflow installs Composer deps and runs `npm run build` before zipping.
