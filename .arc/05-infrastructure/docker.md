# Docker & Service Orchestration

## Overview

Monorepo with 4 services orchestrated via root `docker-compose.yml`. Each service also has its own `docker-compose.yml` for standalone development.

## Services

| Service | Container | Port | Tech |
|---------|-----------|------|------|
| `server` | Laravel API | 7460 | PHP 8.4 FPM Alpine |
| `web` | React SPA | 7461 | Node 22, Vite |
| `scraper-ios` | App Store scraper | 7462 | Node 22, Fastify |
| `scraper-android` | Google Play scraper | 7463 | Python 3.12, FastAPI |
| `mysql` | Database | 7464 | MySQL 8.4 |
| `redis` | Cache/Queue | 6379 | Redis 7 Alpine |

Network: `appstorecat` (Docker bridge)

## Docker File Structure

Each service keeps Docker configs in `.docker/`:

```
server/.docker/
├── Dockerfile                      # Dev image (PHP 8.4 FPM Alpine)
├── Dockerfile.prod                 # Prod image (multi-stage)
├── conf/
│   ├── nginx/default.conf          # Nginx config (prod)
│   ├── supervisord/
│   │   ├── supervisord.conf        # Dev supervisor (artisan serve + workers)
│   │   └── supervisord.prod.conf   # Prod supervisor (php-fpm + nginx + workers)
│   └── php/php.ini                 # PHP config (xdebug, uploads)
├── scripts/entrypoint.sh           # Prod entrypoint
└── mysql/create-testing-database.sh

web/.docker/
├── Dockerfile                      # Dev (node + vite)
├── Dockerfile.prod                 # Prod (multi-stage + nginx)
├── conf/nginx/default.conf
└── scripts/entrypoint.sh

scraper-ios/.docker/
├── Dockerfile                      # Dev
└── Dockerfile.prod                 # Prod (multi-stage tsc)

scraper-android/.docker/
├── Dockerfile                      # Dev
└── Dockerfile.prod                 # Prod
```

## Rules

### Makefile-First

**ALL commands go through `make`.** The Makefile is the single entry point for every project operation.

- **NEVER** run `php artisan` directly — use `make artisan <command>`
- **NEVER** run `composer` directly — use `make composer <command>`
- **NEVER** run `docker compose exec` directly — use the Makefile target
- If a `docker compose exec` command is used more than twice, it MUST be added as a named Makefile target

### Docker Configs

- Docker build files live in each service's `.docker/` directory
- Configs are organized by type: `conf/nginx/`, `conf/supervisord/`, `conf/php/`
- Production entrypoints and scripts go in `scripts/`
- Dev and prod Dockerfiles are separate (`Dockerfile` vs `Dockerfile.prod`)

### Networking

- All services communicate via Docker network (`appstorecat`)
- Web proxies `/api` and `/storage` to `http://server:80`
- Scrapers are internal only — not exposed to web
- Server reaches scrapers via `host.docker.internal` in dev, Docker DNS in prod

### Process Management

- Queue workers and scheduler run via supervisord inside server container
- Dev uses `php artisan serve`, prod uses `php-fpm + nginx`
- Each queue has its own supervisor program (platform-separated)

## Environment Variables

Server reads scraper URLs from environment:

```
APPSTORE_API_URL=http://host.docker.internal:7462
GPLAY_API_URL=http://host.docker.internal:7463
```

These are set in root `docker-compose.yml` and `server/.env`.

## Individual Service Dev

```bash
make dev-server     # server + mysql + redis
make dev-web        # web only
make dev-android       # scraper-android only
make dev-ios    # scraper-ios only
```
