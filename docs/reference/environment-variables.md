# Environment Variables

Complete reference of all environment variables used by AppStoreCat.

## Application

| Variable | Default | Required | Description |
|----------|---------|----------|-------------|
| `APP_NAME` | `AppStoreCat` | No | Application name |
| `APP_ENV` | `local` | Yes | `local` or `production` |
| `APP_KEY` | — | Yes | Encryption key (generate with `php artisan key:generate`) |
| `APP_DEBUG` | `true` | No | Enable debug mode (`false` in production) |
| `APP_URL` | `http://localhost:7460` | Yes | Backend API base URL |
| `FRONTEND_URL` | `http://localhost:7461` | Yes | Frontend URL (used for CORS) |
| `APP_LOCALE` | `en` | No | Default application locale |

## Database

| Variable | Default | Required | Description |
|----------|---------|----------|-------------|
| `DB_CONNECTION` | `mysql` | No | Database driver |
| `DB_HOST` | `appstorecat-mysql` | Yes | MySQL hostname |
| `DB_PORT` | `3306` | No | MySQL port |
| `DB_DATABASE` | `appstorecat` | Yes | Database name |
| `DB_USERNAME` | `sail` | Yes | Database username |
| `DB_PASSWORD` | `password` | Yes | Database password |

## Scrapers

| Variable | Default | Required | Description |
|----------|---------|----------|-------------|
| `APPSTORE_API_URL` | `http://host.docker.internal:7462` | Yes | App Store scraper base URL |
| `GPLAY_API_URL` | `http://host.docker.internal:7463` | Yes | Google Play scraper base URL |
| `APPSTORE_TIMEOUT` | `30` | No | App Store request timeout (seconds) |
| `GPLAY_TIMEOUT` | `30` | No | Google Play request timeout (seconds) |

## Throttle Rates

| Variable | Default | Description |
|----------|---------|-------------|
| `APPSTORE_THROTTLE_SYNC_JOBS` | `3` | Max iOS sync jobs per minute |
| `GPLAY_THROTTLE_SYNC_JOBS` | `2` | Max Android sync jobs per minute |
| `APPSTORE_THROTTLE_CHART_JOBS` | `24` | Max iOS chart fetch jobs per minute |
| `GPLAY_THROTTLE_CHART_JOBS` | `37` | Max Android chart fetch jobs per minute |

## Sync Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `SYNC_IOS_TRACKED_ENABLED` | `true` | Enable tracked iOS app sync |
| `SYNC_IOS_TRACKED_REFRESH_HOURS` | `24` | Hours between tracked iOS syncs |
| `SYNC_IOS_DISCOVERY_ENABLED` | `true` | Enable discovered iOS app sync |
| `SYNC_IOS_DISCOVERY_REFRESH_HOURS` | `72` | Hours between discovered iOS syncs |
| `SYNC_IOS_REVIEWS_ENABLED` | `true` | Enable iOS review sync |
| `SYNC_ANDROID_TRACKED_ENABLED` | `true` | Enable tracked Android app sync |
| `SYNC_ANDROID_TRACKED_REFRESH_HOURS` | `24` | Hours between tracked Android syncs |
| `SYNC_ANDROID_DISCOVERY_ENABLED` | `true` | Enable discovered Android app sync |
| `SYNC_ANDROID_DISCOVERY_REFRESH_HOURS` | `72` | Hours between discovered Android syncs |
| `SYNC_ANDROID_REVIEWS_ENABLED` | `true` | Enable Android review sync |

## Charts

| Variable | Default | Description |
|----------|---------|-------------|
| `CHARTS_IOS_DAILY_SYNC_ENABLED` | `true` | Enable daily iOS chart sync |
| `CHARTS_ANDROID_DAILY_SYNC_ENABLED` | `true` | Enable daily Android chart sync |

## Queue & Cache

| Variable | Default | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | `redis` | Queue driver (`redis` dev, `database` prod) |
| `CACHE_STORE` | `redis` | Cache driver (`redis` dev, `file` prod) |
| `REDIS_HOST` | `appstorecat-redis` | Redis hostname |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | `null` | Redis password |

## Session

| Variable | Default | Description |
|----------|---------|-------------|
| `SESSION_DRIVER` | `database` | Session storage driver |
| `SESSION_LIFETIME` | `120` | Session lifetime in minutes |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost |

## Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_CHANNEL` | `stack` | Log channel (`stack` dev, `stderr` prod) |
| `LOG_LEVEL` | `debug` | Minimum log level |

## Swagger

| Variable | Default | Description |
|----------|---------|-------------|
| `L5_SWAGGER_GENERATE_ALWAYS` | `true` | Auto-generate API docs (`false` in production) |

## Docker Compose Ports

These are set in the root `.env` file (not `backend/.env`):

| Variable | Default | Service |
|----------|---------|---------|
| `BACKEND_PORT` | `7460` | Backend API |
| `FRONTEND_PORT` | `7461` | Frontend |
| `APPSTORE_API_PORT` | `7462` | App Store scraper |
| `GPLAY_API_PORT` | `7463` | Google Play scraper |
| `FORWARD_DB_PORT` | `7464` | MySQL (external) |
| `FORWARD_REDIS_PORT` | `6379` | Redis (external) |
