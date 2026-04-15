# Sync Pipeline

The sync pipeline is the core data collection engine. It's managed by `AppSyncer` and orchestrates the full data lifecycle for each app.

## Pipeline Flow

```
SyncAppJob
    │
    ▼
AppSyncer::syncAll(App)
    │
    ├─ 1. syncIdentity()      → App metadata (name, publisher, category, locales)
    │       └─ findOrCreate Publisher
    │       └─ findOrCreate StoreCategory
    │
    ├─ 2. saveVersion()        → AppVersion record
    │
    ├─ 3. syncListing()        → StoreListing for default locale
    │       └─ detectChanges() → StoreListingChange records (checksum-based)
    │
    ├─ 4. detectLocaleChanges() → Track added/removed locales
    │
    ├─ 5. syncMetrics()        → AppMetric (daily snapshot)
    │       └─ Calculate rating_delta
    │
    ├─ 6. syncReviews()        → Review records (paginated)
    │
    └─ 7. updateVersionDetails() → KeywordAnalyzer on listing text
            └─ AppKeywordDensity records (1/2/3-grams)
```

## Step Details

### 1. Identity Sync

Fetches the app's core metadata from the store.

- Tries `us` country first, falls back to `app.origin_country`
- If 404: marks app as `is_available = false` and stops
- Updates: `display_name`, `display_icon`, `supported_locales`, `original_release_date`, `is_free`
- Creates or links `Publisher` and `StoreCategory` records

### 2. Version Save

Creates or finds the current version record.

- Uses `firstOrCreate` with `(app_id, version)` — no duplicates
- Sets `release_date` from identity data
- Returns the version for use in subsequent steps

### 3. Listing Sync

Fetches the store listing for the default language.

- Stores `StoreListing` record (unique per `app_id` + `language`)
- Generates a `checksum` from listing content
- If checksum differs from previous: compares each field individually
- Creates `StoreListingChange` records for modified fields:
  - `title`, `subtitle`, `description`, `whats_new`, `screenshots`

### 4. Locale Change Detection

Compares the current `supported_locales` with the previous sync.

- Detects newly added locales → creates `StoreListingChange` with `field_changed: locale_added`
- Detects removed locales → creates `StoreListingChange` with `field_changed: locale_removed`

### 5. Metrics Sync

Fetches current ratings and metrics.

- Creates `AppMetric` record (unique per `app_id` + `date`)
- Stores: `rating`, `rating_count`, `rating_breakdown`, `file_size_bytes`
- Calculates `rating_delta` (change in rating_count from previous day)

### 6. Review Sync

Fetches user reviews from the store.

- Paginated: up to 200 reviews per page
- Stores `Review` records (unique per `app_id` + `external_id`)
- Captures: author, title, body, rating, review_date, app_version

### 7. Keyword Analysis

Analyzes the listing text for keyword density.

- Combines: title + subtitle + description + whats_new
- Tokenizes with language-aware stop word filtering (50 languages)
- Extracts n-grams: 1-word, 2-word, and 3-word combinations
- Calculates frequency and density percentage
- Stores `AppKeywordDensity` records

## Sync Scheduling

The Laravel scheduler dispatches sync jobs based on the `last_synced_at` timestamp:

| App Type | Refresh Interval | Queue |
|----------|-----------------|-------|
| Tracked iOS | 24 hours | `sync-tracked-ios` |
| Tracked Android | 24 hours | `sync-tracked-android` |
| Discovered iOS | 72 hours | `sync-discovery-ios` |
| Discovered Android | 72 hours | `sync-discovery-android` |

Apps are only re-synced if `last_synced_at` is older than the configured refresh interval.

## Uniqueness Guards

The pipeline uses database unique constraints to prevent duplicate data:

| Table | Unique On |
|-------|-----------|
| `apps` | `(platform, external_id)` |
| `app_store_listings` | `(app_id, language)` |
| `app_versions` | `(app_id, version)` |
| `app_metrics` | `(app_id, date)` |
| `app_reviews` | `(app_id, external_id)` |

Additionally, `SyncAppJob` implements `ShouldBeUnique` with a 1-hour window per app ID.

## Error Handling

- **404 (App removed):** Marks `is_available = false`, stops sync
- **Network/timeout errors:** Job retries with backoff `[30, 60, 120]` seconds (3 attempts)
- **Failed jobs:** After all retries, jobs go to the `failed_jobs` table for investigation
- **Throttle exceeded:** Job blocks (up to 300s) waiting for a slot
