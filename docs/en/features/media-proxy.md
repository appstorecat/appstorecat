# Media Proxy

Serve app icons and screenshots through a local proxy for consistent, fast loading.

![Media Proxy](../../../screenshots/media-proxy.jpeg)

## Overview

AppStoreCat can serve app icons and screenshots through a local proxy, or use the raw CDN URLs that come directly from the stores. By default, raw CDN URLs are used for simplicity.

## Explorer Pages

The explorer feature lets you browse screenshots and icons across every app in your database:

### Screenshot Explorer

```
GET /api/v1/explorer/screenshots?platform=ios&category_id=6014&search=game&per_page=12
```

Browse screenshots across all synced apps with platform, category, and search filters.

### Icon Explorer

```
GET /api/v1/explorer/icons?platform=android&search=social&per_page=12
```

Browse app icons across all synced apps.

## UI

To browse visual assets, go to **Explorer > Screenshots** or **Explorer > Icons**:

- **Screenshots** — grid view of app screenshots with platform and category filters
- **Icons** — grid view of app icons with search

Both pages support pagination and search.

## Technical Details

- **Controller:** `ExplorerController`
- **Methods:** `screenshots()`, `icons()`
- **Data source:** The `app_store_listings` table (`screenshots` JSON field, `icon_url` field)
- **URL format:** Raw store CDN URLs (App Store uses `mzstatic.com`, Google Play uses `play-lh.googleusercontent.com`)
