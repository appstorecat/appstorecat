# Queue System

AppStoreCat uses platform-separated queues to ensure that the iOS and Android pipelines never block each other.

## Queue Architecture

```
                    ┌─ sync-tracked-ios ──────▶ SyncAppJob (iOS tracked/competitor/backlog)
                    ├─ sync-tracked-android ──▶ SyncAppJob (Android tracked/competitor/backlog)
Scheduler ────────▶ ├─ sync-on-demand-ios ────▶ SyncAppJob (UI-triggered stale refresh, iOS)
                    ├─ sync-on-demand-android ▶ SyncAppJob (UI-triggered stale refresh, Android)
                    ├─ charts-ios ────────────▶ SyncChartSnapshotJob (iOS)
                    ├─ charts-android ────────▶ SyncChartSnapshotJob (Android)
                    └─ default ───────────────▶ General jobs + ReconcileFailedItemsJob
```

## Queues

| Queue | Purpose | Job |
|-------|---------|-----|
| `sync-tracked-ios` | Scheduled iOS app syncs (tracked → competitor → backlog) | `SyncAppJob` |
| `sync-tracked-android` | Scheduled Android app syncs (tracked → competitor → backlog) | `SyncAppJob` |
| `sync-on-demand-ios` | UI-triggered refresh for stale iOS apps | `SyncAppJob` |
| `sync-on-demand-android` | UI-triggered refresh for stale Android apps | `SyncAppJob` |
| `charts-ios` | iOS chart snapshots | `SyncChartSnapshotJob` |
| `charts-android` | Android chart snapshots | `SyncChartSnapshotJob` |
| `default` | General-purpose jobs | Various, including `ReconcileFailedItemsJob` |

## Jobs

### SyncAppJob

Runs every pipeline phase for a single app (identity → listings → metrics → finalize) and tracks progress via `sync_statuses`.

- **Queue:** Platform-specific sync queue (`sync-tracked-*` or `sync-on-demand-*`)
- **Unique:** Per app ID, 1-hour window (prevents re-sync)
- **Retries:** 3 attempts with `[30, 60, 120]` second backoff
- **Throttle:** Redis-based, per platform (iOS: 5/min, Android: 5/min)
- **Block timeout:** 300 seconds (waits for a throttle slot)
- **404 handling:** A 404 from the scraper is classified as `empty_response` — the country is marked as permanently "unavailable", never retried

### SyncChartSnapshotJob

Fetches a chart snapshot (e.g. top_free iOS US) and persists the rankings.

- **Queue:** `charts-ios` or `charts-android`
- **Retries:** 2 attempts with `[60, 300]` second backoff
- **Throttle:** Redis-based (iOS: 24/min, Android: 37/min)
- **Side effect:** Discovers new apps from the chart results

### FetchChartSnapshotJob

Same as `SyncChartSnapshotJob` but without the Redis throttle gate. Used for on-demand chart fetches from the UI.

- **Retries:** 3 attempts with `[30, 60, 120]` second backoff

### ReconcileFailedItemsJob

Retries items written to `sync_statuses.failed_items` in a previous run. Honors the configured max attempts per reason tag (permanent reasons like `empty_response` are skipped).

- **Queue:** `default`
- **Scheduling:** Driven by `sync_statuses.next_retry_at`
- **Tries:** 1 (the job manages its own retry book-keeping via `failed_items.retry_count` / `next_retry_at`)
- **Timeout:** `1800` seconds — the loop walks every failed item under throttle and a large `failed_items` JSON can easily exceed the default 60-second worker timeout; without this override the job would be SIGTERM'd mid-loop and immediately re-dispatched
- **Unique:** Per `sync_status_id`, 1800-second window
- **Status book-keeping:** When all items are reconciled the row becomes `completed`; if every remaining item is `permanent_failure` the row becomes `failed`; if some transient items are still scheduled for retry the row stays `completed` with `next_retry_at` set to the earliest pending retry
- **Scope:** Feeds the non-dead items back into the pipeline under the same Redis throttle rules

## Throttling

All scraper-bound jobs use Redis throttle to avoid breaching store rate limits:

```php
// Example: iOS sync throttle
Redis::throttle('sync-job:ios')
    ->allow(5)          // 5 jobs
    ->every(60)         // per minute
    ->block(300)        // wait up to 300 seconds for a slot
    ->then(fn() => ...)
```

### Throttle Keys

| Key | Allow | Per | Platform |
|-----|-------|-----|----------|
| `sync-job:ios` | 5 | 60s | iOS |
| `sync-job:android` | 5 | 60s | Android |
| `chart-job:ios` | 24 | 60s | iOS |
| `chart-job:android` | 37 | 60s | Android |

Rates are configurable via environment variables (see [Configuration](../getting-started/configuration.md)).

## Queue Drivers

| Environment | Driver | Notes |
|-------------|--------|-------|
| Development | `redis` | Fast, in-memory. Redis also handles cache and throttling |
| Production | `database` | Durable. Redis is not used in production |

### `retry_after` vs. per-job `$timeout`

Laravel's queue driver re-dispatches a reserved job to another worker once `retry_after` seconds elapse without an ack. The rule of thumb is **`retry_after` MUST be greater than every job's `$timeout`** — otherwise a long-running job is silently picked up by a second worker while the first is still processing it, doubling scraper calls and corrupting `sync_statuses`.

| Connection | Env var | Default |
|------------|---------|---------|
| `redis` | `REDIS_QUEUE_RETRY_AFTER` | `1810` (s) |
| `database` | `DB_QUEUE_RETRY_AFTER` | `90` (s) |

The Redis default is intentionally larger than `ReconcileFailedItemsJob::$timeout = 1800` plus a 10-second buffer. If you bump any job's `$timeout` above 1800 seconds, raise `retry_after` in lockstep.

## Scheduled Maintenance

In addition to the sync and chart commands documented in [Sync Pipeline](./sync-pipeline.md), the scheduler runs the following maintenance jobs from `routes/console.php`:

| Schedule | Command | Purpose |
|----------|---------|---------|
| `*/15 * * * *` | `appstorecat:sync:reconcile` | Walks `sync_statuses` rows whose `next_retry_at` has elapsed and dispatches a `ReconcileFailedItemsJob` per row |
| Daily `04:00` | `appstorecat:sync:cleanup-failed-items` | Drops `failed_items` JSON blobs from `completed` / `failed` `sync_statuses` rows older than 14 days. Without this, the JSON column slowly bloats with permanent-failure noise that no longer has any retry value |
| Daily `04:30` | `queue:prune-failed --hours=168` | Built-in Laravel command — deletes rows from `failed_jobs` older than 7 days. Keeps the production table bounded |

## Worker Configuration

Workers process jobs on their assigned queues. In production, Laravel Supervisor manages the workers. In development, the built-in scheduler handles job dispatch.

Restart workers after code changes:

```bash
make queue-restart
```

## Adding New Jobs

When creating new scraper-bound jobs:

1. Always use platform-separated queues (`{queue}-ios` and `{queue}-android`)
2. Apply a Redis throttle with the appropriate connector's rate configuration
3. Implement retries with exponential backoff
4. Consider using `ShouldBeUnique` to avoid duplicate processing
5. Write transient failures to `sync_statuses.failed_items` so `ReconcileFailedItemsJob` can reconcile them
