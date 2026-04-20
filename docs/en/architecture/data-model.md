# Data Model

## Entity Relationship Diagram

```
User ──M:N──▶ App ──1:N──▶ StoreListing
                │             │
                │             └──▶ StoreListingChange
                │
                ├──1:N──▶ AppVersion ──1:N──▶ AppMetric
                │
                ├──N:1──▶ Publisher
                │
                ├──N:1──▶ StoreCategory
                │
                ├──1:1──▶ SyncStatus
                │
                └──1:N──▶ AppCompetitor ──▶ App (competitor)

ChartSnapshot ──1:N──▶ ChartEntry ──▶ App

Country (reference table — internal `zz` "Global" sentinel is filtered out in the API)
```

## Core Tables

### apps

The central entity. Each record represents a unique app on a specific platform.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `platform` | tinyint | Int-backed enum: `1` (iOS) or `2` (Android). Serialized as a slug (`ios` / `android`) in all JSON responses. |
| `external_id` | string | Store ID (e.g. `com.example.app` or `123456789`) |
| `publisher_id` | FK | Link to the publishers table |
| `category_id` | FK | Link to the store_categories table |
| `display_name` | string | Cached app name (from the default locale) |
| `icon_url` | text | Cached icon URL |
| `origin_country_code` | char(2) | Country where the app was first found (FK `countries.code`) |
| `supported_locales` | json | Array of language codes the app supports |
| `original_release_date` | date | Original release date |
| `is_free` | boolean | Free or paid |
| `discovered_from` | tinyint | How the app was discovered (enum: search, trending, publisher, etc.) |
| `discovered_at` | datetime | First discovery time |
| `last_synced_at` | datetime | Time of the last full sync |
| `is_available` | boolean | Whether the app is still reachable in at least one store (per-country availability lives in `app_metrics.is_available`) |

**Uniqueness constraint:** `(platform, external_id)`

### app_store_listings

Per-locale store listing data. One record per app per version per locale.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Link to the apps table |
| `version_id` | FK | Link to the app_versions table (nullable) |
| `locale` | varchar(10) | BCP-47 locale code (e.g. `en-US`, `tr`) |
| `title` | string | App title in this locale |
| `subtitle` | string | App subtitle (iOS only) |
| `promotional_text` | text, nullable | iOS promotional text (`NULL` on Android) |
| `description` | text | Full description |
| `whats_new` | text | Release notes |
| `screenshots` | json | Array of screenshot URLs |
| `icon_url` | string | Icon URL |
| `video_url` | string | Preview video URL |
| `price` | decimal | Price in local currency |
| `currency` | string | Currency code |
| `fetched_at` | datetime | When this listing was fetched |
| `checksum` | string | Hash used for change detection |

**Uniqueness constraint:** `(app_id, version_id, locale)`

> Note: This table does **not** have an `is_available` column. If a locale is not available in a given store, that row is simply not written; per-country availability is kept in `app_metrics.is_available`.

### app_versions

Version history for each app.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Link to the apps table |
| `version` | string | Version string (e.g. `2.1.0`) |
| `release_date` | date | Release date |
| `whats_new` | text | Release notes |
| `file_size_bytes` | bigint | App file size |

**Uniqueness constraint:** `(app_id, version)`

### app_metrics

Daily metric snapshot per country + app. The source of truth for cross-store comparison and per-country availability.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Link to the apps table |
| `version_id` | FK | Link to the app_versions table (nullable) |
| `country_code` | char(2) | FK `countries.code`. For Android, rating is global so the `zz` "Global" sentinel country is used |
| `date` | date | Snapshot date |
| `rating` | decimal(3,2) | Average rating (e.g. 4.56) |
| `rating_count` | uint | Total rating count |
| `rating_breakdown` | json | Per-star counts `{1: 100, 2: 50, ...}` |
| `rating_delta` | int | Change in rating_count since the previous snapshot |
| `price` | decimal, nullable | `NULL` = unknown, `0` = confirmed free |
| `installs_range` | string | Install range (Android only, e.g. `10M+`) |
| `file_size_bytes` | bigint | File size on this date |
| `is_available` | boolean | Whether the app is present on the storefront for this country+date |

**Uniqueness constraint:** `(app_id, country_code, date)`

### app_store_listing_changes

Tracks detected changes in store listings.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Link to the apps table |
| `version_id` | FK | Link to the app_versions table (nullable) |
| `locale` | varchar(10) | BCP-47 locale code |
| `field_changed` | string | `title`, `subtitle`, `promotional_text`, `description`, `whats_new`, `screenshots`, `locale_added`, `locale_removed` |
| `old_value` | text | Previous value |
| `new_value` | text | New value |
| `detected_at` | datetime | When the change was detected |

### sync_statuses

Tracks the state of `AppSyncer` pipeline runs. One record per app.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Link to the apps table (unique) |
| `status` | string | `pending`, `running`, `succeeded`, `failed`, `reconciling` |
| `current_step` | string | Last phase that ran (`identity`, `listings`, `metrics`, `finalize`, `reconciling`) |
| `progress_done` | int | Items completed during the run |
| `progress_total` | int | Items planned |
| `failed_items` | json | Items eligible for retry (with a reason tag) |
| `error_message` | text, nullable | Last error message |
| `job_id` | string, nullable | Laravel queue job UUID associated with the run |
| `next_retry_at` | datetime, nullable | Scheduled time for `ReconcileFailedItemsJob` |

## Supporting Tables

### publishers

| Column | Type | Description |
|--------|------|-------------|
| `name` | string | Publisher/developer name |
| `external_id` | string | Store-specific developer ID |
| `platform` | tinyint | Int-backed enum (`1` iOS / `2` Android), serialized as slug in JSON |
| `url` | string | Publisher store URL |

### store_categories

Seeded from App Store and Google Play category listings.

| Column | Type | Description |
|--------|------|-------------|
| `external_id` | string | Store-specific category ID (nullable — `NULL` marks the "All" sentinel record used for generic/uncategorized charts) |
| `name` | string | Category name |
| `slug` | string | URL-friendly name |
| `platform` | tinyint | Int-backed enum (`1` iOS / `2` Android), serialized as slug in JSON |
| `type` | string | `app`, `game`, or `magazine` |
| `parent_id` | FK | Self-reference for sub-categories |
| `priority` | int | Display ordering |

### countries

Reference table of supported countries with per-platform language configuration.

| Column | Type | Description |
|--------|------|-------------|
| `code` | char(2) | ISO country code (primary key). `zz` is the internal "Global" sentinel — the `/countries` response filters it out |
| `name` | string | Country name |
| `emoji` | string | Flag emoji |
| `is_active_ios` | boolean | Active for iOS operations |
| `is_active_android` | boolean | Active for Android operations |
| `ios_languages` | json | Supported iOS language codes |
| `android_languages` | json | Supported Android language codes |

## Chart Tables

### trending_charts

Daily chart snapshots.

| Column | Type | Description |
|--------|------|-------------|
| `platform` | tinyint | Int-backed enum (`1` iOS / `2` Android), serialized as slug in JSON |
| `collection` | enum | `top_free`, `top_paid`, `top_grossing` |
| `category_id` | FK | Store category (NOT NULL; general charts point at the per-platform "All" sentinel record — iOS id=1, Android id=43) |
| `country_code` | char(2) | FK `countries.code` |
| `snapshot_date` | date | Chart date |

**Uniqueness constraint:** `(platform, collection, country_code, category_id, snapshot_date)`

### trending_chart_entries

Individual app rankings within a chart.

| Column | Type | Description |
|--------|------|-------------|
| `trending_chart_id` | FK | Link to the trending_charts table |
| `rank` | smallint | Rank in the chart (1-200) |
| `app_id` | FK | Link to the apps table |
| `price` | decimal | App price at snapshot time |
| `currency` | string | Currency code |

## Pivot Tables

### user_apps

Many-to-many relationship between users and tracked apps.

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | FK | Link to the users table |
| `app_id` | FK | Link to the apps table |

### app_competitors

User-defined competitor relationships between apps.

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | FK | Link to the users table |
| `app_id` | FK | Primary app |
| `competitor_app_id` | FK | Competitor app |
| `relationship` | string | Relationship type (default: `direct`) |
