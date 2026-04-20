# Environment Variables

Full reference of every environment variable used by AppStoreCat.

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

## Rate Limits

| Variable | Default | Description |
|----------|---------|-------------|
| `APPSTORE_THROTTLE_SYNC_JOBS` | `5` | Maximum iOS sync jobs per minute |
| `GPLAY_THROTTLE_SYNC_JOBS` | `5` | Maximum Android sync jobs per minute |
| `APPSTORE_THROTTLE_CHART_JOBS` | `24` | Maximum iOS chart fetch jobs per minute |
| `GPLAY_THROTTLE_CHART_JOBS` | `37` | Maximum Android chart fetch jobs per minute |

## Sync Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `SYNC_IOS_TRACKED_ENABLED` | `true` | Enable tracked iOS app sync |
| `SYNC_IOS_TRACKED_REFRESH_HOURS` | `24` | Hours between tracked iOS syncs |
| `SYNC_IOS_DISCOVERY_ENABLED` | `true` | Enable discovered iOS app sync |
| `SYNC_IOS_DISCOVERY_REFRESH_HOURS` | `24` | Hours between discovered iOS syncs |
| `SYNC_ANDROID_TRACKED_ENABLED` | `true` | Enable tracked Android app sync |
| `SYNC_ANDROID_TRACKED_REFRESH_HOURS` | `24` | Hours between tracked Android syncs |
| `SYNC_ANDROID_DISCOVERY_ENABLED` | `true` | Enable discovered Android app sync |
| `SYNC_ANDROID_DISCOVERY_REFRESH_HOURS` | `24` | Hours between discovered Android syncs |

## Discovery Sources

Each source can be toggled independently per platform. When a source is off, unknown apps arriving via that path are not added to the database.

| Variable | Default | Description |
|----------|---------|-------------|
| `DISCOVER_IOS_ON_UNKNOWN` | `true` | Enable iOS discovery from unknown sources |
| `DISCOVER_IOS_ON_SEARCH` | `true` | Enable iOS discovery from store search |
| `DISCOVER_IOS_ON_TRENDING` | `true` | Enable iOS discovery from trending charts |
| `DISCOVER_IOS_ON_PUBLISHER_APPS` | `true` | Enable iOS discovery from publisher app lists |
| `DISCOVER_IOS_ON_REGISTER` | `true` | Enable iOS discovery from the user register flow |
| `DISCOVER_IOS_ON_IMPORT` | `true` | Enable iOS discovery from publisher import |
| `DISCOVER_IOS_ON_SIMILAR` | `true` | Enable iOS discovery via similar apps |
| `DISCOVER_IOS_ON_CATEGORY` | `true` | Enable iOS discovery from category listings |
| `DISCOVER_IOS_ON_DIRECT_VISIT` | `false` | Enable iOS discovery from direct visits by external ID (when off, unknown app URLs return 404) |
| `DISCOVER_ANDROID_ON_UNKNOWN` | `true` | Enable Android discovery from unknown sources |
| `DISCOVER_ANDROID_ON_SEARCH` | `true` | Enable Android discovery from store search |
| `DISCOVER_ANDROID_ON_TRENDING` | `true` | Enable Android discovery from trending charts |
| `DISCOVER_ANDROID_ON_PUBLISHER_APPS` | `true` | Enable Android discovery from publisher app lists |
| `DISCOVER_ANDROID_ON_REGISTER` | `true` | Enable Android discovery from the user register flow |
| `DISCOVER_ANDROID_ON_IMPORT` | `true` | Enable Android discovery from publisher import |
| `DISCOVER_ANDROID_ON_SIMILAR` | `true` | Enable Android discovery via similar apps |
| `DISCOVER_ANDROID_ON_CATEGORY` | `true` | Enable Android discovery from category listings |
| `DISCOVER_ANDROID_ON_DIRECT_VISIT` | `false` | Enable Android discovery from direct visits by external ID (when off, unknown app URLs return 404) |

## Charts

| Variable | Default | Description |
|----------|---------|-------------|
| `CHART_IOS_DAILY_SYNC_ENABLED` | `true` | Enable daily iOS chart sync |
| `CHART_ANDROID_DAILY_SYNC_ENABLED` | `true` | Enable daily Android chart sync |

## Queue and Cache

| Variable | Default | Description |
|----------|---------|-------------|
| `QUEUE_CONNECTION` | `redis` | Queue driver (development: `redis`, production: `database`) |
| `CACHE_STORE` | `redis` | Cache driver (development: `redis`, production: `file`) |
| `REDIS_HOST` | `appstorecat-redis` | Redis hostname |
| `REDIS_PORT` | `6379` | Redis port |
| `REDIS_PASSWORD` | `null` | Redis password |

## Session

| Variable | Default | Description |
|----------|---------|-------------|
| `SESSION_DRIVER` | `database` | Session storage driver |
| `SESSION_LIFETIME` | `120` | Session lifetime (minutes) |
| `BCRYPT_ROUNDS` | `12` | Password hashing cost |

## Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_CHANNEL` | `stack` | Log channel (development: `stack`, production: `stderr`) |
| `LOG_LEVEL` | `debug` | Minimum log level |

## Swagger

| Variable | Default | Description |
|----------|---------|-------------|
| `L5_SWAGGER_GENERATE_ALWAYS` | `true` | Auto-generate API documentation (`false` in production) |

## Docker Compose Ports

These are set in the root `.env` file (not `server/.env`):

| Variable | Default | Service |
|----------|---------|---------|
| `BACKEND_PORT` | `7460` | Backend API |
| `FRONTEND_PORT` | `7461` | Frontend |
| `APPSTORE_API_PORT` | `7462` | App Store scraper |
| `GPLAY_API_PORT` | `7463` | Google Play scraper |
| `FORWARD_DB_PORT` | `7464` | MySQL (external) |
| `FORWARD_REDIS_PORT` | `6379` | Redis (external) |
