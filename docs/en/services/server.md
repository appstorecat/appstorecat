# Backend Service

The Laravel API server is AppStoreCat's central service. It acts as the API gateway, owns the database, manages background jobs, and orchestrates all communication with the scraper microservices.

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 13, PHP 8.4 |
| Database | MySQL 8.4 |
| Authentication | Laravel Sanctum (token-based) |
| API documentation | L5-Swagger (OpenAPI) |
| Queue | Redis (development) / Database (production) |
| Cache | Redis (development) / File (production) |
| Code style | Laravel Pint |
| Tests | Pest (PHPUnit) |

## Directory Structure

```
server/
├── app/
│   ├── Connectors/          # Store API integrations
│   │   ├── ConnectorInterface.php
│   │   ├── ConnectorResult.php
│   │   ├── ITunesLookupConnector.php
│   │   └── GooglePlayConnector.php
│   ├── Enums/               # Platform, SyncPhase, etc.
│   ├── Http/
│   │   └── Controllers/Api/V1/
│   │       ├── Account/     # Auth, Profile, Security
│   │       └── App/         # App, Search, Competitor, Keyword
│   ├── Jobs/
│   │   ├── Chart/           # Chart sync jobs
│   │   └── Sync/            # App sync jobs + reconciliation
│   ├── Models/              # Eloquent models (including SyncStatus)
│   ├── Rules/               # Form validation rules such as AppAvailableCountry
│   └── Services/            # Business logic
│       ├── AppRegistrar.php
│       ├── AppSyncer.php
│       └── KeywordAnalyzer.php
├── config/
│   └── appstorecat.php      # Central configuration
├── database/
│   └── migrations/          # All table definitions (including sync_statuses)
├── resources/
│   └── data/stopwords/      # Stop-word dictionaries in 50 languages
├── routes/
│   └── api.php              # All API routes
└── tests/                   # Pest tests
```

## Key Responsibilities

### API Gateway
All web requests go through the server. The backend authenticates users (Sanctum), validates requests (Form Requests), and returns formatted responses (API Resources).

Notable route behaviors:

- `POST /apps` — requires the app to already exist in the DB, otherwise returns 422 (prevents registration of random IDs).
- `POST /publishers/{p}/{id}/import` — validates that each ID in `external_ids[*]` exists.
- `GET /publishers/{p}/{id}` and `/store-apps` — return 404 for unknown records.
- `/apps/{p}/{id}/listing` — accepts `country_code` + `locale`; the `AppAvailableCountry` rule returns 422 if the app is not available in that country.
- `/charts`, `/apps/search`, `/publishers/search` — take a `country_code` parameter.
- `/countries` — filters the internal `zz` sentinel out of the list.
- `GET /apps/{p}/{id}/sync-status` and `POST /apps/{p}/{id}/sync` — sync status and UI-triggered refresh.
- `DirectVisit` is disabled by default.

### Database Owner

The backend is the sole owner of the MySQL database. No other service accesses the database directly.

Schema notes:

- `apps.origin_country_code` is `char(2)` and FKs to `countries.code`.
- The app icon is kept in the `apps.icon_url` column.
- `app_metrics.country_code` is `char(2)` and FKs to `countries.code`; `price` is nullable; `is_available` is the authoritative source per country.
- The `app_store_listings` table uses a `locale` column; iOS listings include a `promotional_text` column.
- `trending_charts.country_code` holds per-country charts.
- The `sync_statuses` table tracks pipeline state.

`apps.is_available` means "reachable in at least one storefront"; the authoritative per-country value is `app_metrics.is_available`.

### Sync Pipeline

Sync is a phased pipeline tracked via the `SyncStatus` model:

1. **identity** — identity is fetched; if this phase fails, the pipeline is aborted.
2. **listings** — store listings per country/locale.
3. **metrics** — per-country metrics (stored globally under the `zz` sentinel for Android).
4. **finalize** — diffs are applied, `apps.is_available` and `unavailable_countries` are recomputed.
5. **reconciling** — `ReconcileFailedItemsJob` re-runs over `sync_statuses.failed_items` (`$timeout = 1800` seconds).

A **404** from the scraper is interpreted as "permanently not available on this storefront" and the corresponding `app_metrics.is_available` is set to `false`; 5xx are retried.

Connectors sanitize unparseable date strings at the Laravel boundary. `ITunesLookupConnector` and `GooglePlayConnector` route `current_version_release_date` and `original_release_date` through a `parseDate()` helper that returns `null` for non-date values such as Google Play's "Never updated" sentinel. The scrapers themselves remain stateless and return raw values; normalization happens server-side.

### Job Orchestration

The Laravel scheduler dispatches sync and chart jobs. All sync/chart queues are **platform-separated** so that iOS and Android never block each other:

| Queue |
|-------|
| `sync-tracked-ios`, `sync-tracked-android` |
| `sync-on-demand-ios`, `sync-on-demand-android` |
| `charts-ios`, `charts-android` |

Scheduled commands (see `routes/console.php`):

| Schedule | Command | Purpose |
|----------|---------|---------|
| `*/20 * * * *` | `appstorecat:apps:sync-tracked --ios` / `--android` | Tracked app sync ticks |
| `*/15 * * * *` | `appstorecat:sync:reconcile` | Reconcile failed sync items |
| `daily 00:30` | `appstorecat:charts:sync-daily --ios` / `--android` | Trending chart snapshots |
| `daily 04:00` | `appstorecat:sync:cleanup-failed-items` | Purge stale entries from `sync_statuses.failed_items` |
| `daily 04:30` | `queue:prune-failed --hours=168` | Drop `failed_jobs` rows older than 7 days |

Queue `retry_after` is set to **1810 seconds** by default (`REDIS_QUEUE_RETRY_AFTER`). It must always be greater than every job's `$timeout` — for example `ReconcileFailedItemsJob` runs with `$timeout = 1800`. If `retry_after` ever drops below a job's timeout, Redis will re-dispatch the same job while it is still running.

### Connector Layer

Connectors abstract HTTP communication with the scraper microservices and normalize response formats across platforms. A 404 from the scraper is modeled as permanently "not available"; other errors are modeled as retryable.

## Running

```bash
make dev-server    # Start backend + MySQL + Redis
make logs-server   # View backend logs
make pint          # Run the code style fixer
make test          # Run the Pest tests
```

## Tests

The backend uses **Pest 4** on top of PHPUnit, with `RefreshDatabase` against a dedicated `appstorecat_testing` MySQL database. The current suite covers 47 test files with around 400 tests.

```bash
make test                                    # Run the full suite
make test EXTRA_ARGS="--filter=Foo"          # Run a focused subset
make test EXTRA_ARGS="--parallel"            # Optional parallel mode
```

Dev dependencies pulled by Composer: `pestphp/pest`, `pestphp/pest-plugin-laravel`, `fakerphp/faker`.

## MySQL Configuration

MySQL 8.4 enables binary logging by default for point-in-time recovery (PITR). The stock `binlog_expire_logs_seconds = 2592000` (30 days) accumulates roughly 1 GB/day of binlog data on a healthy AppStoreCat deployment — about 30 GB of additional disk over a month.

**Recommended retention:** 7 days (`604800`) when there is no replica reading the binlog. This gives a sensible PITR window without paying the 30 GB disk cost.

Apply at runtime:

```sql
SET PERSIST binlog_expire_logs_seconds = 604800;
```

Or in `my.cnf`:

```ini
[mysqld]
binlog_expire_logs_seconds = 604800
```

If you operate replicas, keep the retention high enough that every replica can catch up after the longest expected outage.

## API Documentation

When `L5_SWAGGER_GENERATE_ALWAYS=true`, the Swagger UI is available at `/api/documentation`.

For the full reference, see [API Endpoints](../api/endpoints.md).
