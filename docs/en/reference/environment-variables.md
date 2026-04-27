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
| `SYNC_IOS_TRACKED_BATCH_SIZE` | `5` | Max iOS apps dispatched per 20-minute tick |
| `SYNC_ANDROID_TRACKED_ENABLED` | `true` | Enable tracked Android app sync |
| `SYNC_ANDROID_TRACKED_REFRESH_HOURS` | `24` | Hours between tracked Android syncs |
| `SYNC_ANDROID_TRACKED_BATCH_SIZE` | `5` | Max Android apps dispatched per 20-minute tick |

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

## Session & Auth

| Variable | Default | Required | Description |
|----------|---------|----------|-------------|
| `SESSION_DRIVER` | `database` | No | Session storage driver |
| `SESSION_LIFETIME` | `120` | No | Session lifetime (minutes) |
| `SESSION_DOMAIN` | `null` | Production | Cookie domain (e.g. `.appstore.cat`). Required when frontend and backend are on different subdomains under the same registered domain. |
| `SESSION_SECURE_COOKIE` | `false` | Production | Set to `true` when serving over HTTPS |
| `SESSION_SAME_SITE` | `lax` | No | `lax`, `strict`, or `none`. Use `none` only with `SESSION_SECURE_COOKIE=true` for cross-site SPA. |
| `SANCTUM_STATEFUL_DOMAINS` | — | Production | Comma-separated list of frontend domains permitted to use cookie auth (e.g. `appstore.cat,www.appstore.cat`). Without this, SPA login silently fails. |
| `SANCTUM_TOKEN_PREFIX` | — | No | Optional prefix prepended to all generated API tokens (helps with secret scanning) |
| `BCRYPT_ROUNDS` | `12` | No | Password hashing cost |

## Logging

| Variable | Default | Description |
|----------|---------|-------------|
| `LOG_CHANNEL` | `stack` | Log channel (development: `stack`, production: `stderr` for container-friendly logs) |
| `LOG_STACK` | `single` | Channels included in `stack` (comma-separated: `single,daily,slack`) |
| `LOG_LEVEL` | `debug` | Minimum log level (production: `warning` or `error`) |
| `LOG_DAILY_DAYS` | `14` | Retention (days) for the `daily` channel |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | Channel for deprecation warnings (e.g. `daily`) |

## Workers (Production)

These are read by the production `supervisord` config inside the server container. Override at `docker compose` level via environment.

| Variable | Default | Description |
|----------|---------|-------------|
| `SUPERVISOR_QUEUE_NUMPROCS` | `2` | Number of `queue:work` worker processes |
| `SUPERVISOR_QUEUE_TIMEOUT` | `120` | Per-job timeout (seconds) |
| `SUPERVISOR_QUEUE_TRIES` | `3` | Max retry attempts before failed_jobs |
| `SUPERVISOR_QUEUE_BACKOFF` | `60` | Backoff (seconds) between retries |
| `SUPERVISOR_QUEUES` | `default,sync-tracked-ios,sync-tracked-android,sync-on-demand-ios,sync-on-demand-android,charts-ios,charts-android` | Comma-separated queue priority order |
| `SCHEDULER_ENABLED` | `true` | Run Laravel scheduler inside the server container (set `false` if you run it externally) |

## Frontend (build-time)

Set in the root `.env`. Read by Vite during the web container build.

| Variable | Default | Description |
|----------|---------|-------------|
| `BACKEND_URL` | `http://appstorecat-server:80` | Where the SPA proxies API requests to (Docker-internal) |
| `GA_MEASUREMENT_ID` | — | Google Analytics 4 ID. Leave empty to disable. Safe to expose (client-side ID). |

## MCP Server

Read by `@appstorecat/mcp` (the MCP server is a separate Node.js process spawned by Claude Code; these are NOT set in the Laravel `.env`).

| Variable | Default | Required | Description |
|----------|---------|----------|-------------|
| `APPSTORECAT_API_TOKEN` | — | Yes | Sanctum bearer token. Created from the web UI: Settings → API Tokens → Create. |
| `APPSTORECAT_API_URL` | `http://localhost:7460/api/v1` | No | API base URL. Use `https://server.appstore.cat/api/v1` for the hosted demo. |

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
| `FORWARD_DB_PORT` | `7464` | MySQL (host-side mapping for container port `3306`) |
| `FORWARD_REDIS_PORT` | `7465` | Redis (host-side mapping for container port `6379`) |
