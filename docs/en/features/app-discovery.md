# App Discovery

Discover new apps through search, trending charts, publisher pages, and more.

![App Discovery](../../screenshots/app-discovery.jpeg)

## Overview

AppStoreCat discovers apps organically through user interactions. Every search, chart view, or publisher page visit can introduce new apps into the system.

## Discovery Sources

| Source | How It Triggers |
|--------|----------------|
| **Search** | Searching for apps returns store results and creates records |
| **Trending** | Daily chart sync discovers top apps automatically |
| **Publisher Apps** | Viewing a publisher's app catalog discovers their apps |
| **Register** | Explicitly registering an app via API |
| **Import** | Bulk importing all apps from a publisher |
| **Direct Visit** | Visiting an app by its store ID |

Each source can be enabled/disabled per platform in `config/appstorecat.php` under the `discover` key.

## How It Works

1. User performs an action (search, view charts, visit publisher)
2. Backend calls the appropriate scraper to fetch store data
3. `App::discover()` creates a new app record with the `discovered_from` tag
4. App is queued for background sync on the discovery queue

## Search

```
GET /api/v1/apps/search?term=instagram&platform=ios&country=us
```

Searches the store in real-time through the scraper and returns matching apps. Results are normalized across both platforms.

## Frontend

Navigate to **Discovery > Apps** to search for apps. The UI provides:
- Search input with debounce
- Platform selector (iOS / Android)
- Country selector
- Results with app icon, name, publisher, and rating

## Technical Details

- **Controller:** `AppSearchController`
- **Connectors:** `fetchSearch()` on both connectors
- **Discovery queue:** `discover`
- **Config:** `appstorecat.discover.{platform}.on_search` (and other `on_*` toggles)
