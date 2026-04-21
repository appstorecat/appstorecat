# Troubleshooting

Common issues you may hit running AppStoreCat, and how to fix them.

## Docker Issues

### Containers do not start

Check whether the ports are already in use:

```bash
lsof -i :7460   # Check the backend port
lsof -i :7461   # Check the frontend port
```

Fix: change the ports in the root `.env` file, or stop the conflicting process.

### MySQL health check fails

MySQL takes a few seconds to start. Check the logs:

```bash
docker compose logs appstorecat-mysql
```

If the issue persists, make sure the volume is not corrupted:

```bash
make clean   # Remove volumes
make setup   # Recreate
```

### Redis connection error

The backend waits for Redis to be reachable. In development, check:

```bash
docker compose ps appstorecat-redis
```

In production Redis is not used — make sure `QUEUE_CONNECTION=database` and `CACHE_STORE=file` are set.

## Backend Issues

### Scraper connection errors

If the backend cannot reach the scrapers:

1. Check that the scrapers are running: `make ps`
2. Verify the URLs in the `.env` file:
   - Development: `APPSTORE_API_URL=http://host.docker.internal:7462`
   - Production: `APPSTORE_API_URL=http://appstorecat-scraper-ios:7462`
3. Test scraper health: `curl http://localhost:7462/health`

### Queue jobs are not being processed

Check whether the workers are running:

```bash
docker compose exec appstorecat-server php artisan queue:work --once
```

Restart the workers:

```bash
docker compose exec appstorecat-server php artisan queue:restart
```

### Jobs land in the failed_jobs table

List failed jobs:

```bash
docker compose exec appstorecat-server php artisan queue:failed
```

Common causes:
- The scraper service is down → restart the scraper
- Rate limit exceeded → jobs retry automatically
- The app was removed from the store → the scraper returns HTTP 404; check the `apps.is_available` flag (reachable in at least one store) and the per-country `app_metrics.is_available` flags
- Persistent failed items are picked up by `ReconcileFailedItemsJob` and appear in the `reconciling` phase of `sync_statuses`

Retry all failed jobs:

```bash
docker compose exec appstorecat-server php artisan queue:retry all
```

### Migration errors

If migrations fail:

```bash
# Check current migration status
docker compose exec appstorecat-server php artisan migrate:status

# Run pending migrations
docker compose exec appstorecat-server php artisan migrate
```

## Frontend Issues

### Blank page / API errors

Check the server URL configuration. The web forwards API calls to the server:

```bash
# Check frontend logs
make logs-web
```

Make sure `BACKEND_URL` is set correctly in the frontend container's environment.

### Hot reload does not work

The frontend volume mount must include `./web:/app`. Check that the Vite dev server is running:

```bash
make logs-web
```

## Scraper Issues

### App Store scraper returns empty results

Some category/country combinations are not supported by the App Store. This is expected and is logged as a warning.

### Google Play scraper timeout

Google Play data fetches can be slower than App Store. Increase the timeout:

```env
GPLAY_TIMEOUT=60
```

## Performance

### Slow sync jobs

Check the rate limit settings in `config/appstorecat.php`. The defaults are conservative:

- iOS sync: 5 jobs per minute
- Android sync: 5 jobs per minute

These can be raised if your IP is not being rate limited.

### Database growth

The `app_metrics` and `trending_chart_entries` tables grow fastest. Consider:

- Lowering the batch size (`SYNC_{IOS,ANDROID}_TRACKED_BATCH_SIZE`) to slow ingestion of competitor / discovered apps
- Disabling daily chart sync for the platform you don't need (`CHART_{IOS,ANDROID}_DAILY_SYNC_ENABLED=false`)
- Narrowing the active country list via `countries.is_active_{ios,android}`

## Sync Pipeline

### What happens when failed sync items pile up?

The sync pipeline is split into phases (**identity → listings → metrics → finalize → reconciling**) and progress is tracked in the `sync_statuses` table. Failed items are automatically picked up and retried by `ReconcileFailedItemsJob`. For manual inspection:

```bash
make artisan tinker
>>> \App\Models\SyncStatus::where('phase', 'reconciling')->latest()->take(20)->get();
```

### Queues are blocked

iOS and Android queues are separated by platform (`sync-tracked-{ios,android}`, `sync-on-demand-{ios,android}`, `charts-{ios,android}`). A slow one will not block the other. To see which queue has a backlog:

```bash
make artisan queue:monitor sync-tracked-ios,sync-tracked-android,sync-on-demand-ios,sync-on-demand-android
```
