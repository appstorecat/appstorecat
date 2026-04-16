# Google Play Scraper Service

A stateless Python microservice that scrapes app data from the Google Play Store.

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | FastAPI |
| Language | Python |
| Scraper | gplay-scraper |
| Server | uvicorn |
| Validation | Pydantic |
| Tests | pytest |

## Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/health` | Health check |
| GET | `/charts` | Chart rankings |
| GET | `/apps/search` | Search apps by term |
| GET | `/apps/{app_id}/identity` | App identity and metadata |
| GET | `/apps/{app_id}/listings` | Store listing for a locale |
| GET | `/apps/{app_id}/listings/locales` | Listings for multiple locales |
| GET | `/apps/{app_id}/metrics` | Rating and metrics |
| GET | `/apps/{app_id}/reviews` | User reviews |
| GET | `/developers/{developer_id}/apps` | Developer's app catalog |
| GET | `/developers/search` | Search for developers |

## Key Parameters

### Charts
- `collection`: Chart type (default: `top_free`)
- `category`: Google Play category (default: `APPLICATION`)
- `country`: ISO country code (default: `us`)
- `count`: Number of results (default: 100, max: 200)

### Search
- `term` (required): Search query (min 1 char)
- `limit`: Max results (default: 10, max: 50)
- `country`: ISO country code (default: `us`)

### App Data
- `country`: ISO country code (default: `us`)
- `locale`: Locale code (default: `en`)

## Key Differences from App Store Scraper

| Aspect | App Store | Google Play |
|--------|-----------|-------------|
| Chart depth | Up to 200 | Up to 100 |
| Review scope | Per country | Global |
| Subtitle | Supported | Not available |
| Install count | Not available | Available (range) |
| Locale param | `lang` | `locale` |
| Default category | None | `APPLICATION` |

## Running

```bash
make dev-android      # Start service
make logs-android     # View logs
make test-android     # Run pytest
```

## API Documentation

OpenAPI docs are available at `/docs` when the service is running (FastAPI auto-generated).

## Design Principles

- **Stateless:** No database, no cache, no persistent state
- **Pydantic models:** All responses are validated through Pydantic schemas
- **Error forwarding:** Store errors are forwarded with appropriate status codes
- **Port:** Configurable via `PORT` env var (default: 7463)
