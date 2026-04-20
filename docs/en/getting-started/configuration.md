# Configuration

AppStoreCat is configured through environment variables and a central configuration file. All configuration lives in the server service.

## Environment Variables

The backend `.env` file (`server/.env`) controls core settings. Copy from the example file:

```bash
cp server/.env.example server/.env
```

### Application

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `local` | Environment: `local`, `production` |
| `APP_DEBUG` | `true` | Enable debug mode |
| `APP_URL` | `http://localhost:7460` | Backend API URL |
| `FRONTEND_URL` | `http://localhost:7461` | Frontend URL (for CORS) |

### Database

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `appstorecat-mysql` | MySQL host (Docker service name) |
| `DB_PORT` | `3306` | MySQL port |
| `DB_DATABASE` | `appstorecat` | Database name |
| `DB_USERNAME` | `sail` | Database user |
| `DB_PASSWORD` | `password` | Database password |

### Scraper URLs

| Variable | Default | Description |
|----------|---------|-------------|
| `APPSTORE_API_URL` | `http://host.docker.internal:7462` | App Store scraper URL |
| `GPLAY_API_URL` | `http://host.docker.internal:7463` | Google Play scraper URL |

### Queue and Cache

| Variable | Default | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | `redis` | Queue driver: `redis` (development), `database` (production) |
| `CACHE_STORE` | `redis` | Cache driver |
| `REDIS_HOST` | `appstorecat-redis` | Redis host |

## AppStoreCat Configuration

The main configuration file is `server/config/appstorecat.php`. Settings are grouped into 4 sections:

### Connector Settings

Control how the backend talks to the scraper microservices:

| Variable | Default | Description |
|----------|---------|-------------|
| `APPSTORE_TIMEOUT` | `30` | App Store scraper request timeout (seconds) |
| `GPLAY_TIMEOUT` | `30` | Google Play scraper request timeout (seconds) |
| `APPSTORE_THROTTLE_SYNC_JOBS` | `5` | Max iOS sync jobs per minute |
| `GPLAY_THROTTLE_SYNC_JOBS` | `5` | Max Android sync jobs per minute |
| `APPSTORE_THROTTLE_CHART_JOBS` | `24` | Max iOS chart jobs per minute |
| `GPLAY_THROTTLE_CHART_JOBS` | `37` | Max Android chart jobs per minute |

### Sync Settings

Control automatic app sync per platform:

| Variable | Default | Description |
|----------|---------|-------------|
| `SYNC_IOS_TRACKED_ENABLED` | `true` | Enable sync for tracked iOS apps |
| `SYNC_IOS_TRACKED_REFRESH_HOURS` | `24` | Tracked iOS apps sync interval (hours) |
| `SYNC_IOS_DISCOVERY_ENABLED` | `true` | Enable sync for discovered iOS apps |
| `SYNC_IOS_DISCOVERY_REFRESH_HOURS` | `24` | Discovered iOS apps sync interval (hours) |
| `SYNC_ANDROID_TRACKED_ENABLED` | `true` | Enable sync for tracked Android apps |
| `SYNC_ANDROID_TRACKED_REFRESH_HOURS` | `24` | Tracked Android apps sync interval (hours) |
| `SYNC_ANDROID_DISCOVERY_ENABLED` | `true` | Enable sync for discovered Android apps |
| `SYNC_ANDROID_DISCOVERY_REFRESH_HOURS` | `24` | Discovered Android apps sync interval (hours) |

The sync pipeline runs in phases and is tracked via the `sync_statuses` table: **identity → listings → metrics → finalize → reconciling**. Failed items are picked up and retried by `ReconcileFailedItemsJob`.

### Chart Settings

Control daily trending chart sync:

| Variable | Default | Description |
|----------|---------|-------------|
| `CHART_IOS_DAILY_SYNC_ENABLED` | `true` | Enable daily iOS chart sync |
| `CHART_ANDROID_DAILY_SYNC_ENABLED` | `true` | Enable daily Android chart sync |

### Discovery Settings

Control which actions can discover (create) new apps in the database. Each source can be toggled per platform via `DISCOVER_{IOS,ANDROID}_ON_{SOURCE}` environment variables:

| Source | Default | Description |
|--------|---------|-------------|
| `on_search` | `true` | Apps found via store search |
| `on_trending` | `true` | Apps found in trending charts |
| `on_publisher_apps` | `true` | Apps found via the publisher's app list |
| `on_register` | `true` | Apps registered directly by users |
| `on_import` | `true` | Apps imported via publisher import |
| `on_similar` | `true` | Apps found via similar apps |
| `on_category` | `true` | Apps found in category listings |
| `on_direct_visit` | `false` | Apps visited directly by external ID — when off, unknown app URLs return 404 |
| `on_unknown` | `true` | Apps from unknown sources |

## Docker Compose Ports

The root `docker-compose.yml` uses these port variables (set in the root `.env` file):

| Variable | Default | Service |
|----------|---------|---------|
| `BACKEND_PORT` | `7460` | Laravel API |
| `FRONTEND_PORT` | `7461` | React web |
| `APPSTORE_API_PORT` | `7462` | App Store scraper |
| `GPLAY_API_PORT` | `7463` | Google Play scraper |
| `FORWARD_DB_PORT` | `7464` | MySQL |
| `FORWARD_REDIS_PORT` | `6379` | Redis |
