# Data Model

## Entity Relationship Diagram

```
User ──M:N──▶ App ──1:N──▶ StoreListing
                │             │
                │             └──▶ StoreListingChange
                │
                ├──1:N──▶ AppVersion ──1:N──▶ AppMetric
                │
                ├──1:N──▶ Review
                │
                ├──N:1──▶ Publisher
                │
                ├──N:1──▶ StoreCategory
                │
                └──1:N──▶ AppCompetitor ──▶ App (competitor)

ChartSnapshot ──1:N──▶ ChartEntry ──▶ App

Country (reference table)
```

## Core Tables

### apps

The central entity. Each record represents a unique app on a specific platform.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `platform` | tinyint | Int-backed enum: `1` (iOS) or `2` (Android). Serialized as slug (`ios` / `android`) in all JSON responses. |
| `external_id` | string | Store ID (e.g., `com.example.app` or `123456789`) |
| `publisher_id` | FK | Links to publishers table |
| `category_id` | FK | Links to store_categories table |
| `display_name` | string | Cached app name (from default locale) |
| `display_icon` | string | Cached icon URL |
| `origin_country` | string | Country where app was first found |
| `supported_locales` | json | Array of locale codes the app supports |
| `original_release_date` | date | First release date |
| `is_free` | boolean | Free or paid |
| `discovered_from` | tinyint | How the app was discovered (enum: search, trending, publisher, etc.) |
| `discovered_at` | datetime | When first discovered |
| `last_synced_at` | datetime | Last full sync timestamp |
| `is_available` | boolean | Whether the app is still on the store |

**Unique constraint:** `(platform, external_id)`

### app_store_listings

Store listing data per language. One record per app per language.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Links to apps |
| `version_id` | FK | Links to app_versions (nullable) |
| `language` | string | Locale code (e.g., `en-US`, `tr`) |
| `title` | string | App title in this language |
| `subtitle` | string | App subtitle (iOS only) |
| `description` | text | Full description |
| `whats_new` | text | Release notes |
| `screenshots` | json | Array of screenshot URLs |
| `icon_url` | string | Icon URL |
| `video_url` | string | Preview video URL |
| `price` | decimal | Price in local currency |
| `currency` | string | Currency code |
| `fetched_at` | datetime | When this listing was fetched |
| `checksum` | string | Hash for change detection |

**Unique constraint:** `(app_id, language)`

### app_versions

Version history for each app.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Links to apps |
| `version` | string | Version string (e.g., `2.1.0`) |
| `release_date` | date | Release date |
| `whats_new` | text | Release notes |
| `file_size_bytes` | bigint | App file size |

**Unique constraint:** `(app_id, version)`

### app_metrics

Daily metrics snapshot per app.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Links to apps |
| `version_id` | FK | Links to app_versions (nullable) |
| `date` | date | Snapshot date |
| `rating` | decimal(3,2) | Average rating (e.g., 4.56) |
| `rating_count` | uint | Total number of ratings |
| `rating_breakdown` | json | Per-star counts `{1: 100, 2: 50, ...}` |
| `rating_delta` | int | Change in rating_count since last snapshot |
| `installs_range` | string | Install range (Android only, e.g., `10M+`) |
| `file_size_bytes` | bigint | File size at this date |

**Unique constraint:** `(app_id, date)`

### app_reviews

User reviews synced from stores.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Links to apps |
| `country_code` | FK | Links to countries (nullable for Android) |
| `external_id` | string | Store-specific review ID |
| `author` | string | Reviewer name |
| `title` | string | Review title (iOS only) |
| `body` | text | Review text |
| `rating` | tinyint | 1-5 star rating |
| `review_date` | date | When the review was posted |
| `app_version` | string | App version at time of review |

**Unique constraint:** `(app_id, external_id)`

### app_store_listing_changes

Tracks changes detected in store listings.

| Column | Type | Description |
|--------|------|-------------|
| `app_id` | FK | Links to apps |
| `version_id` | FK | Links to app_versions (nullable) |
| `language` | string | Locale code |
| `field_changed` | string | `title`, `subtitle`, `description`, `whats_new`, `screenshots`, `locale_removed` |
| `old_value` | text | Previous value |
| `new_value` | text | New value |
| `detected_at` | datetime | When the change was detected |

## Supporting Tables

### publishers

| Column | Type | Description |
|--------|------|-------------|
| `name` | string | Publisher/developer name |
| `external_id` | string | Store-specific developer ID |
| `platform` | tinyint | Int-backed enum (`1` iOS / `2` Android), serialized as slug in JSON |
| `url` | string | Publisher store URL |

### store_categories

Seeded from App Store and Google Play category lists.

| Column | Type | Description |
|--------|------|-------------|
| `external_id` | string | Store-specific category ID (nullable — `NULL` marks the "All" sentinel row used for overall/uncategorised charts) |
| `name` | string | Category name |
| `slug` | string | URL-friendly name |
| `platform` | tinyint | Int-backed enum (`1` iOS / `2` Android), serialized as slug in JSON |
| `type` | string | `app`, `game`, or `magazine` |
| `parent_id` | FK | Self-referencing for subcategories |
| `priority` | int | Display order |

### countries

Reference table for supported countries with per-platform locale configuration.

| Column | Type | Description |
|--------|------|-------------|
| `code` | string(2) | ISO country code (primary key) |
| `name` | string | Country name |
| `emoji` | string | Flag emoji |
| `is_active_ios` | boolean | Active for iOS operations |
| `is_active_android` | boolean | Active for Android operations |
| `ios_languages` | json | Supported iOS locale codes |
| `android_languages` | json | Supported Android locale codes |

## Chart Tables

### trending_charts

Daily chart snapshots.

| Column | Type | Description |
|--------|------|-------------|
| `platform` | tinyint | Int-backed enum (`1` iOS / `2` Android), serialized as slug in JSON |
| `collection` | enum | `top_free`, `top_paid`, `top_grossing` |
| `category_id` | FK | Store category (NOT NULL; overall charts point at the platform's "All" sentinel row — iOS id=1, Android id=43) |
| `country` | FK | Country code |
| `snapshot_date` | date | Chart date |

**Unique constraint:** `(platform, collection, country, category_id, snapshot_date)`

### trending_chart_entries

Individual app rankings within a chart.

| Column | Type | Description |
|--------|------|-------------|
| `trending_chart_id` | FK | Links to trending_charts |
| `rank` | smallint | Position in chart (1-200) |
| `app_id` | FK | Links to apps |
| `price` | decimal | App price at time of snapshot |
| `currency` | string | Currency code |

## Pivot Tables

### user_apps

Many-to-many relationship between users and tracked apps.

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | FK | Links to users |
| `app_id` | FK | Links to apps |

### app_competitors

User-defined competitor relationships between apps.

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | FK | Links to users |
| `app_id` | FK | The base app |
| `competitor_app_id` | FK | The competitor app |
| `relationship` | string | Relationship type (default: `direct`) |
