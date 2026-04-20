# Connectors

Connectors are the server's interface for talking to the scraper microservices. They abstract HTTP communication, normalize response formats, and provide a unified API for both platforms.

## Architecture

```
Backend Service Layer
        │
        ▼
ConnectorInterface
        │
   ┌────┴─────────────────┐
   ▼                       ▼
ITunesLookupConnector    GooglePlayConnector
   │                       │
   ▼                       ▼
scraper-ios :7462   scraper-android :7463
```

## ConnectorInterface

All connectors implement the same interface:

```php
interface ConnectorInterface
{
    public function supports(string $platform): bool;
    public function fetchIdentity(App $app, string $country = 'us'): ConnectorResult;
    public function fetchListings(App $app, string $country = 'us', ?string $locale = null): ConnectorResult;
    public function fetchMetrics(App $app, string $country = 'us'): ConnectorResult;
    public function fetchDeveloperApps(string $developerExternalId): ConnectorResult;
    public function fetchSearch(string $term, int $limit = 10, string $country = 'us'): array;
    public function fetchChart(string $collection, string $country, ?string $categoryExternalId = null): array;
    public function getSourceName(): string;
}
```

## ConnectorResult

Every connector method returns a `ConnectorResult`:

```php
class ConnectorResult
{
    public readonly bool $success;
    public readonly array $data;
    public readonly ?string $error;
    public readonly ?int $statusCode;
}
```

- `ConnectorResult::success($data)` — Successful response carrying normalized data
- `ConnectorResult::failure($error, $statusCode)` — Failed response with error details (404 becomes `empty_response`)

## ITunesLookupConnector

Talks to the App Store scraper microservice (`scraper-ios`).

| Method | Scraper Endpoint | Key Response Fields |
|--------|-----------------|---------------------|
| `fetchIdentity` | `GET /apps/:appId/identity` | name, publisher, category, locales, release_date, is_free |
| `fetchListings` | `GET /apps/:appId/listings` | title, subtitle, promotional_text, description, whats_new, screenshots, icon, price |
| `fetchMetrics` | `GET /apps/:appId/metrics` | rating, rating_count, rating_breakdown, file_size |
| `fetchDeveloperApps` | `GET /developers/:id/apps` | apps[] with basic details |
| `fetchSearch` | `GET /apps/search` | results[] with app_id, name, developer |
| `fetchChart` | `GET /charts` | ranked results (up to 200 apps) |

> The iOS identity response conveys free/paid information as an `is_free` boolean. The listing payload includes a `promotional_text` field.

**Configuration:** `appstorecat.connectors.appstore.base_url`, `appstorecat.connectors.appstore.timeout`

## GooglePlayConnector

Talks to the Google Play scraper microservice (`scraper-android`).

| Method | Scraper Endpoint | Key Response Fields |
|--------|-----------------|---------------------|
| `fetchIdentity` | `GET /apps/{app_id}/identity` | Same as the iTunes connector |
| `fetchListings` | `GET /apps/{app_id}/listings` | Same (uses `locale` instead of `lang`; `promotional_text` is always `null`) |
| `fetchMetrics` | `GET /apps/{app_id}/metrics` | Same (no file_size_bytes; rating is global for Play) |
| `fetchDeveloperApps` | `GET /developers/{id}/apps` | Same |
| `fetchSearch` | `GET /apps/search` | Same |
| `fetchChart` | `GET /charts` | Same (default category: APPLICATION, maximum 100) |

> Since Android metrics have no per-country separation, `AppSyncer` writes them to the `app_metrics` table under the `zz` "Global" sentinel country.

**Configuration:** `appstorecat.connectors.gplay.base_url`, `appstorecat.connectors.gplay.timeout`

## Data Normalization

Both connectors normalize store-specific response formats into a consistent structure. For example:

- **Screenshots:** Both return `string[]` URL arrays
- **Rating breakdown:** Both return `{1: count, 2: count, ..., 5: count}`
- **Publisher:** Both return `publisher_name`, `publisher_external_id`, `publisher_url`
- **Identity:** Both return the same fields (some may be null depending on platform)

This normalization happens in the connector layer so that the service layer (`AppSyncer`) needs no platform-specific logic.

## Error Handling

- **404 responses:** Indicate the app is not available on this storefront. The connector returns a failure with an `empty_response` error. The syncer treats this as a permanent "not in this country" signal — the pipeline writes `app_metrics.is_available = false` for the country and does not retry forever. The Android scraper raises an explicit `AppNotFoundError` that the FastAPI exception handler converts to 404.
- **Timeout:** Configurable per connector (default 30 seconds). Jobs retry with exponential backoff.
- **Rate limiting:** Handled at the job level via Redis throttle, not in the connector.
