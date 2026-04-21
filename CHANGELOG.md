# Changelog

All notable changes to AppStoreCat will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Open-source documentation restructuring
- Job performance logging for queue monitoring
- `apps.bundle_id` column for iOS bundle identifiers (populated lazily)
- FK `apps.origin_country_code` → `countries.code`
- FK `app_metrics.country_code` → `countries.code` (tightened from VARCHAR(10) to CHAR(2))
- Indexes on `apps.last_synced_at`, `apps.discovered_from`, composite `(platform, is_available, last_synced_at)`; `app_store_listings(app_id, locale, fetched_at)` and `.checksum`; `app_store_listing_changes(app_id, field_changed, detected_at)`; `app_versions.release_date` and `(app_id, release_date)`; `store_categories(platform, parent_id)`; `trending_chart_entries.app_id`; `sync_statuses.job_id`
- `Country` row `zz` (Global) to host Android metrics that are not country-specific
- `SYNC_{IOS,ANDROID}_TRACKED_BATCH_SIZE` (default 5) controls how many apps the scheduler dispatches per 20-minute tick

### Changed
- Renamed columns for naming consistency: `apps.origin_country` → `origin_country_code`, `apps.display_icon` → `icon_url`, `app_store_listings.language` → `locale`, `app_store_listing_changes.language` → `locale`, `trending_charts.country` → `country_code`
- API query parameters / response keys aligned to new names: `?country=` → `?country_code=`, `?language=` → `?locale=`
- `AppMetric::GLOBAL_COUNTRY` from `'GLOBAL'` to ISO 3166 user-assigned `'zz'`
- `locale_added` / `locale_removed` replace `language_added` / `language_removed` in `StoreListingChange.field_changed`

### Fixed
- Chart fetch exceptions now always propagate for retry and failed_jobs tracking
- Fallback to web-scraped iPhone screenshots when iTunes API returns none
- App icon aspect ratio in explorer icons page

### Removed
- Dropped the separate discovery sync command, its dedicated queues, and its env vars — replaced by a tiered fallback inside `appstorecat:apps:sync-tracked` (tracked → competitor → backlog, all sharing the `sync-tracked-{platform}` queue and the tracked staleness window)

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

[Unreleased]: https://github.com/appstorecat/appstorecat/compare/v0.0.4...HEAD
[0.0.4]: https://github.com/appstorecat/appstorecat/compare/v0.0.3...v0.0.4
[0.0.3]: https://github.com/appstorecat/appstorecat/compare/v0.0.2...v0.0.3
[0.0.2]: https://github.com/appstorecat/appstorecat/releases/tag/v0.0.2
