# Configuration

AppStoreCat is configured through environment variables and a central config file. All configuration is in the server service.

## Environment Variables

The server `.env` file (`server/.env`) controls core settings. Copy from the example:

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

### Queue & Cache

| Variable | Default | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | `redis` | Queue driver: `redis` (dev), `database` (prod) |
| `CACHE_STORE` | `redis` | Cache driver |
| `REDIS_HOST` | `appstorecat-redis` | Redis host |

## AppStoreCat Configuration

The main configuration file is `server/config/appstorecat.php`. Settings are grouped into 4 sections:

### Connector Settings

Controls how the server communicates with scraper microservices:

| Variable | Default | Description |
|----------|---------|-------------|
| `APPSTORE_TIMEOUT` | `30` | App Store scraper request timeout (seconds) |
| `GPLAY_TIMEOUT` | `30` | Google Play scraper request timeout (seconds) |
| `APPSTORE_THROTTLE_SYNC_JOBS` | `3` | Max iOS sync jobs per minute |
| `GPLAY_THROTTLE_SYNC_JOBS` | `2` | Max Android sync jobs per minute |
| `APPSTORE_THROTTLE_CHART_JOBS` | `24` | Max iOS chart jobs per minute |
| `GPLAY_THROTTLE_CHART_JOBS` | `37` | Max Android chart jobs per minute |

### Sync Settings

Controls automatic app synchronization per platform:

| Variable | Default | Description |
|----------|---------|-------------|
| `SYNC_IOS_TRACKED_ENABLED` | `true` | Enable sync for tracked iOS apps |
| `SYNC_IOS_TRACKED_REFRESH_HOURS` | `24` | Hours between tracked iOS app syncs |
| `SYNC_IOS_DISCOVERY_ENABLED` | `true` | Enable sync for discovered iOS apps |
| `SYNC_IOS_DISCOVERY_REFRESH_HOURS` | `72` | Hours between discovered iOS app syncs |
| `SYNC_IOS_REVIEWS_ENABLED` | `true` | Enable iOS review syncing |
| `SYNC_ANDROID_TRACKED_ENABLED` | `true` | Enable sync for tracked Android apps |
| `SYNC_ANDROID_TRACKED_REFRESH_HOURS` | `24` | Hours between tracked Android app syncs |
| `SYNC_ANDROID_DISCOVERY_ENABLED` | `true` | Enable sync for discovered Android apps |
| `SYNC_ANDROID_DISCOVERY_REFRESH_HOURS` | `72` | Hours between discovered Android app syncs |
| `SYNC_ANDROID_REVIEWS_ENABLED` | `true` | Enable Android review syncing |

### Chart Settings

Controls daily trending chart synchronization:

| Variable | Default | Description |
|----------|---------|-------------|
| `CHARTS_IOS_DAILY_SYNC_ENABLED` | `true` | Enable daily iOS chart sync |
| `CHARTS_ANDROID_DAILY_SYNC_ENABLED` | `true` | Enable daily Android chart sync |

### Discovery Settings

Controls which actions can discover (create) new apps in the database. Each source can be toggled per platform in `config/appstorecat.php`:

| Source | Description |
|--------|-------------|
| `on_search` | Apps found via store search |
| `on_trending` | Apps found in trending charts |
| `on_publisher_apps` | Apps found via publisher's app list |
| `on_register` | Apps explicitly registered by users |
| `on_import` | Apps imported from publisher import |
| `on_direct_visit` | Apps visited directly by external ID |
| `on_unknown` | Apps from unknown sources |

## Docker Compose Ports

The root `docker-compose.yml` uses these port variables (set in the root `.env`):

| Variable | Default | Service |
|----------|---------|---------|
| `BACKEND_PORT` | `7460` | Laravel API |
| `FRONTEND_PORT` | `7461` | React web |
| `APPSTORE_API_PORT` | `7462` | App Store scraper |
| `GPLAY_API_PORT` | `7463` | Google Play scraper |
| `FORWARD_DB_PORT` | `7464` | MySQL |
| `FORWARD_REDIS_PORT` | `6379` | Redis |
