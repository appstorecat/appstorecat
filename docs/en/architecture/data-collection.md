# Data Collection

AppStoreCat gathers app data organically through normal store interactions. There is no bulk scraping or serial crawling — all data flows through natural user actions and background sync cycles.

## How Apps Enter the System

Apps are discovered through user interactions and stored with a `discovered_from` source tag:

| Source | Trigger | Description |
|--------|---------|-------------|
| `on_search` | User searches for an app | Store search results create app records |
| `on_trending` | Daily chart sync | Top apps in trending lists are discovered |
| `on_publisher_apps` | Viewing a publisher's apps | Developer app lists create records |
| `on_register` | User registers an app via API | Direct app registration — only accepted for previously discovered apps |
| `on_import` | Publisher bulk import | All of a publisher's apps are imported (each `external_id` must already exist in the DB) |
| `on_direct_visit` | Viewing an app by ID | **Disabled by default** (`DISCOVER_{IOS,ANDROID}_ON_DIRECT_VISIT=false`). Direct URL visits for unknown apps return 404 — discovery via search/chart is required first |

Each source can be enabled/disabled per platform in `config/appstorecat.php`.

## Sync Tiers

Once discovered, apps are synced on two different schedules:

### Tracked Apps (Priority Tier)

Apps explicitly tracked by users. Synced every **24 hours** by default.

- Full data sync: identity, listings, metrics, versions
- Queue: `sync-tracked-ios` / `sync-tracked-android`
- Control: `SYNC_{PLATFORM}_TRACKED_REFRESH_HOURS`

### Discovery Apps (Background Tier)

Apps that have been discovered but are not tracked. Synced every **24 hours** by default.

- Same full sync as tracked apps, but at a lower priority
- Queue: `sync-discovery-ios` / `sync-discovery-android`
- Control: `SYNC_{PLATFORM}_DISCOVERY_REFRESH_HOURS`

### On-demand Refresh from the UI

When a user opens an app detail or listing page whose `last_synced_at` is older than the refresh threshold, the API dispatches a `SyncAppJob` onto the platform's `sync-on-demand-ios` / `sync-on-demand-android` queue. This dedicated lane keeps user-triggered refreshes from waiting behind the cron-driven discovery backlog. The UI polls progress via `GET /apps/{platform}/{externalId}/sync-status`, and the user can also trigger an explicit refresh with `POST /apps/{platform}/{externalId}/sync`.

## What Gets Synced

The `AppSyncer` pipeline runs in the following phases and writes the status of each step to the `sync_statuses` table:

```
1. identity    → App metadata (name, publisher, category, languages)
                 The pipeline stops if this fails.
2. listings    → Store listings for every active country + locale
3. metrics     → Per-country rating / rating_count / is_available
                 (Android metrics are aggregated under the `zz` Global sentinel country)
4. finalize    → `apps.last_synced_at`, change detection, checksums
5. reconciling → `ReconcileFailedItemsJob` retries transient failures
```

Keyword density is **not** a sync step; it is computed on demand against the stored listing when the API is called. No separate persistence or reindexing is needed.

## Throttle Rates

Each platform has independent Redis-based throttle rates to avoid breaching store rate limits:

| Platform | Sync Jobs | Chart Jobs |
|----------|-----------|------------|
| iOS (App Store) | 5/minute | 24/minute |
| Android (Google Play) | 5/minute | 37/minute |

Jobs exceeding the throttle wait for a slot to open (up to 300 seconds). This enables steady, sustainable data collection without triggering rate limits.

The Laravel scheduler dispatches the `sync-discovery` and `sync-tracked` commands on each platform every **20 minutes** — at 5 apps per minute, this lines up ~100 apps per cycle and keeps the sync pool in sync with the schedule.

## Chart Collection

Trending charts are synced daily for every active country:

- **Collections:** `top_free`, `top_paid`, `top_grossing`
- **Depth:** Up to 200 apps per chart
- **Queues:** `charts-ios` / `charts-android`
- Apps discovered from charts are tagged `discovered_from: trending`

## Change Detection

When a listing is synced, its contents are hashed into a `checksum`. If the checksum differs from the previous sync:

1. Each field (title, subtitle, promotional_text, description, whats_new, screenshots) is compared one by one
2. Changed fields produce `StoreListingChange` records with old/new values
3. Locale additions and removals are also tracked as `locale_added` / `locale_removed`

This feature powers the **Changes** tab on the web; it shows a timeline of store listing changes across tracked and competitor apps.

## Failed Item Reconciliation

If the sync fails for an item (e.g. transient 5xx), it is stored in `sync_statuses.failed_items` with a reason tag. `ReconcileFailedItemsJob` retries these items up to a configured maximum attempt count per reason. 404 responses from the scraper are classified as `empty_response` and treated as permanent "not available on this storefront" — they are not retried.
