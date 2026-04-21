# Sync Pipeline

The sync pipeline is the core data collection engine. It is driven by `AppSyncer` and orchestrates the full data lifecycle for each app. The state of pipeline runs is persisted in the `sync_statuses` table (status, current_step, progress_done/total, failed_items, error_message, job_id, next_retry_at).

## Pipeline Flow

```
SyncAppJob
    │
    ▼
AppSyncer::syncAll(App)
    │
    ├─ 1. identity()         → App metadata (name, publisher, category, languages)
    │       ├─ findOrCreate Publisher
    │       ├─ findOrCreate StoreCategory
    │       └─ Pipeline stops on failure
    │
    ├─ 2. listings()         → StoreListing + StoreListingChange per active country + locale
    │       └─ detectChanges() compares checksum
    │
    ├─ 3. metrics()          → AppMetric (per country + day; Android under the `zz` sentinel)
    │       └─ Computes rating_delta, sets `is_available`
    │
    ├─ 4. finalize()         → `apps.last_synced_at`, summary fields, `unavailable_countries`
    │
    └─ 5. reconciling()      → ReconcileFailedItemsJob retries transient failures
```

Keyword density is **not** a pipeline step — `KeywordAnalyzer` is called on demand from the keyword endpoint and reads from the existing `StoreListing`. See the [Keyword Density](../features/keyword-density.md) feature page.

## Phase Details

### 1. Identity

Fetches the app's core metadata from the store.

- Tries the `us` storefront first and falls back to `apps.origin_country_code` on failure
- On 404: classified as `empty_response`; if no storefront can resolve identity, the pipeline stops this run (so later phases don't run against an unresolved app)
- Updates: `display_name`, `icon_url`, `supported_locales`, `original_release_date`, `is_free`, `origin_country_code`
- Creates or links `Publisher` and `StoreCategory` records

### 2. Listings

For each active country, fetches the store listing in every locale the country supports.

- Creates a `StoreListing` record (unique `(app_id, version_id, locale)`)
- Writes `title`, `subtitle`, `promotional_text` (iOS-only), `description`, `whats_new`, `screenshots`, `icon_url`
- Produces a `checksum` over the listing contents
- If the checksum differs from the previous one, compares each field and creates `StoreListingChange` records
- Added/removed locales from the `supported_locales` comparison are marked as `locale_added` / `locale_removed`
- No record is written if the storefront does not return the locale

### 3. Metrics

Fetches per-country ratings and price.

- Creates an `AppMetric` record (unique `(app_id, country_code, date)`)
- Since Android metrics are global, they are stored under the `zz` sentinel country
- Persists: `rating`, `rating_count`, `rating_breakdown`, `price` (null = unknown, 0 = free), `installs_range`, `file_size_bytes`, `is_available`
- Computes `rating_delta` (change in rating_count since the previous day)
- If a 404 comes back for a country → marked as `empty_response`, `is_available = false` is written for that country, and it will not be retried

### 4. Finalize

- Update `apps.last_synced_at`
- Refresh summary fields and caches
- The `AppDetailResource`'s `unavailable_countries` field is derived from `app_metrics.is_available = false` rows
- `apps.is_available` reflects reachability in at least one storefront; the source of truth for per-country availability is `app_metrics`

### 5. Reconciling

- Examines the `failed_items` entries previous phases wrote for this run
- `ReconcileFailedItemsJob` queues them at `next_retry_at`, honoring the configured max retry count per reason tag
- Permanent reasons like `empty_response` are skipped — no infinite retries

## Sync Scheduling

The Laravel scheduler fires `appstorecat:apps:sync-tracked` on both platforms every **20 minutes**; it pulls stale apps and dispatches a `SyncAppJob` to `sync-tracked-{platform}` for each one. Each tick is capped at `SYNC_{PLATFORM}_TRACKED_BATCH_SIZE` apps (default 5).

The command picks apps in tiered priority order so idle ticks still do useful work:

1. Tracked apps (via `user_apps`)
2. Competitor apps (`app_competitors.competitor_app_id`) that are not themselves tracked
3. Any other available app, oldest first

Within each tier, apps that have never been synced are picked before apps with a stale `last_synced_at`.

| App Type | Refresh Interval | Queue |
|----------|------------------|-------|
| Tracked / competitor / backlog iOS | 24 hours | `sync-tracked-ios` |
| Tracked / competitor / backlog Android | 24 hours | `sync-tracked-android` |

Apps are only re-synced if their `last_synced_at` is older than the configured refresh interval.

### On-demand Refresh Queue

`AppController::show()` and `AppController::listing()` dispatch a `SyncAppJob` to `sync-on-demand-ios` / `sync-on-demand-android` when the visited app's data is stale. The UI polls progress via `GET /apps/{platform}/{externalId}/sync-status`; the user can also trigger an explicit refresh via `POST /apps/{platform}/{externalId}/sync`. This keeps user-triggered refreshes on their own worker pool and prevents them from waiting behind the scheduled tracked queue.

## Uniqueness Safeguards

The pipeline uses database uniqueness constraints to prevent duplicate data:

| Table | Uniqueness Criteria |
|-------|---------------------|
| `apps` | `(platform, external_id)` |
| `app_store_listings` | `(app_id, version_id, locale)` |
| `app_versions` | `(app_id, version)` |
| `app_metrics` | `(app_id, country_code, date)` |
| `sync_statuses` | `app_id` |

In addition, `SyncAppJob` enforces `ShouldBeUnique` with a 1-hour window per app ID.

## Error Handling

- **Identity failure:** Pipeline stops, `sync_statuses.status = failed` is written. Later phases do not run.
- **404 `empty_response`:** The country/locale is marked as permanently "unavailable"; not retried.
- **Transient failures (5xx, timeout):** Written to `failed_items` with a reason tag; `ReconcileFailedItemsJob` retries based on the max-attempts rule per reason.
- **Job-level retry:** 3 attempts with `[30, 60, 120]` second backoff.
- **Failed jobs:** After all attempts, jobs land in the `failed_jobs` table for inspection.
- **Throttle exceeded:** The job waits for a slot (up to 300 seconds).
