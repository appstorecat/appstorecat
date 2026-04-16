# Docker Deployment

AppStoreCat runs entirely in Docker, both for development and production.

## Development Stack

The development stack (`docker-compose.yml`) runs 6 containers:

| Container | Image | Port |
|-----------|-------|------|
| `appstorecat-server` | Built from `server/.docker/Dockerfile` | 7460 |
| `appstorecat-web` | Built from `web/.docker/Dockerfile` | 7461 |
| `appstorecat-scraper-ios` | Built from `scraper-ios/.docker/Dockerfile` | 7462 |
| `appstorecat-scraper-android` | Built from `scraper-android/.docker/Dockerfile` | 7463 |
| `appstorecat-mysql` | `mysql:8.4` | 7464 |
| `appstorecat-redis` | `redis:7-alpine` | 6379 |

### Start Development

```bash
make setup   # First time only
make dev     # Start all services
```

### Volumes

- `./server` is mounted into the server container for live reload
- `./web` is mounted into the web container (with `/app/node_modules` excluded)
- `./scraper-ios` and `./scraper-android` are mounted for live reload
- MySQL and Redis data persist in named Docker volumes

### Health Checks

MySQL and Redis have health checks configured. The server container waits for both to be healthy before starting (`depends_on` with `service_healthy`).

## Production Stack

The production stack (`docker-compose.production.yml`) uses pre-built images from Docker Hub.

### Key Differences from Development

| Aspect | Development | Production |
|--------|-------------|------------|
| Images | Built locally | Pre-built from Docker Hub (`appstorecat/appstorecat-*`) |
| Redis | Yes (queue, cache, throttle) | No (database queue, file cache) |
| Ports | Published (`ports`) | Internal only (`expose`) |
| Network | Local bridge | External `dokploy-network` + internal bridge |
| Restart | None | `unless-stopped` |
| Logging | Default | `stderr` driver |
| Queue driver | `redis` | `database` |

### Production Environment Variables

In addition to the standard `.env` variables, production requires:

```env
APP_ENV=production
APP_DEBUG=false
APP_VERSION=0.0.3
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr
```

### Building Production Images

```bash
# Build and push multi-platform images for all services
make build-prod

# Full release: bump version, build, push, tag
make release v=0.0.4
```

Images are built for `linux/amd64` and `linux/arm64`.

### Networks

Production uses two networks:
- **dokploy-network** (external) — Managed by the Dokploy reverse proxy
- **appstorecat** (bridge) — Internal service communication

## Docker Commands

```bash
make ps          # Show service status
make logs        # Follow all logs
make logs-server   # Follow server logs only
make restart     # Restart all services
make clean       # Stop + remove volumes
make nuke        # Full cleanup including images
```
