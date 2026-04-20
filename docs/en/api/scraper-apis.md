# Scraper APIs

The two scraper microservices expose REST APIs that the server consumes through connectors. These APIs are not intended for direct external use — the server API is the public interface.

## App Store Scraper (scraper-ios)

**Base URL:** `http://localhost:7462`
**Framework:** Fastify 5 (Node.js/TypeScript)
**Documentation:** http://localhost:7462/docs

### Endpoints

| Endpoint | Parameters | Description |
|----------|------------|-------------|
| `GET /health` | — | Returns `{status: "ok"}` |
| `GET /charts` | `collection` (required), `category`, `country`, `num` | Chart rankings (max 200) |
| `GET /apps/search` | `term` (required), `limit`, `country` | Search apps |
| `GET /apps/:appId/identity` | `country`, `lang` | App metadata (returns an `is_free` boolean) |
| `GET /apps/:appId/listings` | `country`, `lang` | Store listing (includes `promotional_text`) |
| `GET /apps/:appId/listings/locales` | `countries` (comma-separated) | Multi-country listings |
| `GET /apps/:appId/metrics` | `country`, `lang` | Rating, file size |
| `GET /developers/:developerId/apps` | — | Developer's apps |
| `GET /developers/search` | `term`, `limit`, `country` | Search developers |

### Chart Collections

- `top_free` — Top Free Apps
- `top_paid` — Top Paid Apps
- `top_grossing` — Top Grossing Apps

---

## Google Play Scraper (scraper-android)

**Base URL:** `http://localhost:7463`
**Framework:** FastAPI (Python)
**Documentation:** http://localhost:7463/docs

### Endpoints

| Endpoint | Parameters | Description |
|----------|------------|-------------|
| `GET /health` | — | Returns `{status: "ok"}` |
| `GET /charts` | `collection`, `category`, `country`, `count` | Chart rankings (max 200) |
| `GET /apps/search` | `term` (required), `limit`, `country` | Search apps |
| `GET /apps/{app_id}/identity` | `country` | App metadata |
| `GET /apps/{app_id}/listings` | `locale`, `country` | Store listing (`promotional_text` is always `null`) |
| `GET /apps/{app_id}/listings/locales` | `locales` (comma-separated) | Multi-locale listings |
| `GET /apps/{app_id}/metrics` | `country` | Rating (global), install count |
| `GET /developers/{developer_id}/apps` | — | Developer's apps |
| `GET /developers/search` | `term`, `limit`, `country` | Search developers |

### Chart Defaults

- Default collection: `top_free`
- Default category: `APPLICATION`
- Maximum count: 200 (but usually returns up to 100)

---

## Platform Differences

| Feature | App Store Scraper | Google Play Scraper |
|---------|-------------------|---------------------|
| Language parameter | `lang` | `locale` |
| Multi-locale parameter | `countries` | `locales` |
| Max chart results | 200 | ~100 |
| Subtitle support | Yes | No |
| `promotional_text` | Yes | No (`null`) |
| Install count | No | Yes (range string) |
| File size in metrics | Yes | No |
| Rating scope | Per country | Global (stored under the `zz` sentinel) |

## Error Format

Both scrapers return errors as follows:

```json
{
  "error": "Error message",
  "statusCode": 404
}
```

Common errors:
- `404` — App not found on this storefront. The Android scraper emits this as `AppNotFoundError` and the FastAPI exception handler turns it into 404. The iOS scraper also returns 404. Server connectors treat 404 as `empty_response` — the country is marked as permanently "unavailable" and will not be retried.
- `500` — Upstream scraper library error
