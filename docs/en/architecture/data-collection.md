# Data Collection

AppStoreCat collects app data organically through normal store interactions. There is no bulk scraping or mass crawling — all data flows through natural user actions and background sync cycles.

## How Apps Enter the System

Apps are discovered through user interactions and stored with a `discovered_from` source tag:

| Source | Trigger | Description |
|--------|---------|-------------|
| `on_search` | User searches for apps | Store search results create app records |
| `on_trending` | Daily chart sync | Top apps from trending charts are discovered |
| `on_publisher_apps` | Viewing a publisher's apps | Developer app lists create records |
| `on_register` | User registers an app via API | Direct app registration |
| `on_import` | Publisher bulk import | All apps from a publisher are imported |
| `on_direct_visit` | Viewing an app by ID | Direct URL access discovers the app |

Each source can be enabled/disabled per platform in `config/appstorecat.php`.

## Sync Tiers

Once discovered, apps are synced on two different schedules:

### Tracked Apps (Priority Tier)

Apps explicitly tracked by users. Synced every **24 hours** by default.

- Full data sync: identity, listing, metrics, reviews, versions
- Queue: `sync-tracked-ios` / `sync-tracked-android`
- Controlled by: `SYNC_{PLATFORM}_TRACKED_REFRESH_HOURS`

### Discovery Apps (Background Tier)

Apps discovered but not tracked. Synced every **72 hours** by default.

- Same full sync as tracked, but lower priority
- Queue: `sync-discovery-ios` / `sync-discovery-android`
- Controlled by: `SYNC_{PLATFORM}_DISCOVERY_REFRESH_HOURS`

## What Gets Synced

Each sync cycle (managed by `AppSyncer`) performs these steps in order:

```
1. Identity    → App metadata (name, publisher, category, locales)
2. Version     → Current version number and release date
3. Listing     → Store listing (title, description, screenshots per language)
4. Changes     → Detect differences from previous listing (checksum-based)
5. Metrics     → Rating, rating count, breakdown, file size
6. Reviews     → User reviews (paginated, up to 200 per page)
7. Keywords    → Keyword density analysis from listing text
```

## Throttle Rates

Each platform has independent Redis-based throttle rates to respect store rate limits:

| Platform | Sync Jobs | Chart Jobs |
|----------|-----------|------------|
| iOS (App Store) | 3/minute | 24/minute |
| Android (Google Play) | 2/minute | 37/minute |

Jobs that exceed the throttle block (up to 300s) until a slot opens. This ensures steady, sustainable data collection without triggering rate limits.

## Chart Collection

Trending charts are synced daily for each active country:

- **Collections:** `top_free`, `top_paid`, `top_grossing`
- **Depth:** Up to 200 apps per chart
- **Queues:** `charts-ios` / `charts-android`
- Apps discovered from charts are tagged with `discovered_from: trending`

## Change Detection

When a listing is synced, its content is hashed into a `checksum`. If the checksum differs from the previous sync:

1. Each field (title, subtitle, description, whats_new, screenshots) is compared individually
2. Changed fields create `StoreListingChange` records with old/new values
3. Locale additions and removals are also tracked

This powers the **Changes** tab in the frontend, which shows a timeline of store listing changes across tracked and competitor apps.
