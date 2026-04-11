# Publisher Discovery

Search for publishers, view their app catalogs, and bulk import all their apps.

![Publisher Discovery](../../screenshots/publisher-discovery.jpeg)

## Overview

AppStoreCat tracks publishers (developers) and their app catalogs. You can search for publishers, view all their apps, and import entire catalogs at once.

## How It Works

Publishers are created automatically when apps are synced — the publisher data comes from the app's identity response. Publishers can also be discovered through search.

## Features

### Publisher Search

Search for publishers by name across both stores. Results come directly from the scraper microservices.

### Publisher App Catalog

View all apps published by a specific publisher. This fetches the complete developer app list from the store.

### Bulk Import

Import all apps from a publisher into your database with a single action. Each imported app is discovered with the `on_import` source tag and queued for background sync.

## API

### Search Publishers

```
GET /api/v1/publishers/search?term=google&platform=android&country=us
```

### List Publishers

```
GET /api/v1/publishers
```

Returns publishers from your tracked apps.

### Publisher Details

```
GET /api/v1/publishers/{platform}/{externalId}
```

### Publisher's Store Apps

```
GET /api/v1/publishers/{platform}/{externalId}/store-apps
```

Fetches all apps from the store (live scraper call).

### Import All Publisher Apps

```
POST /api/v1/publishers/{platform}/{externalId}/import
```

## Frontend

- **Discovery > Publishers** — Search for publishers across stores
- **Publishers** (sidebar) — View publishers from your tracked apps
- **Publisher detail page** — View publisher info and app catalog with import button

## Technical Details

- **Model:** `Publisher`
- **Table:** `publishers`
- **Unique constraint:** `(platform, external_id)`
- **Controller:** `PublisherController`
- **Connector method:** `fetchDeveloperApps()`, `fetchSearch()`
- **Note:** Android publisher `external_id` can contain URL-encoded characters (spaces, plus signs)
