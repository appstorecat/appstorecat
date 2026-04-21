# Jobs & Queue

## Directory Structure

Jobs are organized by domain under `app/Jobs/`:

```
app/Jobs/
├── Sync/
│   └── SyncAppJob.php             # App data sync (identity + listing + metrics)
├── Chart/
│   └── FetchChartSnapshotJob.php  # Trending chart snapshot
└── ...future domains.../
```

## Rules

- **One folder per domain** — group related jobs together (`Sync/`, `Chart/`, etc.)
- Use `ShouldQueue` interface on all jobs
- Inject services via `handle()` method parameters (Laravel auto-resolves)
- Use `backoff` array for progressive retry delays: `[30, 60, 120]`
- Set `$tries = 3` for retryable jobs
- Always clean up state in `finally` block (e.g., clear `sync_queued_at`)

## Artisan Command Naming

All project commands use the `appstorecat:` prefix with domain grouping:

```
appstorecat:{domain}:{action}
```

Examples:
- `appstorecat:apps:sync-tracked` — Scheduled sync batch (tracked → competitor → backlog)

Commands live in `app/Console/Commands/{Domain}/`.

## Queue Architecture

All scraper-bound queues are platform-separated so iOS and Android rate limits never block each other:

| Queue | Responsibility | Selection Criteria |
|-------|---------------|-------------------|
| `sync-tracked-ios` / `sync-tracked-android` | Scheduled batch: tracked → competitor → backlog | See `SyncTrackedCommand::findPendingApps()` (tiered selection) |
| `sync-on-demand-ios` / `sync-on-demand-android` | UI-triggered refresh for stale apps | Dispatched from `AppController::show()` / `::listing()` |

Both pools use the same `SyncAppJob` and `AppSyncer` service. Tracked apps get the first slots on every tick; on-demand queues exist so user page views are never stuck behind the scheduled batch.

### Other Queues

| Queue | Purpose |
|-------|---------|
| `default` | General jobs |
| `charts-ios` / `charts-android` | Trending chart fetching |

## Sync Pipeline

`SyncAppJob` runs `AppSyncer::syncAll()`:

1. `syncIdentity()` — publisher, category, locales, version
2. `saveVersion()` — create/find AppVersion record
3. `syncListing()` — store listing + change detection
4. `syncMetrics()` — rating, installs, rating breakdown
5. `syncReviews()` — user reviews
6. `updateVersionDetails()` — copy whats_new, file_size to version
7. Set `last_synced_at = now()`

Keyword density is **not** part of the sync pipeline — `KeywordAnalyzer` is invoked on demand from the keyword endpoints and reads directly from the current `StoreListing`.

### Duplicate Protection

`SyncAppJob::dispatchIfNotQueued()` checks `sync_queued_at` — skips if queued within last 10 minutes.

### Scheduling

The sync-tracked command runs every 20 minutes via Laravel Scheduler, per platform:

```php
Schedule::command('appstorecat:apps:sync-tracked --ios')->cron('*/20 * * * *');
Schedule::command('appstorecat:apps:sync-tracked --android')->cron('*/20 * * * *');
```

Each tick is capped at `SYNC_{PLATFORM}_TRACKED_BATCH_SIZE` apps (default 5). Inside the command, tiers are filled in order (tracked → competitor → backlog) until the batch is full.

Each run fans out up to 100 stale apps to the matching queue. At the default 5 syncs/min per platform throttle, the 20-minute cadence is sized to drain one batch before the next one fires.

### On-Demand Sync (UI)

When a user visits an app detail page (`AppController::show()`) or listing page (`::listing()`) whose `last_synced_at` is stale (older than the tracked / discovery threshold), the controller dispatches a `SyncAppJob` to `sync-on-demand-{platform}` instead of running inline. This keeps the HTTP response fast while ensuring user-triggered refreshes don't queue behind the cron backlog.
