# Queue System

AppStoreCat uses platform-separated queues to ensure iOS and Android pipelines never block each other.

## Queue Architecture

```
                    ┌─ sync-tracked-ios ──────▶ SyncAppJob (iOS tracked)
                    ├─ sync-tracked-android ──▶ SyncAppJob (Android tracked)
                    ├─ sync-discovery-ios ────▶ SyncAppJob (iOS discovered)
Scheduler ─────────▶├─ sync-discovery-android ▶ SyncAppJob (Android discovered)
                    ├─ charts-ios ────────────▶ SyncChartSnapshotJob (iOS)
                    ├─ charts-android ────────▶ SyncChartSnapshotJob (Android)
                    ├─ discover ──────────────▶ Discovery jobs
                    └─ default ───────────────▶ General jobs
```

## Queues

| Queue | Purpose | Job |
|-------|---------|-----|
| `sync-tracked-ios` | Sync tracked iOS apps | `SyncAppJob` |
| `sync-tracked-android` | Sync tracked Android apps | `SyncAppJob` |
| `sync-discovery-ios` | Sync discovered iOS apps | `SyncAppJob` |
| `sync-discovery-android` | Sync discovered Android apps | `SyncAppJob` |
| `charts-ios` | iOS chart snapshots | `SyncChartSnapshotJob` |
| `charts-android` | Android chart snapshots | `SyncChartSnapshotJob` |
| `discover` | App discovery | Various |
| `default` | General-purpose jobs | Various |

## Jobs

### SyncAppJob

Syncs a single app's full data (identity, listing, metrics, reviews, keywords).

- **Queue:** Platform-specific sync queue
- **Unique:** Per app ID, 1 hour window (prevents duplicate syncs)
- **Retries:** 3 attempts with backoff `[30, 60, 120]` seconds
- **Throttle:** Redis-based, per platform (iOS: 3/min, Android: 2/min)
- **Block timeout:** 300 seconds (waits for throttle slot)

### SyncChartSnapshotJob

Fetches a chart snapshot (e.g., top_free iOS US) and saves rankings.

- **Queue:** `charts-ios` or `charts-android`
- **Retries:** 2 attempts with backoff `[60, 300]` seconds
- **Throttle:** Redis-based (iOS: 24/min, Android: 37/min)
- **Side effect:** Discovers new apps from chart results

### FetchChartSnapshotJob

Same as `SyncChartSnapshotJob` but without Redis throttle blocking. Used for on-demand chart fetches from the UI.

- **Retries:** 3 attempts with backoff `[30, 60, 120]` seconds

## Throttling

All scraper-bound jobs use Redis throttle to respect store rate limits:

```php
// Example: iOS sync throttle
Redis::throttle('sync-job:ios')
    ->allow(3)          // 3 jobs
    ->every(60)         // per minute
    ->block(300)        // wait up to 300s for a slot
    ->then(fn() => ...)
```

### Throttle Keys

| Key | Allow | Per | Platform |
|-----|-------|-----|----------|
| `sync-job:ios` | 3 | 60s | iOS |
| `sync-job:android` | 2 | 60s | Android |
| `chart-job:ios` | 24 | 60s | iOS |
| `chart-job:android` | 37 | 60s | Android |

Rates are configurable via environment variables (see [Configuration](../getting-started/configuration.md)).

## Queue Drivers

| Environment | Driver | Notes |
|-------------|--------|-------|
| Development | `redis` | Fast, in-memory. Redis also handles cache and throttle |
| Production | `database` | Persistent. Redis is not used in production |

## Worker Configuration

Workers process jobs from their assigned queues. In production, Laravel Supervisor manages workers. In development, the built-in scheduler handles job dispatch.

Restart workers after code changes:

```bash
docker compose exec appstorecat-server php artisan queue:restart
```

## Adding New Jobs

When creating new scraper-related jobs:

1. Always use platform-separated queues (`{queue}-ios` and `{queue}-android`)
2. Apply Redis throttle with the appropriate connector's rate config
3. Implement retry with exponential backoff
4. Consider `ShouldBeUnique` to prevent duplicate processing
