# Scraper APIs

The two scraper microservices expose REST APIs that the backend consumes through connectors. These APIs are not intended for direct external use — the backend API is the public interface.

## App Store Scraper (scraper-appstore)

**Base URL:** `http://localhost:7462`
**Framework:** Fastify 5 (Node.js/TypeScript)
**Docs:** http://localhost:7462/docs

### Endpoints

| Endpoint | Params | Description |
|----------|--------|-------------|
| `GET /health` | — | Returns `{status: "ok"}` |
| `GET /charts` | `collection` (req), `category`, `country`, `num` | Chart rankings (max 200) |
| `GET /apps/search` | `term` (req), `limit`, `country` | Search apps |
| `GET /apps/:appId/identity` | `country`, `lang` | App metadata |
| `GET /apps/:appId/listings` | `country`, `lang` | Store listing |
| `GET /apps/:appId/listings/locales` | `countries` (comma-separated) | Multi-country listings |
| `GET /apps/:appId/metrics` | `country`, `lang` | Rating, file size |
| `GET /apps/:appId/reviews` | `country`, `page` | User reviews |
| `GET /developers/:developerId/apps` | — | Developer's apps |
| `GET /developers/search` | `term`, `limit`, `country` | Search developers |

### Chart Collections

- `top_free` — Top Free Apps
- `top_paid` — Top Paid Apps
- `top_grossing` — Top Grossing Apps

---

## Google Play Scraper (scraper-gplay)

**Base URL:** `http://localhost:7463`
**Framework:** FastAPI (Python)
**Docs:** http://localhost:7463/docs

### Endpoints

| Endpoint | Params | Description |
|----------|--------|-------------|
| `GET /health` | — | Returns `{status: "ok"}` |
| `GET /charts` | `collection`, `category`, `country`, `count` | Chart rankings (max 200) |
| `GET /apps/search` | `term` (req), `limit`, `country` | Search apps |
| `GET /apps/{app_id}/identity` | `country` | App metadata |
| `GET /apps/{app_id}/listings` | `locale`, `country` | Store listing |
| `GET /apps/{app_id}/listings/locales` | `locales` (comma-separated) | Multi-locale listings |
| `GET /apps/{app_id}/metrics` | `country` | Rating, installs |
| `GET /apps/{app_id}/reviews` | `country`, `limit` | User reviews (max 500) |
| `GET /developers/{developer_id}/apps` | — | Developer's apps |
| `GET /developers/search` | `term`, `limit`, `country` | Search developers |

### Chart Defaults

- Default collection: `top_free`
- Default category: `APPLICATION`
- Max count: 200 (but typically returns up to 100)

---

## Platform Differences

| Feature | App Store Scraper | Google Play Scraper |
|---------|-------------------|---------------------|
| Language param | `lang` | `locale` |
| Multi-locale param | `countries` | `locales` |
| Chart max results | 200 | ~100 |
| Review pagination | `page` param | `limit` param (max 500) |
| Subtitle support | Yes | No |
| Install count | No | Yes (range string) |
| File size in metrics | Yes | No |
| Review country scope | Per country | Global |

## Error Format

Both scrapers return errors as:

```json
{
  "error": "Error message",
  "statusCode": 404
}
```

Common errors:
- `404` — App not found / removed from store
- `500` — Upstream scraper library error
