# Architecture Overview

AppStoreCat is a monorepo of 4 services that communicate over HTTP:

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────────┐
│  Frontend   │────▶│  Backend (API)   │────▶│  scraper-ios        │
│  React SPA  │     │  Laravel 13      │     │  Fastify + Node.js  │
│  :7461      │     │  :7460           │     │  :7462              │
└─────────────┘     └──┬───────────┬───┘     └─────────────────────┘
                       │           │
                       │           │         ┌─────────────────────┐
                       │           └────────▶│  scraper-android    │
                       │                     │  FastAPI + Python   │
                ┌──────▼─────┐ ┌──────────┐  │  :7463              │
                │   MySQL    │ │  Redis   │  └─────────────────────┘
                │   :7464    │ │  :7465   │
                └────────────┘ └──────────┘
```

> All host-side ports use the **746x** series for consistency. Inside Docker, MySQL and Redis still listen on their standard ports (`3306`, `6379`).

## Design Principles

### Backend as Gateway

The backend is the single entry point for all data operations. The frontend never talks to the scrapers directly. This approach provides:

- Centralized authentication and rate limiting
- Data normalization across different store formats
- Background synchronization independent of the frontend

### Stateless Scrapers

The scraper microservices have no database, cache, or state. They receive a request, scrape the store, normalize the response, and return it. This keeps them simple, independently deployable, and easy to swap out.

### Platform Separation

iOS and Android are treated as independent pipelines. Each has its own:

- Queue workers (`sync-tracked-ios`, `sync-tracked-android`, etc.)
- Throttle rates (App Store and Google Play have different rate limits)
- Configuration (sync intervals, discovery sources, chart settings)
- Connectors (`ITunesLookupConnector`, `GooglePlayConnector`)

This ensures that one platform's rate limits or errors will never block the other.

## Services

| Service | Technology | Role |
|---------|------------|------|
| **server** | Laravel 13, PHP 8.4 | API gateway, business logic, database owner, queue workers |
| **web** | React 19, Vite, TypeScript | User interface |
| **scraper-ios** | Fastify 5, Node.js | App Store scraping |
| **scraper-android** | FastAPI, Python | Google Play scraping |
| **mysql** | MySQL 8.4 | Persistent storage |
| **redis** | Redis 7 | Cache, queue broker, throttling (development only) |

## Data Flow

### User-initiated (synchronous)

1. The user searches for an app on the web
2. The frontend calls `GET /api/v1/apps/search?term=...&platform=ios&country_code=us`
3. The backend forwards the request to `scraper-ios` through `ITunesLookupConnector`
4. The scraper returns results, the server normalizes them and forwards to the web
5. The user clicks an app → the server verifies it has already been discovered via search/chart (404/422 for unknown apps). Discovery via direct URL is disabled by default (`DISCOVER_{IOS,ANDROID}_ON_DIRECT_VISIT=false`).

### Background (asynchronous)

1. The Laravel scheduler dispatches sync jobs (e.g. `SyncAppJob`, `SyncChartSnapshotJob`, `ReconcileFailedItemsJob`)
2. Jobs are placed on platform-specific queues (`sync-tracked-ios`, `sync-on-demand-android`, `charts-android`, etc.)
3. Queue workers pick up jobs, apply Redis throttle, and call connectors
4. Connectors call the scraper microservices; when the scraper returns 404 the pipeline marks the result as `empty_response` (no infinite retries)
5. Results are normalized and persisted; `sync_statuses` tracks the progress and failed items of each step

## Infrastructure

### Development

All services run on Docker via `docker-compose.yml`. Redis manages queues, cache, and throttling.

### Production

Uses `docker-compose.production.yml` with pre-built Docker Hub images. Key differences:

- **No Redis** — queues use the `database` driver
- **External network** — services sit behind a reverse proxy (Dokploy)
- Services use `expose` instead of `ports` (the proxy handles routing)
- `unless-stopped` restart policy

See the [Deployment](../deployment/docker.md) page for production setup details.
