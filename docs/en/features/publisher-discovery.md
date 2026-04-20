# Publisher Discovery

Search publishers, view their app catalogs, and bulk import all of their apps.

![Publisher Discovery](../../../screenshots/publisher-discovery.jpeg)

## Overview

AppStoreCat tracks publishers (developers) and their app catalogs. You can search publishers, view all of their apps, and import entire catalogs at once.

## How It Works

Publishers are created automatically when apps are synced — publisher data comes from the app's identity response. Publishers can also be discovered via search.

## Features

### Publisher Search

Search publishers by name across both stores. Results come directly from the scraper microservices.

### Publisher App Catalog

View every app published by a given publisher. This pulls the full developer app list from the store.

### Bulk Import

Import every app of a publisher into your database with a single action. Each imported app is discovered with an `on_import` source tag and queued for background sync.

## API

### Search Publishers

```
GET /api/v1/publishers/search?term=google&platform=android&country_code=US
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

Returns `404` for publishers that are not in the database.

### Publisher's Store Apps

```
GET /api/v1/publishers/{platform}/{externalId}/store-apps
```

Pulls every app from the store (live scraper call). Returns `404` if the publisher is not in the DB.

### Import All Publisher Apps

```
POST /api/v1/publishers/{platform}/{externalId}/import
```

Each item in `external_ids[*]` is validated individually; an invalid ID rejects the entire request with `422`.

## UI

- **Discovery > Publishers** — search publishers across stores
- **Publishers** (sidebar) — view publishers from your tracked apps
- **Publisher detail page** — publisher info and app catalog with an import button

## Technical Details

- **Model:** `Publisher`
- **Table:** `publishers`
- **Unique constraint:** `(platform, external_id)`
- **Controller:** `PublisherController`
- **Connector methods:** `fetchDeveloperApps()`, `fetchSearch()`
- **Note:** Android publisher `external_id` values may contain URL-encoded characters (spaces, plus signs)
