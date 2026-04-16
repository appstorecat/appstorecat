# Architecture Overview

AppStoreCat is a monorepo with 4 services that communicate over HTTP:

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────────┐
│  Frontend   │────▶│  Backend (API)   │────▶│  scraper-ios   │
│  React SPA  │     │  Laravel 13      │     │  Fastify + Node.js  │
│  :7461      │     │  :7460           │     │  :7462              │
└─────────────┘     └──────┬───────────┘     └─────────────────────┘
                           │           │
                           │           │     ┌─────────────────────┐
                           │           └────▶│  scraper-android      │
                           │                 │  FastAPI + Python   │
                    ┌──────▼───────┐         │  :7463              │
                    │    MySQL     │         └─────────────────────┘
                    │    :7464     │
                    └──────────────┘
```

## Design Principles

### Backend as Gateway

The server is the single point of entry for all data operations. The web app never communicates directly with scrapers. This allows:

- Centralized authentication and rate limiting
- Data normalization across different store formats
- Background sync without web app involvement

### Stateless Scrapers

Scraper microservices have no database, no cache, no state. They receive a request, scrape the store, normalize the response, and return it. This keeps them simple, independently deployable, and easy to replace.

### Platform Separation

iOS and Android are treated as independent pipelines. They have separate:

- Queue workers (`sync-tracked-ios`, `sync-tracked-android`, etc.)
- Throttle rates (App Store and Google Play have different rate limits)
- Configuration (sync intervals, discovery sources, chart settings)
- Connectors (`ITunesLookupConnector`, `GooglePlayConnector`)

This ensures one platform's rate limits or failures never block the other.

## Services

| Service | Tech | Role |
|---------|------|------|
| **server** | Laravel 13, PHP 8.4 | API gateway, business logic, database owner, queue workers |
| **web** | React 19, Vite, TypeScript | User interface |
| **scraper-ios** | Fastify 5, Node.js | App Store data scraping |
| **scraper-android** | FastAPI, Python | Google Play data scraping |
| **mysql** | MySQL 8.4 | Persistent storage |
| **redis** | Redis 7 | Cache, queue broker, throttling (dev only) |

## Data Flow

### User-Initiated (Synchronous)

1. User searches for an app in the web app
2. Frontend calls `GET /api/v1/apps/search?term=...&platform=ios`
3. Backend forwards to `scraper-ios` via `ITunesLookupConnector`
4. Scraper returns results, server normalizes and returns to web
5. User clicks an app → server fetches full details via connector and creates DB records

### Background (Asynchronous)

1. Laravel scheduler dispatches sync jobs (e.g., `SyncAppJob`, `SyncChartSnapshotJob`)
2. Jobs are placed on platform-specific queues (`sync-tracked-ios`, `charts-android`, etc.)
3. Queue workers pick up jobs, apply Redis throttle, call connectors
4. Connectors call scraper microservices
5. Results are normalized and saved to database

## Infrastructure

### Development

All services run in Docker via `docker-compose.yml`. Redis handles queues, cache, and throttling.

### Production

Uses `docker-compose.production.yml` with pre-built images from Docker Hub. Key differences:

- **No Redis** — queues use the `database` driver
- **External network** — services are behind a reverse proxy (Dokploy)
- Services use `expose` instead of `ports` (proxy handles routing)
- `unless-stopped` restart policy

See [Deployment](../deployment/docker.md) for production setup details.
