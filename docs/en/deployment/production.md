# Production Deployment

This guide covers deploying AppStoreCat to production.

## Requirements

- A server with Docker and Docker Compose v2 installed
- A reverse proxy (Nginx, Caddy, Traefik, or Dokploy)
- A domain name (optional but recommended)

## Step 1: Prepare the Environment

Create an `.env` file on your server:

```env
# Application
APP_NAME=AppStoreCat
APP_ENV=production
APP_KEY=base64:...    # Generate with: php artisan key:generate --show
APP_DEBUG=false
APP_VERSION=0.0.3

# URLs (replace with your own domain)
APP_URL=https://api.yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

# Scraper URLs (internal Docker network)
APPSTORE_API_URL=http://appstorecat-scraper-ios:7462
GPLAY_API_URL=http://appstorecat-scraper-android:7463

# Database
DB_DATABASE=appstorecat
DB_USERNAME=appstorecat
DB_PASSWORD=your-secure-password
MYSQL_ROOT_PASSWORD=your-root-password

# Queue and Cache (no Redis in production)
QUEUE_CONNECTION=database
CACHE_STORE=file

# Logging
LOG_CHANNEL=stderr

# Ports
BACKEND_PORT=7460
FRONTEND_PORT=7461
APPSTORE_API_PORT=7462
GPLAY_API_PORT=7463
FORWARD_DB_PORT=3306
```

## Step 2: Deploy

```bash
# Run the production compose file
docker compose -f docker-compose.production.yml up -d
```

## Step 3: Initialize the Database

```bash
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan migrate
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan db:seed
```

## Step 4: Configure the Reverse Proxy

Route traffic to the exposed ports:

| Domain/Path | Service Port |
|-------------|--------------|
| `api.yourdomain.com` | `appstorecat-server:7460` |
| `app.yourdomain.com` | `appstorecat-web:7461` |

The scraper services are for internal use only and should not be publicly accessible.

## Upgrading

```bash
# Pull new images
docker compose -f docker-compose.production.yml pull

# Restart with new images
docker compose -f docker-compose.production.yml up -d

# Run migrations
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan migrate
```

## Queue Workers

In production, the server container runs a Supervisor process that manages:

- **Queue workers** — process background sync and chart jobs. All scraper queues are platform-separated: `sync-tracked-{ios,android}`, `sync-on-demand-{ios,android}`, `charts-{ios,android}`. iOS and Android have independent rate limits and worker profiles.
- **Scheduler** — dispatches recurring jobs (cron); `ReconcileFailedItemsJob` periodically re-queues failed sync items.

These are configured automatically in the production Docker image.

## Monitoring

### Logs

```bash
# All service logs
docker compose -f docker-compose.production.yml logs -f

# Server only
docker compose -f docker-compose.production.yml logs -f appstorecat-server
```

### Failed Jobs

Check failed background jobs:

```bash
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan queue:failed
```

Retry failed jobs:

```bash
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan queue:retry all
```

## Security Considerations

- Set `APP_DEBUG=false` in production
- Use strong database passwords
- Keep scraper services internal (not publicly accessible)
- Use HTTPS through your reverse proxy
- Set `L5_SWAGGER_GENERATE_ALWAYS=false` to disable Swagger in production
