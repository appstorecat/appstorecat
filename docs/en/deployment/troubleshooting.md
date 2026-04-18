# Troubleshooting

Common issues and solutions when running AppStoreCat.

## Docker Issues

### Containers won't start

Check if ports are already in use:

```bash
lsof -i :7460   # Check server port
lsof -i :7461   # Check web port
```

Solution: Change ports in the root `.env` file or stop the conflicting process.

### MySQL health check failing

MySQL takes a few seconds to initialize. Check the logs:

```bash
docker compose logs appstorecat-mysql
```

If it keeps failing, ensure the volume isn't corrupted:

```bash
make clean   # Remove volumes
make setup   # Rebuild
```

### Redis connection refused

The server expects Redis to be available. In development, check:

```bash
docker compose ps appstorecat-redis
```

In production, Redis is not used — ensure `QUEUE_CONNECTION=database` and `CACHE_STORE=file` are set.

## Backend Issues

### Scraper connection errors

If the server can't reach scrapers:

1. Check scrapers are running: `make ps`
2. Verify URLs in `.env`:
   - Dev: `APPSTORE_API_URL=http://host.docker.internal:7462`
   - Prod: `APPSTORE_API_URL=http://appstorecat-scraper-ios:7462`
3. Test scraper health: `curl http://localhost:7462/health`

### Queue jobs not processing

Check if workers are running:

```bash
docker compose exec appstorecat-server php artisan queue:work --once
```

Restart workers:

```bash
docker compose exec appstorecat-server php artisan queue:restart
```

### Jobs going to failed_jobs

List failed jobs:

```bash
docker compose exec appstorecat-server php artisan queue:failed
```

Common causes:
- Scraper service down → restart scraper
- Rate limit exceeded → jobs will retry automatically
- App removed from store → expected 404, check `is_available` flag

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

Check the server URL configuration. The web app proxies API calls to the server:

```bash
# Check web logs
make logs-web
```

Ensure `BACKEND_URL` is set correctly in the web container environment.

### Hot reload not working

The web app volume mount should include `./web:/app`. Check that the Vite dev server is running:

```bash
make logs-web
```

## Scraper Issues

### App Store scraper returning empty results

Some category/country combinations are not supported by the App Store. This is expected and logged as a warning.

### Google Play scraper timeouts

Google Play scraping can be slower than App Store. Increase the timeout:

```env
GPLAY_TIMEOUT=60
```

## Performance

### Slow sync jobs

Check throttle rates in `config/appstorecat.php`. Default rates are conservative:

- iOS sync: 5 jobs/minute
- Android sync: 5 jobs/minute

These can be increased if your IP is not getting rate-limited.

### Database growing large

The `app_reviews` and `trending_chart_entries` tables grow the fastest. Consider:

- Disabling review sync for platforms you don't need
- Adjusting discovery sync frequency (`SYNC_{PLATFORM}_DISCOVERY_REFRESH_HOURS`)
