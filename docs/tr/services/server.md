# Backend Servisi

Laravel API server, AppStoreCat'in merkezi servisidir. API gecidi olarak gorev yapar, veritabanina sahiptir, arka plan gorevlerini yonetir ve scraper mikroservisleriyle tum iletisimi orkestra eder.

## Teknoloji Yigini

| Bilesen | Teknoloji |
|---------|-----------|
| Framework | Laravel 13, PHP 8.4 |
| Veritabani | MySQL 8.4 |
| Kimlik Dogrulama | Laravel Sanctum (token tabanli) |
| API Dokumantasyonu | L5-Swagger (OpenAPI) |
| Kuyruk | Redis (gelistirme) / Database (production) |
| Onbellek | Redis (gelistirme) / File (production) |
| Kod Stili | Laravel Pint |
| Testler | Pest (PHPUnit) |

## Dizin Yapisi

```
server/
├── app/
│   ├── Connectors/          # Magaza API entegrasyonlari
│   │   ├── ConnectorInterface.php
│   │   ├── ConnectorResult.php
│   │   ├── ITunesLookupConnector.php
│   │   └── GooglePlayConnector.php
│   ├── Enums/               # Platform, SyncPhase, vb.
│   ├── Http/
│   │   └── Controllers/Api/V1/
│   │       ├── Account/     # Kimlik Dogrulama, Profil, Guvenlik
│   │       └── App/         # Uygulama, Arama, Rakip, Anahtar Kelime
│   ├── Jobs/
│   │   ├── Chart/           # Grafik senkronizasyon gorevleri
│   │   └── Sync/            # Uygulama senkronizasyon gorevleri + reconciliation
│   ├── Models/              # Eloquent modelleri (SyncStatus dahil)
│   ├── Rules/               # AppAvailableCountry gibi form dogrulama kurallari
│   └── Services/            # Is mantigi
│       ├── AppRegistrar.php
│       ├── AppSyncer.php
│       └── KeywordAnalyzer.php
├── config/
│   └── appstorecat.php      # Merkezi yapilandirma
├── database/
│   └── migrations/          # Tum tablo tanimlari (sync_statuses dahil)
├── resources/
│   └── data/stopwords/      # 50 dilde durak kelime sozlukleri
├── routes/
│   └── api.php              # Tum API rotalari
└── tests/                   # Pest testleri
```

## Temel Sorumluluklar

### API Gecidi
Tum web istekleri server uzerinden gecer. Backend, kullanicilari dogrular (Sanctum), istekleri dogrular (Form Request'ler) ve formatlanmis yanitlar dondurur (API Resource'lari).

Onemli rota davranislari:

- `POST /apps` — uygulamanin DB'de zaten var olmasini sart kosar, yoksa 422 dondurur (rastgele ID kaydini engeller).
- `POST /publishers/{p}/{id}/import` — `external_ids[*]` icindeki her kimligin var olmasini dogrular.
- `GET /publishers/{p}/{id}` ve `/store-apps` — bilinmeyen kayitlar icin 404 doner.
- `/apps/{p}/{id}/listing` — `country_code` + `locale` kabul eder; `AppAvailableCountry` kurali uygulama o ulkede mevcut degilse 422 dondurur.
- `/charts`, `/apps/search`, `/publishers/search` — `country_code` parametresi alir.
- `/countries` — dahili `zz` sentinelini listeden filtreler.
- `GET /apps/{p}/{id}/sync-status` ve `POST /apps/{p}/{id}/sync` — senkronizasyon durumu ve UI'dan tetiklenen tazeleme.
- `DirectVisit` varsayilan olarak kapalidir.

### Veritabani Sahibi

Backend, MySQL veritabaninin tek sahibidir. Baska hicbir servis veritabanina dogrudan erismez.

Sema notlari:

- `apps.origin_country_code` `char(2)` tipindedir ve `countries.code`'a FK verir.
- Uygulama ikonu `apps.icon_url` sutununda tutulur.
- `app_metrics.country_code` `char(2)` olup `countries.code`'a FK verir; `price` nullable'dir; `is_available` ulke basina yetkili kaynaktir.
- `app_store_listings` icinde `locale` sutunu kullanilir; iOS listelemeleri `promotional_text` sutunu icerir.
- `trending_charts.country_code` ulke basina grafikleri tutar.
- `sync_statuses` tablosu pipeline durumunu izler.

`apps.is_available` "en az bir storefront'ta erisilebilir" anlamina gelir; ulke bazinda yetkili deger `app_metrics.is_available`'dir.

### Senkronizasyon Pipeline'i

Senkronizasyon `SyncStatus` modeliyle izlenen fazli bir pipeline'dir:

1. **identity** — Kimlik getirilir; bu faz basarisiz olursa pipeline iptal edilir.
2. **listings** — Ulke/locale basina mağaza listelemeleri.
3. **metrics** — Ulke basina metrikler (Android `zz` sentineli ile global olarak saklanir).
4. **finalize** — Farklari uygular, `apps.is_available` ve `unavailable_countries` yeniden hesaplanir.
5. **reconciling** — `ReconcileFailedItemsJob`, `sync_statuses.failed_items` uzerinde tekrar calisir.

Scraper'dan gelen **404**, "bu storefront'ta kalici olarak mevcut degil" olarak yorumlanir ve ilgili `app_metrics.is_available = false` olarak isaretlenir; 5xx'lar yeniden denenir.

### Gorev Orkestrasyonu

Laravel zamanlayicisi senkronizasyon ve grafik gorevlerini gonderir. Tum senkronizasyon/chart kuyruklari **platforma ayrilmistir**, boylece iOS ve Android birbirini bloklamaz:

| Kuyruk |
|--------|
| `sync-discovery-ios`, `sync-discovery-android` |
| `sync-tracked-ios`, `sync-tracked-android` |
| `sync-on-demand-ios`, `sync-on-demand-android` |
| `charts-ios`, `charts-android` |

### Connector Katmani

Connector'lar, scraper mikroservisleriyle HTTP iletisimini soyutlar ve platformlar arasi yanit formatlarini normallestir. Scraper'dan gelen 404, kalici "mevcut degil" olarak; diger hatalar ise yeniden denenebilir olarak modellenir.

## Calistirma

```bash
make dev-server    # Backend + MySQL + Redis'i baslat
make logs-server   # Backend loglarini goruntule
make pint          # Kod stili duzelticiyi calistir
make test-server   # Pest testlerini calistir
```

## API Dokumantasyonu

`L5_SWAGGER_GENERATE_ALWAYS=true` oldugunda Swagger UI `/api/documentation` adresinde kullanilabilir.

Tam referans icin [API Endpoint'leri](../api/endpoints.md) sayfasina bakin.
