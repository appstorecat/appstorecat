# App Discovery

Discover new apps via search, trending charts, publisher pages, and more.

![App Discovery](../../../screenshots/app-discovery.jpeg)

## Overview

AppStoreCat discovers apps organically through user interactions. Every search, chart view, or publisher page visit can introduce new apps to the system.

## Discovery Sources

| Source | How It's Triggered |
|--------|--------------------|
| **Search** | Searching for apps returns store results and creates records |
| **Trending** | Daily chart sync automatically discovers the most popular apps |
| **Publisher Apps** | Viewing a publisher's app catalog discovers their apps |
| **Registration** | Explicitly registering an app via the API |
| **Import** | Bulk importing all of a publisher's apps |
| **Direct Visit** | Visiting an app by its store ID (disabled by default) |

Each source can be enabled/disabled per platform under the `discover` key in `config/appstorecat.php`. `on_direct_visit` is disabled by default — if you go directly to an app URL that does not exist in the database, the API returns `404`.

## How It Works

1. The user performs an action (search, view charts, visit publisher)
2. The backend calls the appropriate scraper to fetch store data
3. `App::discover()` creates a new app record with a `discovered_from` tag
4. The app is pushed onto the platform-separated discovery queue (`sync-discovery-ios` or `sync-discovery-android`) for background sync
5. The sync is tracked phase-by-phase in the `sync_statuses` table (identity → listings → metrics → finalize → reconciling); if the identity phase fails, the entire pipeline stops and the app is marked "unavailable"

## Search

```
GET /api/v1/apps/search?term=instagram&platform=ios&country_code=US
```

Searches the store in real time via the scraper and returns matching apps. Results are normalized across both platforms. `country_code` is a two-letter ISO code validated against `countries.code`.

## UI

Go to **Discovery > Apps** to search for apps. The UI offers:
- Debounced search input
- Platform picker (iOS / Android)
- Country picker
- Results with app icon, name, publisher, and rating

## Technical Details

- **Controller:** `AppSearchController`
- **Connectors:** `fetchSearch()` on both connectors
- **Discovery queues:** `sync-discovery-ios`, `sync-discovery-android` (platform-separated)
- **Configuration:** `appstorecat.discover.{platform}.on_search` (and the other `on_*` keys; `on_direct_visit` defaults to `false`)
- **404 contract:** Scrapers return 404 when an app is not found in a store; such cases are handled later by `ReconcileFailedItemsJob` via `sync_statuses.failed_items`.
