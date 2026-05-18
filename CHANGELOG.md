# Changelog

All notable changes to AppStoreCat will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.6] - 2026-05-18

### Added
- **Test suite** — Pest 4 infrastructure with Feature, Unit, Connector, Job, and Regression suites. 402 tests / 1418 assertions, all green. Run via `make test` or `make test EXTRA_ARGS="--filter=..."`.
- Model factories for every domain model used by the suite.
- Research audit notes under `plans/research/` covering 16 production tables (sizes, hot indexes, dead columns, sparsity, recompression candidates).
- External Sensor Tower link in the app detail sidebar for quick competitive cross-reference.
- Nightly `cleanup-failed-items` cron at 04:00 to age out stale `failed_items` rows on `sync_statuses`.
- Nightly `queue:prune-failed --hours=168` at 04:30 to keep `failed_jobs` bounded at one week.

### Changed
- **Database footprint cut from 10.5 GB to 4.2 GB (-61%)** via three migrations applying `ROW_FORMAT=COMPRESSED` and dropping redundant indexes / dead columns:
  - `trending_chart_entries` (43.5M rows): 7.7 GB → 2.6 GB (-66%)
  - `app_store_listings` (520K rows): 2.8 GB → 1.5 GB (-47%)
  - `app_metrics` (970K rows): 273 MB → 106 MB (-61%)
- `ReconcileFailedItemsJob` now sets `$timeout = 1800`, fixes a ternary bug that was retriggering parallel dispatches, and aligns the queue `retry_after` to 1810 — eliminating overlapping reconcile runs.
- Connector boundary now sanitizes unparseable date strings (e.g. Google Play's "Never updated") before reaching the parser, removing ~1981 failed_jobs/month at their source.
- `App::discover()` guards against empty `external_id` instead of writing ghost rows.
- `AppDetailResource.file_size_bytes` reads from `app_versions` as the single source of truth.

### Fixed
- `app_store_listings.subtitle` widened from `VARCHAR(255)` to `TEXT`, unblocking ~4284 silent insert failures where subtitles exceeded the byte limit under multibyte encoding.
- `KeywordController` and `DashboardController` now return a non-null `displayName` (previously null for apps with only locale-scoped names).
- `StoreAppRequest` returns `422` for invalid `platform` values instead of bubbling a 500.
- Dead reads and unsafe writes cleaned up across controllers and models (`App`, `ChartEntry`, `AppSyncer`, etc.).

### Removed
- 5 dead columns dropped from `app_metrics`; ghost `apps` columns removed from `syncIdentity()`.
- `plans/database/trending-chart-entries-sparse-storage.md` outlined a sparse refactor but was not needed — `ROW_FORMAT=COMPRESSED` delivered the same disk win without schema change.

### Operations notes
- MySQL `binlog_expire_logs_seconds` recommendation lowered to **7 days** (604800) — the upstream default of 30 days is wasteful for single-node deployments without replication consumers.

## [1.2.5] - 2026-04-27

### Fixed
- **`add_competitor` no longer requires you to track the competitor first.** The previous flow forced two steps — `track_app(competitor)` to create the row, then `add_competitor` with the resulting internal id — which polluted `list_tracked_apps` with apps the user only ever wanted as competitors, and made `untrack_app` either lose the competitor relationships or leave the watchlist messy.
- The new flow: pass `competitor_external_id` (and optionally `competitor_platform`, defaults to the parent app's platform). The server registers the competitor's `apps` row on demand via the existing `AppRegistrar`, but does **not** attach it to the caller's `user_apps` watchlist. So competitors show up in reports, charts, and change feeds, but not in MyApps.
- Existing payloads using `competitor_app_id` (internal id) continue to work unchanged — the field is now optional alongside `competitor_external_id`, and exactly one of the two is required.

### Changed
- `AppRegistrar` gains an `ensureExists($externalId, $platform): App` method that mirrors `register()` minus the `user_apps` attach. Refactored `register()` to call it internally so the firstOrCreate logic stays in one place.
- `StoreCompetitorRequest` schema and OpenAPI annotation document both flows, with `competitor_external_id` listed as the recommended one.
- MCP `add_competitor` tool description updated to drop the now-obsolete "track the competitor first" instruction; new example in `docs/en/services/mcp.md` and the inline tool table.
- No database migration. No schema change. No web UI change. The competitor add dialog in the SPA still works through `competitor_app_id` because that path is preserved.

## [1.2.4] - 2026-04-27

### Added
- **MCP write tools** — the server expands from 28 read-only tools to 32 (28 read + 4 write) with no API changes:
  - `track_app(platform, external_id)` — adds the app to the caller's watchlist; resolves and creates the row from the store if needed and queues a sync.
  - `untrack_app(platform, external_id)` — removes the app from the watchlist (and any competitor rows involving it).
  - `add_competitor(platform, external_id, competitor_app_id, relationship?)` — links a competitor to a tracked app. `competitor_app_id` is the internal numeric id from `get_app`.
  - `remove_competitor(platform, external_id, competitor_id)` — drops a competitor relationship.
- Write tools carry standard MCP hints (`readOnlyHint: false`, `destructiveHint` on the destructive ones, `idempotentHint` where appropriate). Claude Code surfaces these and asks for confirmation before invoking them.
- `client.ts` gains an `apiSend(method, path, body?)` helper for POST / DELETE / PUT / PATCH alongside the existing `apiGet`. Empty 204 responses are normalized into `{"status":204}` so LLMs always see a structured payload.

### Changed
- README, landing page, llms.txt, and `mcp/README.md` updated from "28 tools" to "32 tools (28 read + 4 write)".
- `docs/en/services/mcp.md` grows a new "Read vs Write" section explaining the hint semantics and showing a chained track-then-add-competitor example.

## [1.2.3] - 2026-04-27

### Fixed
- `make release` is now idempotent. Each git step (`commit`, `tag`, `gh release create`) checks whether it has already been done and skips itself cleanly instead of failing the pipeline. v1.2.2's release exited with `make: *** [release] Error 1` after Docker push and npm publish succeeded but the version bump was already committed; the leftover steps had to be run by hand. This shouldn't happen again.

## [1.2.2] - 2026-04-27

### Added
- Astro + Starlight docs site live at `https://appstorecat.github.io/appstorecat/`. Installation page is now the docs homepage so users land directly on the install steps.
- Static redirect from the old `/getting-started/installation` URL to the new root.

### Changed
- Landing's version badges (header pill and hero eyebrow) now read from `VERSION` at build time via Vite's `__APP_VERSION__` define, replacing the hard-coded `v1.2`.
- Landing documentation links use trailing slashes to match Starlight's emit format and avoid a 301 round-trip on every doc click.
- Homepage card grid in `docs-site` now respects `DOCS_SITE_BASE`, so deploys under a sub-path (`/appstorecat`) link correctly.

## [1.2.1] - 2026-04-27

### Added
- Astro + Starlight documentation site under `docs-site/`, deployed to GitHub Pages on every push that touches `docs/` or `docs-site/`. Source markdown stays in `docs/en/` and is mirrored into the Starlight content collection by `scripts/sync-docs.mjs` at build time. Search index built in via Pagefind (no Algolia account required).
- `.github/workflows/docs.yml` — install → sync → build → deploy pipeline; concurrency-guarded.
- `mcp/README.md` so the npm page for `@appstorecat/mcp` is no longer empty: quick install, env vars, 28-tool category table, architecture diagram.
- `docs/en/getting-started/install-script.md` documenting what `install.sh` does, what it does NOT do, and how to verify it before piping to shell.
- `FORWARD_REDIS_PORT=7465` in both `.env.*.example` files so Redis joins the **746x** host-port series.

### Changed
- **Landing page rewrite.** Centered hero with two equal install entry points (self-host curl / Claude Code MCP) as togglable tabs, dotted-grid background, metric strip (28 MCP tools · 230+ countries · 50 languages · 60s · MIT), three use-case pillars (ASO / product / indie), six-card feature grid, dedicated MCP section, self-host block, comparison strip versus commercial alternatives, refreshed FAQ. Header gained Login / Try Demo (or Dashboard when authenticated) alongside the GitHub Star button.
- **README rewrite.** Quick Start now offers Option A (`curl` one-liner) and Option B (manual git clone) at equal visual weight; an MCP Quick Start section ships immediately under it. Feature list collapsed from nine screenshot-heavy blocks into a single inline table. Documentation section is now compact category lines instead of a 30-line vertical list. Doc URLs point at the live site (`appstorecat.github.io/appstorecat/`).
- `web/public/install.sh` is now production-ready: dependency + Docker daemon + Compose v2 checks, port-conflict warnings on `7460–7465`, `.env.development.example` → `.env` copy step, idempotent re-runs (`git pull --ff-only`), 120-second healthcheck poll on `localhost:7461`, anchor-style `APPSTORECAT_REPO_URL` / `APPSTORECAT_DIR` env overrides, structured error output with troubleshooting hints.
- `web/public/llms.txt` rewritten with Quick Start, MCP Quick Start, architecture, full feature list, and a 12-link documentation index.
- `docs/en/deployment/production.md` substantially expanded: Caddyfile / Nginx / Traefik real configs, `mysqldump → S3` backup script + cron, ufw firewall rules, log rotation, upgrade + rollback procedure, security checklist.
- `docs/en/reference/environment-variables.md` filled in the missing surface area: MCP env (`APPSTORECAT_API_TOKEN`, `APPSTORECAT_API_URL`), Sanctum (`SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`), supervisor tunables, frontend build vars, expanded logging.
- Host-side ports now consistently use the **746x** series across `.env.*.example`, `docker-compose.yml`, all docs, install.sh, and architecture diagrams. Previous Redis default `6379` switched to `7465` (container port stays `6379`).
- Landing page documentation links now resolve to `https://appstorecat.github.io/appstorecat/...` instead of raw GitHub blob URLs.
- `lint.yml` and `tests.yml` set to `workflow_dispatch`-only while the underlying suites stabilize. The only push-triggered workflow is `docs.yml`.

### Removed
- All Turkish documentation under `docs/tr/` (39 files) and the `README-tr.md` mirror. The TR docs were ASCII-stripped and out of sync with the EN source; we are EN-only going forward.

### Fixed
- README's "Documentation" section now links the MCP server doc (the file existed but was previously orphaned, hiding the project's headline feature from anyone arriving via README).
- `production.md` example env block previously listed `FORWARD_DB_PORT=3306` (mixing host and container ports); now correctly `7464`, with internal-vs-host-port relationships called out throughout.

## [1.2.0] - 2026-04-22

### Added
- **MCP server** expanded from a single `get_categories` tool to a 25-tool read-only surface covering apps, competitors, changes, charts, ratings, keywords, publishers, explorer, reference, and dashboard endpoints. Every tool is Swagger-strict (zod inputs mirror the Swagger parameter list exactly) and chain-first (response IDs are preserved so callers can plan multi-step lookups).
- MCP client now serializes array (`foo[]=a&foo[]=b`) and object (`foo[key]=v`) query params, interpolates path parameters via a shared `buildPath()` helper, and normalizes API errors into structured tool results instead of throwing.
- Shared `mcp/src/tools/_schemas.ts` with reusable zod primitives (`Platform`, `ExternalId`, `DateStr`, `Ngram`, …) to keep every tool input faithful to the OpenAPI contract.

### Fixed
- Publisher `/store-apps` endpoint now returns the documented `{apps: [...]}` envelope. `BaseResource` disables Laravel's default `data` wrapper, so the previous `StoreAppResource::collection()` return produced a bare JSON array and the publisher detail page silently rendered zero apps.

### Removed
- `mcp/src/tools/meta.ts` (superseded by `reference.ts` + other category-specific modules).

## [1.1.3] - 2026-04-20

### Added
- `apps.bundle_id` column for iOS bundle identifiers (populated lazily)
- FK `apps.origin_country_code` → `countries.code`
- FK `app_metrics.country_code` → `countries.code` (tightened from VARCHAR(10) to CHAR(2))
- Indexes on `apps.last_synced_at`, `apps.discovered_from`, composite `(platform, is_available, last_synced_at)`; `app_store_listings(app_id, locale, fetched_at)` and `.checksum`; `app_store_listing_changes(app_id, field_changed, detected_at)`; `app_versions.release_date` and `(app_id, release_date)`; `store_categories(platform, parent_id)`; `trending_chart_entries.app_id`; `sync_statuses.job_id`
- `Country` row `zz` (Global) to host Android metrics that are not country-specific
- `SYNC_{IOS,ANDROID}_TRACKED_BATCH_SIZE` (default 5) controls how many apps the scheduler dispatches per 20-minute tick
- Job performance logging for queue monitoring
- Open-source documentation restructuring under `docs/en`

### Changed
- Renamed columns for naming consistency: `apps.origin_country` → `origin_country_code`, `apps.display_icon` → `icon_url`, `app_store_listings.language` → `locale`, `app_store_listing_changes.language` → `locale`, `trending_charts.country` → `country_code`
- API query parameters / response keys aligned to new names: `?country=` → `?country_code=`, `?language=` → `?locale=`
- `AppMetric::GLOBAL_COUNTRY` from `'GLOBAL'` to ISO 3166 user-assigned `'zz'`
- `locale_added` / `locale_removed` replace `language_added` / `language_removed` in `StoreListingChange.field_changed`

### Fixed
- Chart fetch exceptions now always propagate for retry and failed_jobs tracking
- Fallback to web-scraped iPhone screenshots when iTunes API returns none
- App icon aspect ratio in explorer icons page
- Skip listing-change detection within the same version (prevents spurious diffs during intra-version syncs)

### Removed
- Dropped the separate discovery sync command, its dedicated queues, and its env vars — replaced by a tiered fallback inside `appstorecat:apps:sync-tracked` (tracked → competitor → backlog, all sharing the `sync-tracked-{platform}` queue and the tracked staleness window)

## [1.1.2] - 2026-04-19

### Added
- Daily rating history with per-star breakdown chart

### Changed
- Changes pages rebuilt as a grouped feed on shared primitives
- Country/version controls shared with Keyword Density tab

## [1.1.1] - 2026-04-18

### Added
- Google Analytics 4 via runtime env injection
- Product Hunt badge on landing page
- Responsive pass across web UI (shadcn-first sidebar, unified mobile/desktop filter bar)

### Fixed
- Invalidate all tracking-dependent lists on track toggle
- Hero CTA responsive layout on landing

## [1.0.1] - 2026-04-18

### Added
- Rankings tab on app detail page with country/collection/category pivot and date navigation
- On-demand sync queues (`sync-on-demand-ios`, `sync-on-demand-android`) dispatched from the UI when an app's data is stale, so user-triggered refreshes don't wait behind the cron backlog
- `HasPlatform` trait with `scopePlatform()` helper and `PlatformCast` for uniform platform input handling

### Changed
- `platform` column on `apps`, `publishers`, `store_categories`, and `chart_snapshots` switched from VARCHAR to TINYINT (int-backed enum: iOS=1, Android=2). JSON API output still uses the slug strings `ios` / `android`
- Keyword density is now computed on-the-fly from the current `StoreListing` on every request — no persistent storage
- Sync scheduler cadence changed from daily to every 20 minutes for both discovery and tracked pools across both platforms
- Default scraper throttle rates raised: `APPSTORE_THROTTLE_SYNC_JOBS` 3→5, `GPLAY_THROTTLE_SYNC_JOBS` 2→5 per minute
- Default discovery app refresh window lowered from 72h to 24h to match the tracked cadence

### Fixed
- `trending_charts.category_id` is now NOT NULL; "overall" charts use a dedicated "All" sentinel `StoreCategory` row (iOS id=1, Android id=43) with `external_id = NULL`
- App rediscovery refreshes cached `display_name` and `display_icon` when they differ from store data

### Removed
- `app_keyword_densities` table and `AppKeywordDensity` model (keyword density no longer persisted)

## [0.0.3] - 2026-04-01

### Added
- Multi-language store listing support across full stack
- Multi-country support with per-platform country activation
- Price and currency tracking across all layers
- Daily chart sync with priority and platform-separated queues
- Stop words filter for keyword analysis (50 languages)
- Searchable country/language selectors
- Removed from Store badge for unavailable apps
- Explorer pages for screenshots and app icons
- Review sync pipeline with multi-page support
- Configurable app discovery per source
- App sync pipeline with dual queue system (tracked/discovery)
- Trending charts with automatic data collection
- Media proxy for app icons and listing assets
- Publisher discovery and search

### Changed
- Refactored to platform-separated queue architecture (iOS/Android independent)
- Refactored throttling to job-level with Redis
- Split all config into per-platform iOS/Android keys
- Language-based unique listings (removed country_code dependency)
- Renamed reviews table to app_reviews

### Removed
- DNA build pipeline (replaced by trending-based data collection)
- Unused Dashboard page and AddAppModal component
- Unused database columns and schema cleanup

## [0.0.2] - 2026-03-01

### Added
- Production Docker deployment with supervisor
- Queue worker and scheduler configuration
- Orval API client generation

### Changed
- Optimized Dockerfile layer caching and image size
- Renamed Docker services with appstorecat- prefix
- Renamed Category to StoreCategory with seeded data

### Fixed
- Field-level validation errors on auth forms
- Absolute URLs for storage images in API responses
- npm vulnerability fixes

## 0.0.1 - 2026-02-15

### Added
- Initial Laravel project setup
- Database schema and core models
- Store connectors for iTunes and Google Play
- Auth API with Sanctum token authentication
- App tracking and store search API
- Store listing and version endpoints
- Reviews API with filters and rating summary
- Keyword density analysis and comparison API
- Competitor tracking API
- Dashboard summary API
- Publisher pages API with search and bulk import
- OpenAPI documentation with L5-Swagger
- React frontend with Vite and shadcn/ui
- Frontend auth, app detail, keyword, competitor, changes, publisher, settings pages
- Sidebar navigation with theme toggle

[Unreleased]: https://github.com/appstorecat/appstorecat/compare/v1.2.6...HEAD
[1.2.6]: https://github.com/appstorecat/appstorecat/compare/v1.2.5...v1.2.6
[1.2.5]: https://github.com/appstorecat/appstorecat/compare/v1.2.4...v1.2.5
[1.2.4]: https://github.com/appstorecat/appstorecat/compare/v1.2.3...v1.2.4
[1.2.3]: https://github.com/appstorecat/appstorecat/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/appstorecat/appstorecat/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/appstorecat/appstorecat/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/appstorecat/appstorecat/compare/v1.1.3...v1.2.0
[1.1.3]: https://github.com/appstorecat/appstorecat/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/appstorecat/appstorecat/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/appstorecat/appstorecat/compare/v1.0.1...v1.1.1
[1.0.1]: https://github.com/appstorecat/appstorecat/compare/v0.0.4...v1.0.1
[0.0.4]: https://github.com/appstorecat/appstorecat/compare/v0.0.3...v0.0.4
[0.0.3]: https://github.com/appstorecat/appstorecat/compare/v0.0.2...v0.0.3
[0.0.2]: https://github.com/appstorecat/appstorecat/releases/tag/v0.0.2
