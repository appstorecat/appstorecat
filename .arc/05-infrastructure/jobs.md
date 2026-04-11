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
- `appstorecat:apps:sync-discovery` — Sync next stale untracked app
- `appstorecat:apps:sync-tracked` — Sync next stale tracked app

Commands live in `app/Console/Commands/{Domain}/`.

## Queue Architecture

Two independent sync pools run in parallel:

| Queue | Responsibility | Selection Criteria |
|-------|---------------|-------------------|
| `sync-discovery` | Untracked apps (discovered via search, trending, etc.) | `doesntHave('users')`, oldest `last_synced_at` |
| `sync-tracked` | Tracked apps (user is actively following) | `whereHas('users')`, oldest `last_synced_at` |

Both pools use the same `SyncAppJob` and `AppSyncer` service. A tracked app gets synced more frequently because it has its own dedicated pool.

### Other Queues

| Queue | Purpose |
|-------|---------|
| `default` | General jobs |
| `discover` | App discovery |
| `charts` | Trending chart fetching |

## Sync Pipeline

`SyncAppJob` runs `AppSyncer::syncAll()`:

1. `syncIdentity()` — publisher, category, locales, version
2. `saveVersion()` — create/find AppVersion record
3. `syncListing()` — store listing + change detection + keyword analysis
4. `syncMetrics()` — rating, installs, rating breakdown
5. `updateVersionDetails()` — copy whats_new, file_size to version
6. Set `last_synced_at = now()`

### Duplicate Protection

`SyncAppJob::dispatchIfNotQueued()` checks `sync_queued_at` — skips if queued within last 10 minutes.

### Scheduling

Both commands run every minute via Laravel Scheduler:

```php
Schedule::command('appstorecat:apps:sync-discovery')->everyMinute();
Schedule::command('appstorecat:apps:sync-tracked')->everyMinute();
```

Each picks one app per run → ~2880 total syncs/day (1440 per pool).

### On-Demand Sync (UI)

When a user visits an app detail page for the first time (`last_synced_at` is null), `AppController::show()` calls `AppSyncer::syncAll()` synchronously so the user sees data immediately.
