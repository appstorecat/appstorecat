# Quick Start

This guide walks through using AppStoreCat after installation.

## 1. Create an Account

Open http://localhost:7461 and create a new account. This creates a Sanctum API token for authentication.

## 2. Discover Apps

### Via Trending Charts

Go to **Explorer > Trending** to see the top free, top paid, and top grossing apps for both iOS and Android. Apps that come from the charts are automatically discovered and added to the database.

### Via Search

Go to **Explorer > Apps** and search for any app by name. Results come directly from the App Store and Google Play. Click any app to view its details — that action discovers the app automatically.

> Note: `DISCOVER_{IOS,ANDROID}_ON_DIRECT_VISIT=false` by default. That means directly visiting an app by external ID that is not yet in the database returns 404. Only active discovery sources — search, trending charts, publisher pages, and so on — create the record.

### Via Publishers

Go to **Explorer > Publishers** and search for a publisher. View their apps and import them all at once.

## 3. Track an App

While viewing an app's detail page, click the **Track** button. Tracked apps are synced more frequently (every 24 hours by default) and appear in your app list.

## 4. Explore App Data

Once an app has been synced, you can inspect:

- **Store Listing** — title, description, screenshots, and icon per `locale`
- **Versions** — release history with release dates and what's new
- **Keywords** — N-gram-aware keyword density analysis
- **Competitors** — add competitor apps for comparison
- **Changes** — track store listing changes over time

## 5. Background Sync

AppStoreCat syncs data automatically in the background using queue workers. The sync pipeline is split into phases (tracked via the `SyncStatus` table):

1. **identity** — core app identity
2. **listings** — store listings per locale
3. **metrics** — per-country metrics (`app_metrics`)
4. **finalize** — the app's overall status (`apps.is_available`)
5. **reconciling** — failed items are retried via `ReconcileFailedItemsJob`

Default intervals:

- **Tracked apps** are synced every 24 hours (`SYNC_{IOS,ANDROID}_TRACKED_REFRESH_HOURS`)
- **Discovered apps** are synced every 24 hours (`SYNC_{IOS,ANDROID}_DISCOVERY_REFRESH_HOURS`)
- **Charts** are synced daily (`CHART_{IOS,ANDROID}_DAILY_SYNC_ENABLED`)

All scraper jobs are platform-separated: `sync-discovery-{ios,android}`, `sync-tracked-{ios,android}`, `sync-on-demand-{ios,android}`, `charts-{ios,android}`. This way iOS and Android never block each other.

Check `make logs-server` to see sync activity.

## API Access

All features are also available via the REST API at `http://localhost:7460/api/v1/`. See the [API documentation](../api/endpoints.md) for the full endpoint reference.

When `L5_SWAGGER_GENERATE_ALWAYS=true`, the Swagger UI is available at http://localhost:7460/api/documentation.

## Next Steps

- [Configuration](./configuration.md) — customize sync intervals, throttle rates, and discovery sources
- [Architecture Overview](../architecture/overview.md) — understand how the system works
- [Feature Documentation](../features/) — detailed guides for each feature
