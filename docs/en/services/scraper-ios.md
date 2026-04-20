# App Store Scraper Service

A stateless Node.js microservice that fetches app data from the Apple App Store.

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Fastify 5 |
| Language | TypeScript |
| Scraper | app-store-scraper |
| API documentation | @fastify/swagger + Swagger UI |

## Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/health` | Health check |
| GET | `/charts` | Chart rankings (top free / paid / grossing) |
| GET | `/apps/search` | Search apps by term |
| GET | `/apps/:appId/identity` | App identity and metadata |
| GET | `/apps/:appId/listings` | Store listing for one country |
| GET | `/apps/:appId/listings/locales` | Listings for multiple countries |
| GET | `/apps/:appId/metrics` | Rating and metrics |
| GET | `/developers/:developerId/apps` | Developer's app catalog |
| GET | `/developers/search` | Search developers |

## Key Parameters

### Charts
- `collection` (required): `top_free`, `top_paid`, `top_grossing`
- `category`: App Store genre ID (optional)
- `country`: ISO country code (default: `us`)
- `num`: result count (default: 200, max: 200)

### Search
- `term` (required): search query (min 1 character)
- `limit`: maximum results (default: 10, max: 50)
- `country`: ISO country code (default: `us`)

### App Data
- `country`: ISO country code (default: `us`)
- `lang`: language code (optional)

## Response Format

### Identity

The identity response conveys paid/free info as an `is_free` boolean.

### Listing

- The `promotional_text` field (iOS-specific) is part of the response.

### Error Responses

All endpoints return JSON. Error responses use this format:

```json
{
  "error": "Error message",
  "statusCode": 404
}
```

## Error Semantics

The `sendScraperError()` helper maps errors from `app-store-scraper` (Error instances or plain objects) to the correct HTTP status code:

- **404 Not Found** — the app is not available in the target storefront. The server side interprets this as "permanently not available in this country".
- **5xx** — unexpected errors; retried on the server side.

Error cases are emitted as structured JSON logs.

## Running

```bash
make dev-ios      # Start the service
make logs-ios     # View logs
```

## API Documentation

While the service is running, the Swagger UI is available at `/docs`.

## Design Principles

- **Stateless:** no database, no cache, no persistent state
- **Normalized responses:** raw App Store data is normalized into consistent JSON structures
- **Error propagation:** store errors (404, rate limit) are propagated with the correct HTTP status code; missing app = 404 (not 500)
- **Port:** configurable via the `PORT` environment variable (default: 7462)
