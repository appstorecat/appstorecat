# Connector'lar

Connector'lar, backend'in scraper mikroservisleriyle iletisim arayuzudur. HTTP iletisimini soyutlar, yanit formatlarini normalize eder ve her iki platform icin birlesik bir API saglar.

## Mimari

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
scraper-appstore :7462   scraper-gplay :7463
```

## ConnectorInterface

Tum connector'lar ayni arayuzu uygular:

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

Her connector metodu bir `ConnectorResult` dondurur:

```php
class ConnectorResult
{
    public readonly bool $success;
    public readonly array $data;
    public readonly ?string $error;
    public readonly ?int $statusCode;
}
```

- `ConnectorResult::success($data)` — Normalize edilmis veri iceren basarili yanit
- `ConnectorResult::failure($error, $statusCode)` — Hata detaylari iceren basarisiz yanit

## ITunesLookupConnector

App Store scraper mikroservisi (`scraper-appstore`) ile iletisim kurar.

| Metot | Scraper Endpoint'i | Temel Yanit Alanlari |
|-------|-------------------|---------------------|
| `fetchIdentity` | `GET /apps/:appId/identity` | name, publisher, category, locales, release_date, is_free |
| `fetchListings` | `GET /apps/:appId/listings` | title, subtitle, description, whats_new, screenshots, icon, price |
| `fetchMetrics` | `GET /apps/:appId/metrics` | rating, rating_count, rating_breakdown, file_size |
| `fetchReviews` | `GET /apps/:appId/reviews` | reviews[], rating_breakdown |
| `fetchDeveloperApps` | `GET /developers/:id/apps` | apps[] with basic details |
| `fetchSearch` | `GET /apps/search` | results[] with app_id, name, developer |
| `fetchChart` | `GET /charts` | ranked results (up to 200 apps) |

**Yapilandirma:** `appstorecat.connectors.appstore.base_url`, `appstorecat.connectors.appstore.timeout`

## GooglePlayConnector

Google Play scraper mikroservisi (`scraper-gplay`) ile iletisim kurar.

| Metot | Scraper Endpoint'i | Temel Yanit Alanlari |
|-------|-------------------|---------------------|
| `fetchIdentity` | `GET /apps/{app_id}/identity` | iTunes connector ile ayni |
| `fetchListings` | `GET /apps/{app_id}/listings` | Ayni (`lang` yerine `locale` parametresi kullanir) |
| `fetchMetrics` | `GET /apps/{app_id}/metrics` | Ayni (file_size_bytes yok) |
| `fetchReviews` | `GET /apps/{app_id}/reviews` | Ayni |
| `fetchDeveloperApps` | `GET /developers/{id}/apps` | Ayni |
| `fetchSearch` | `GET /apps/search` | Ayni |
| `fetchChart` | `GET /charts` | Ayni (varsayilan kategori: APPLICATION, maksimum 100) |

**Yapilandirma:** `appstorecat.connectors.gplay.base_url`, `appstorecat.connectors.gplay.timeout`

## Veri Normalizasyonu

Her iki connector da magazaya ozgu yanit formatlarini tutarli bir yapiya normalize eder. Ornegin:

- **Ekran goruntuleri:** Her ikisi de `string[]` URL dizisi dondurur
- **Puan dagilimi:** Her ikisi de `{1: sayi, 2: sayi, ..., 5: sayi}` dondurur
- **Yayinci:** Her ikisi de `publisher_name`, `publisher_external_id`, `publisher_url` dondurur
- **Kimlik:** Her ikisi de ayni alanlari dondurur (bazilari platforma gore null olabilir)

Bu normalizasyon connector katmaninda gerceklesir, boylece servis katmani (`AppSyncer`) platforma ozgu mantik gerektirmez.

## Hata Yonetimi

- **404 yanitlari:** Uygulamanin artik mevcut olmadigini gosterir. Connector basarisizlik dondurur ve syncer uygulamayi `is_available = false` olarak isaretler.
- **Zaman asimi:** Connector basina yapilandirilabiir (varsayilan 30 saniye). Job'lar ustel geri cekilme ile yeniden dener.
- **Hiz sinirlandirma:** Connector'da degil, job seviyesinde Redis throttle ile yonetilir.
