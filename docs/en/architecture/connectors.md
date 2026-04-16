# Connectors

Connectors are the server's interface to scraper microservices. They abstract HTTP communication, normalize response formats, and provide a unified API for both platforms.

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
    public function fetchListings(App $app, string $country = 'us', ?string $language = null): ConnectorResult;
    public function fetchMetrics(App $app, string $country = 'us'): ConnectorResult;
    public function fetchReviews(App $app, string $country = 'us', int $page = 1): ConnectorResult;
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

- `ConnectorResult::success($data)` — Successful response with normalized data
- `ConnectorResult::failure($error, $statusCode)` — Failed response with error details

## ITunesLookupConnector

Communicates with the App Store scraper microservice (`scraper-ios`).

| Method | Scraper Endpoint | Key Response Fields |
|--------|------------------|---------------------|
| `fetchIdentity` | `GET /apps/:appId/identity` | name, publisher, category, locales, release_date, is_free |
| `fetchListings` | `GET /apps/:appId/listings` | title, subtitle, description, whats_new, screenshots, icon, price |
| `fetchMetrics` | `GET /apps/:appId/metrics` | rating, rating_count, rating_breakdown, file_size |
| `fetchReviews` | `GET /apps/:appId/reviews` | reviews[], rating_breakdown |
| `fetchDeveloperApps` | `GET /developers/:id/apps` | apps[] with basic details |
| `fetchSearch` | `GET /apps/search` | results[] with app_id, name, developer |
| `fetchChart` | `GET /charts` | ranked results (up to 200 apps) |

**Config:** `appstorecat.connectors.appstore.base_url`, `appstorecat.connectors.appstore.timeout`

## GooglePlayConnector

Communicates with the Google Play scraper microservice (`scraper-android`).

| Method | Scraper Endpoint | Key Response Fields |
|--------|------------------|---------------------|
| `fetchIdentity` | `GET /apps/{app_id}/identity` | Same as iTunes connector |
| `fetchListings` | `GET /apps/{app_id}/listings` | Same (uses `locale` param instead of `lang`) |
| `fetchMetrics` | `GET /apps/{app_id}/metrics` | Same (no file_size_bytes) |
| `fetchReviews` | `GET /apps/{app_id}/reviews` | Same |
| `fetchDeveloperApps` | `GET /developers/{id}/apps` | Same |
| `fetchSearch` | `GET /apps/search` | Same |
| `fetchChart` | `GET /charts` | Same (default category: APPLICATION, max 100) |

**Config:** `appstorecat.connectors.gplay.base_url`, `appstorecat.connectors.gplay.timeout`

## Data Normalization

Both connectors normalize store-specific response formats into a consistent structure. For example:

- **Screenshots:** Both return `string[]` of URLs
- **Rating breakdown:** Both return `{1: count, 2: count, ..., 5: count}`
- **Publisher:** Both return `publisher_name`, `publisher_external_id`, `publisher_url`
- **Identity:** Both return the same fields (some may be null based on platform)

This normalization happens in the connector layer, so the service layer (`AppSyncer`) doesn't need platform-specific logic.

## Error Handling

- **404 responses:** Indicate the app is no longer available. The connector returns a failure, and the syncer marks the app as `is_available = false`.
- **Timeouts:** Configurable per connector (default 30s). Jobs retry with exponential backoff.
- **Rate limiting:** Handled at the job level via Redis throttle, not in the connector.
