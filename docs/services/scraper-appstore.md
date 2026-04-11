# App Store Scraper Service

A stateless Node.js microservice that scrapes app data from the Apple App Store.

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Fastify 5 |
| Language | TypeScript |
| Scraper | app-store-scraper |
| API Docs | @fastify/swagger + Swagger UI |
| Tests | Vitest |

## Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/health` | Health check |
| GET | `/charts` | Chart rankings (top free/paid/grossing) |
| GET | `/apps/search` | Search apps by term |
| GET | `/apps/:appId/identity` | App identity and metadata |
| GET | `/apps/:appId/listings` | Store listing for a country |
| GET | `/apps/:appId/listings/locales` | Listings for multiple countries |
| GET | `/apps/:appId/metrics` | Rating and metrics |
| GET | `/apps/:appId/reviews` | User reviews |
| GET | `/developers/:developerId/apps` | Developer's app catalog |
| GET | `/developers/search` | Search for developers |

## Key Parameters

### Charts
- `collection` (required): `top_free`, `top_paid`, `top_grossing`
- `category`: App Store genre ID (optional)
- `country`: ISO country code (default: `us`)
- `num`: Number of results (default: 200, max: 200)

### Search
- `term` (required): Search query (min 1 char)
- `limit`: Max results (default: 10, max: 50)
- `country`: ISO country code (default: `us`)

### App Data
- `country`: ISO country code (default: `us`)
- `lang`: Language code (optional)

## Response Format

All endpoints return JSON. Error responses use:

```json
{
  "error": "Error message",
  "statusCode": 404
}
```

## Running

```bash
make dev-appstore      # Start service
make logs-appstore     # View logs
make test-appstore     # Run vitest
```

## API Documentation

Swagger UI is available at `/docs` when the service is running.

## Design Principles

- **Stateless:** No database, no cache, no persistent state
- **Normalized responses:** Raw App Store data is normalized into consistent JSON structures
- **Error forwarding:** Store errors (404, rate limits) are forwarded with appropriate status codes
- **Port:** Configurable via `PORT` env var (default: 7462)
