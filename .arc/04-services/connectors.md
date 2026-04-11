# Connectors

## Overview

Connectors are HTTP clients in the backend that fetch data from scraper microservices. Each connector implements `ConnectorInterface` and returns a `ConnectorResult` DTO.

**Important:** Connectors do NOT scrape stores directly. They call scraper APIs via HTTP.

## Architecture

```
Backend Connector → HTTP → Scraper API → Store (web scraping)

ITunesLookupConnector  → http://scraper-appstore:7462
GooglePlayConnector    → http://scraper-gplay:7463
```

## Location

`backend/app/Connectors/`

## Interface

```php
interface ConnectorInterface
{
    public function supports(string $platform): bool;
    public function fetchIdentity(App $app): ConnectorResult;
    public function fetchListings(App $app): ConnectorResult;
    public function fetchLocalizedListings(App $app): array;
    public function fetchMetrics(App $app): ConnectorResult;
    public function fetchReviews(App $app, string $country = 'us'): ConnectorResult;
    public function fetchDeveloperApps(string $developerExternalId): ConnectorResult;
    public function getRateLimitPerMinute(): int;
    public function getSourceName(): string;
}
```

## ConnectorResult DTO

```php
class ConnectorResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data = [],
        public readonly ?string $error = null,
        public readonly ?int $statusCode = null,
    ) {}

    public static function success(array $data): self;
    public static function failure(string $error, ?int $statusCode = null): self;
}
```

## HTTP Helper Pattern

Both connectors use a private `get()` method:

```php
private function get(string $path, array $query = []): array
{
    $baseUrl = config('dna.connectors.gplay.base_url');
    $timeout = config('dna.connectors.gplay.timeout', 30);

    $response = Http::timeout($timeout)->get("{$baseUrl}{$path}", $query);

    if ($response->failed()) {
        throw new \RuntimeException("Scraper API request failed: {$response->status()}");
    }

    return $response->json();
}
```

## Configuration

`backend/config/dna.php`:

```php
'connectors' => [
    'appstore' => [
        'base_url' => env('APPSTORE_API_URL', 'http://scraper-appstore:7462'),
        'rate_limit_per_minute' => 20,
        'timeout' => 30,
    ],
    'gplay' => [
        'base_url' => env('GPLAY_API_URL', 'http://scraper-gplay:7463'),
        'rate_limit_per_minute' => 10,
        'timeout' => 30,
    ],
],
```

## Locale Normalization

Google Play connector normalizes locales to `xx_XX` format:

```php
private const LOCALE_NORMALIZE = [
    'en' => 'en_US', 'tr' => 'tr_TR', 'de' => 'de_DE', ...
];
```

All listings must be saved with normalized locales. Backend always queries `where('locale', 'en_US')`.

## Rules

- Connectors call scraper APIs via HTTP — never scrape stores directly
- Always return `ConnectorResult` (success or failure)
- Use `config('dna.connectors...')` for URLs, never hardcode
- Normalize all locales to `xx_XX` format
- Map scraper response fields to backend's expected format in `mapListingData()`
- No caching in connectors
