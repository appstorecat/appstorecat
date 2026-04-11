# Production Deployment

This guide covers deploying AppStoreCat to a production environment.

## Prerequisites

- A server with Docker and Docker Compose v2
- A reverse proxy (Nginx, Caddy, Traefik, or Dokploy)
- A domain name (optional but recommended)

## Step 1: Prepare Environment

Create a `.env` file on your server:

```env
# Application
APP_NAME=AppStoreCat
APP_ENV=production
APP_KEY=base64:...    # Generate with: php artisan key:generate --show
APP_DEBUG=false
APP_VERSION=0.0.3

# URLs (replace with your domain)
APP_URL=https://api.yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

# Scraper URLs (internal Docker network)
APPSTORE_API_URL=http://appstorecat-scraper-appstore:7462
GPLAY_API_URL=http://appstorecat-scraper-gplay:7463

# Database
DB_DATABASE=appstorecat
DB_USERNAME=appstorecat
DB_PASSWORD=your-secure-password
MYSQL_ROOT_PASSWORD=your-root-password

# Queue & Cache (no Redis in production)
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
# Pull the production compose file
docker compose -f docker-compose.production.yml up -d
```

## Step 3: Initialize Database

```bash
docker compose -f docker-compose.production.yml exec appstorecat-backend php artisan migrate
docker compose -f docker-compose.production.yml exec appstorecat-backend php artisan db:seed
```

## Step 4: Configure Reverse Proxy

Route traffic to the exposed ports:

| Domain/Path | Service Port |
|-------------|-------------|
| `api.yourdomain.com` | `appstorecat-backend:7460` |
| `app.yourdomain.com` | `appstorecat-frontend:7461` |

The scraper services are internal only and should not be publicly accessible.

## Updating

```bash
# Pull new images
docker compose -f docker-compose.production.yml pull

# Restart with new images
docker compose -f docker-compose.production.yml up -d

# Run migrations
docker compose -f docker-compose.production.yml exec appstorecat-backend php artisan migrate
```

## Queue Workers

In production, the backend container runs a Supervisor process that manages:

- **Queue workers** — Process background sync and chart jobs
- **Scheduler** — Dispatches recurring jobs (cron)

These are configured automatically in the production Docker image.

## Monitoring

### Logs

```bash
# All service logs
docker compose -f docker-compose.production.yml logs -f

# Backend only
docker compose -f docker-compose.production.yml logs -f appstorecat-backend
```

### Failed Jobs

Check failed background jobs:

```bash
docker compose -f docker-compose.production.yml exec appstorecat-backend php artisan queue:failed
```

Retry failed jobs:

```bash
docker compose -f docker-compose.production.yml exec appstorecat-backend php artisan queue:retry all
```

## Security Considerations

- Set `APP_DEBUG=false` in production
- Use strong database passwords
- Keep scraper services internal (not publicly accessible)
- Use HTTPS via your reverse proxy
- Set `L5_SWAGGER_GENERATE_ALWAYS=false` to disable Swagger in production
