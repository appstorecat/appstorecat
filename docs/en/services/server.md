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
5. **reconciling** — `ReconcileFailedItemsJob` re-runs over `sync_statuses.failed_items`.

A **404** from the scraper is interpreted as "permanently not available on this storefront" and the corresponding `app_metrics.is_available` is set to `false`; 5xx are retried.

### Job Orchestration

The Laravel scheduler dispatches sync and chart jobs. All sync/chart queues are **platform-separated** so that iOS and Android never block each other:

| Queue |
|-------|
| `sync-discovery-ios`, `sync-discovery-android` |
| `sync-tracked-ios`, `sync-tracked-android` |
| `sync-on-demand-ios`, `sync-on-demand-android` |
| `charts-ios`, `charts-android` |

### Connector Layer

Connectors abstract HTTP communication with the scraper microservices and normalize response formats across platforms. A 404 from the scraper is modeled as permanently "not available"; other errors are modeled as retryable.

## Running

```bash
make dev-server    # Start backend + MySQL + Redis
make logs-server   # View backend logs
make pint          # Run the code style fixer
make test-server   # Run the Pest tests
```

## API Documentation

When `L5_SWAGGER_GENERATE_ALWAYS=true`, the Swagger UI is available at `/api/documentation`.

For the full reference, see [API Endpoints](../api/endpoints.md).
