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
