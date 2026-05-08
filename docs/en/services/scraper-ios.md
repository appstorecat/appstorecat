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

## Outbound Proxy

Set `IOS_PROXY_URL` to route every outbound call to Apple (the iTunes lookup API and the `apps.apple.com` web fallback used for screenshots/subtitles/ratings) through a proxy. Leave the variable empty to call Apple directly.

The proxy type is selected by the URL scheme:

| Scheme | Behavior |
|--------|----------|
| `http://[user:pass@]host:port` | The scraper exports `HTTPS_PROXY`/`HTTP_PROXY` so the legacy `request` library inside `app-store-scraper` picks the proxy up. Native `fetch()` is routed via undici's `EnvHttpProxyAgent`. |
| `https://[user:pass@]host:port` | Same as `http://`, with TLS to the proxy. |
| `socks5://[user:pass@]host:port` | The scraper threads a `socks-proxy-agent` instance into every `app-store-scraper` call's `requestOptions.agent` and installs undici's `Socks5ProxyAgent` as the global dispatcher for native `fetch()`. `socks5h://` is accepted as an alias. |

In all cases the scraper:

- redacts `user:pass@` credentials from error logs and HTTP error responses;
- reports `proxy: "configured"` on `GET /health`;
- logs `outbound proxy enabled` with `proxy_host` (no credentials) and `proxy_scheme` on startup.

For proxy rotation, point `IOS_PROXY_URL` at a sidecar proxy container (e.g. `tinyproxy`, `squid`) that handles the rotation upstream — the scraper itself does not rotate.

> **Note:** Node's SOCKS5 dispatcher is marked experimental upstream (`undici`); the scraper emits an `ExperimentalWarning` on startup when a SOCKS5 URL is configured. Functionality is stable — only the API stability marker is new.

## Design Principles

- **Stateless:** no database, no cache, no persistent state
- **Normalized responses:** raw App Store data is normalized into consistent JSON structures
- **Error propagation:** store errors (404, rate limit) are propagated with the correct HTTP status code; missing app = 404 (not 500)
- **Port:** configurable via the `PORT` environment variable (default: 7462)
