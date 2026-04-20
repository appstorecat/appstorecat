# Connector'lar

Connector'lar, server'in scraper mikroservisleriyle iletisim arayuzudur. HTTP iletisimini soyutlar, yanit formatlarini normalize eder ve her iki platform icin birlesik bir API saglar.

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
scraper-ios :7462   scraper-android :7463
```

## ConnectorInterface

Tum connector'lar ayni arayuzu uygular:

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
- `ConnectorResult::failure($error, $statusCode)` — Hata detaylari iceren basarisiz yanit (404 durumunda `empty_response` nedeniyle)

## ITunesLookupConnector

App Store scraper mikroservisi (`scraper-ios`) ile iletisim kurar.

| Metot | Scraper Endpoint'i | Temel Yanit Alanlari |
|-------|-------------------|---------------------|
| `fetchIdentity` | `GET /apps/:appId/identity` | name, publisher, category, locales, release_date, is_free |
| `fetchListings` | `GET /apps/:appId/listings` | title, subtitle, promotional_text, description, whats_new, screenshots, icon, price |
| `fetchMetrics` | `GET /apps/:appId/metrics` | rating, rating_count, rating_breakdown, file_size |
| `fetchDeveloperApps` | `GET /developers/:id/apps` | apps[] with basic details |
| `fetchSearch` | `GET /apps/search` | results[] with app_id, name, developer |
| `fetchChart` | `GET /charts` | ranked results (up to 200 apps) |

> iOS kimlik yaniti ucretsiz/ucretli bilgisini `is_free` boolean'i olarak iletir. Liste payload'i `promotional_text` alanini icerir.

**Yapilandirma:** `appstorecat.connectors.appstore.base_url`, `appstorecat.connectors.appstore.timeout`

## GooglePlayConnector

Google Play scraper mikroservisi (`scraper-android`) ile iletisim kurar.

| Metot | Scraper Endpoint'i | Temel Yanit Alanlari |
|-------|-------------------|---------------------|
| `fetchIdentity` | `GET /apps/{app_id}/identity` | iTunes connector ile ayni |
| `fetchListings` | `GET /apps/{app_id}/listings` | Ayni (`lang` yerine `locale` parametresi kullanir; `promotional_text` her zaman `null`) |
| `fetchMetrics` | `GET /apps/{app_id}/metrics` | Ayni (file_size_bytes yok, puan Play icin global) |
| `fetchDeveloperApps` | `GET /developers/{id}/apps` | Ayni |
| `fetchSearch` | `GET /apps/search` | Ayni |
| `fetchChart` | `GET /charts` | Ayni (varsayilan kategori: APPLICATION, maksimum 100) |

> Android metrikleri ulke ayrimina sahip olmadigi icin `AppSyncer` bunlari `app_metrics` tablosuna `zz` "Global" sentinel ulkesi altinda yazar.

**Yapilandirma:** `appstorecat.connectors.gplay.base_url`, `appstorecat.connectors.gplay.timeout`

## Veri Normalizasyonu

Her iki connector da magazaya ozgu yanit formatlarini tutarli bir yapiya normalize eder. Ornegin:

- **Ekran goruntuleri:** Her ikisi de `string[]` URL dizisi dondurur
- **Puan dagilimi:** Her ikisi de `{1: sayi, 2: sayi, ..., 5: sayi}` dondurur
- **Yayinci:** Her ikisi de `publisher_name`, `publisher_external_id`, `publisher_url` dondurur
- **Kimlik:** Her ikisi de ayni alanlari dondurur (bazilari platforma gore null olabilir)

Bu normalizasyon connector katmaninda gerceklesir, boylece servis katmani (`AppSyncer`) platforma ozgu mantik gerektirmez.

## Hata Yonetimi

- **404 yanitlari:** Uygulamanin bu storefront'ta mevcut olmadigini gosterir. Connector `empty_response` hatasiyla basarisizlik dondurur. Syncer bunu kalici bir "bu ulkede yok" sinyali olarak isler — pipeline ilgili ulke icin `app_metrics.is_available = false` yazar ve sonsuza kadar yeniden denenmez. Android scraper'i, FastAPI istisna isleyicisi tarafindan 404'e cevrilen acik bir `AppNotFoundError` yayar.
- **Zaman asimi:** Connector basina yapilandirilabilir (varsayilan 30 saniye). Job'lar ustel geri cekilme ile yeniden dener.
- **Hiz sinirlandirma:** Connector'da degil, job seviyesinde Redis throttle ile yonetilir.
