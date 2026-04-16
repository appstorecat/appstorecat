# Scraper API Rules

## Principles

- Scrapers are **stateless microservices** — no database, no cache, no auth
- Both scrapers expose **identical endpoint structure** with unified schemas
- Scrapers return data in a **normalized format** that server connectors consume directly
- Each scraper has its own Dockerfile, docker-compose.yml, and test suite
- Scraper APIs are **internal only** — not exposed to web or public

## Endpoint Convention

All scraper APIs must implement these endpoints:

```
GET /health
GET /apps/search?term=&limit=
GET /apps/{id}/identity
GET /apps/{id}/listings?locale=&country=
GET /apps/{id}/listings/locales?locales=|countries=
GET /apps/{id}/metrics
GET /apps/{id}/reviews?country=&limit=|page=
GET /developers/{id}/apps
GET /developers/search?term=
GET /docs
```

## Schema Convention

Response schemas must match across both scrapers. Shared model names:

- `AppIdentity` — app metadata
- `StoreListing` — store listing per locale
- `AppMetrics` — rating, installs, file size
- `AppReview` — user review
- `DeveloperApp` — developer's app summary
- `SearchResult` — search result item
- `HealthResponse` — health check
- `ErrorResponse` — error response

## No Cache Rule

Scrapers must **never** cache data. Every request hits the store directly. Caching is the server's responsibility (to be added later).

## Error Handling

- Return HTTP 500 with `{ "error": "message", "detail": "..." }` on scraper failures
- Never crash the process — catch all exceptions at endpoint level
- Log warnings for fallback scenarios (e.g., web scraping when package fails)

## Testing

- Mock the underlying scraper packages in tests
- Test all endpoints including edge cases (not found, invalid ID)
- Health endpoint must always return 200

## scraper-ios Specific

- **Stack:** TypeScript, Fastify 5, app-store-scraper
- **Fallback:** Web scraping for subtitle, screenshots, video, rating breakdown
- **Port:** 7462
- **Test runner:** vitest

## scraper-android Specific

- **Stack:** Python, FastAPI, gplay-scraper
- **Developer page:** Direct HTML scraping (gplay-scraper developer endpoint unreliable)
- **whatsNew:** May return as list — join with newlines
- **Port:** 7463
- **Test runner:** pytest
