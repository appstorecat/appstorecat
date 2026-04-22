# Senkronizasyon Pipeline'i

Senkronizasyon pipeline'i temel veri toplama motorudur. `AppSyncer` tarafindan yonetilir ve her uygulama icin tam veri yasam dongusunu orkestra eder. Pipeline calismalarinin durumu `sync_statuses` tablosunda saklanir (status, current_step, progress_done/total, failed_items, error_message, job_id, next_retry_at).

## Pipeline Akisi

```
SyncAppJob
    │
    ▼
AppSyncer::syncAll(App)
    │
    ├─ 1. identity()         → Uygulama metadata'si (ad, yayinci, kategori, diller)
    │       ├─ findOrCreate Publisher
    │       ├─ findOrCreate StoreCategory
    │       └─ Basarisiz olursa pipeline durur
    │
    ├─ 2. listings()         → Aktif ulke + locale basina StoreListing + StoreListingChange
    │       └─ detectChanges() checksum karsilastirir
    │
    ├─ 3. metrics()          → AppMetric (ulke + gun basina; Android `zz` sentinel'i altinda)
    │       └─ rating_delta hesapla, `is_available` ayarla
    │
    ├─ 4. finalize()         → `apps.last_synced_at`, ozet alanlar, `unavailable_countries`
    │
    └─ 5. reconciling()      → ReconcileFailedItemsJob gecici hatalari yeniden dener
```

Anahtar kelime yogunlugu bir pipeline adimi **degildir** — `KeywordAnalyzer` keyword ucundan talep uzerine cagrilir ve mevcut `StoreListing`'den okur. Bkz. [Anahtar Kelime Yogunlugu](../features/keyword-density.md) ozellik sayfasi.

## Faz Detaylari

### 1. Identity

Uygulamanin temel metadata'sini magazadan getirir.

- Ilk olarak `us` ulkesini dener, basarisiz olursa `apps.origin_country_code`'a doner
- 404 durumunda: `empty_response` olarak siniflandirir; hic bir storefront kimligi cozumlenemezse pipeline bu calismayi durdurur (sonraki fazlar tanimsiz bir uygulamaya calismasin diye)
- Gunceller: `display_name`, `icon_url`, `supported_locales`, `original_release_date`, `is_free`, `origin_country_code`
- `Publisher` ve `StoreCategory` kayitlarini olusturur veya baglar

### 2. Listings

Her aktif ulke icin o ulkede desteklenen her locale'de magaza listesini getirir.

- `StoreListing` kaydi olusturur (`(app_id, version_id, locale)` benzersiz)
- `title`, `subtitle`, `promotional_text` (iOS-only), `description`, `whats_new`, `screenshots`, `icon_url` alanlarini yazar
- Liste iceriginden bir `checksum` olusturur
- Checksum oncekinden farkliysa her alani karsilastirir ve `StoreListingChange` kayitlari olusturur — degisiklik algilama yalnizca onceki liste *farkli bir* `app_version` ile iliskiliyse tetiklenir; ayni surum icinde yapilan upsert'ler (ornegin ayni pass'te iki defa yakalanan bir locale) sessizce atlanir
- `supported_locales` karsilastirmasinda eklenen/kaldirilan locale'ler `locale_added` / `locale_removed` olarak isaretlenir
- Storefront locale'i dondurmezse hicbir kayit yazilmaz

### 3. Metrics

Ulke basina puanlari ve fiyati getirir.

- `AppMetric` kaydi olusturur (`(app_id, country_code, date)` benzersiz)
- Android metrikleri global oldugundan `zz` sentinel ulkesi altinda depolanir
- Depolar: `rating`, `rating_count`, `rating_breakdown`, `price` (null = bilinmiyor, 0 = ucretsiz), `installs_range`, `file_size_bytes`, `is_available`
- `rating_delta` hesaplar (onceki gunden bu yana rating_count degisimi)
- 404 bir ulke icin gelirse → `empty_response` olarak isaretlenir ve o ulke icin `is_available = false` yazilir, bir daha denemeye sokulmaz

### 4. Finalize

- `apps.last_synced_at` degerini guncelle
- Ozet alanlarini ve onbellekleri yenile
- `AppDetailResource`'un `unavailable_countries` alani `app_metrics.is_available = false` degerleri uzerinden turetilir
- `apps.is_available` alani en az bir storefront'ta erisilebilirligi temsil eder; ulke bazinda dogruluk kaynagi `app_metrics`'tir

### 5. Reconciling

- Onceki fazlarin bu calismaya yazdigi `failed_items` girislerini inceler
- `ReconcileFailedItemsJob`, neden etiketi basina yapilandirilmis maksimum yeniden deneme sayisina uyarak `next_retry_at` zamaninda onlari siraya sokar
- `empty_response` gibi kalici reason'lar atlanir — sonsuz yeniden deneme yok

## Senkronizasyon Zamanlamasi

Laravel zamanlayicisi her iki platformda da `appstorecat:apps:sync-tracked` komutunu her **20 dakikada** bir tetikler ve her platform icin `SYNC_{PLATFORM}_TRACKED_BATCH_SIZE` (varsayilan 5) uygulamayi `sync-tracked-{platform}` kuyruguna gonderir. Komut, yeterli eskiyen takip edilen uygulama bulamadiginda katmanli geri dusus uygular: once takip edilen uygulamalar, sonra henuz takip edilmeyen rakip uygulamalar, en son birikmis havuzdan en eski senkronize edilmis uygulamalar.

| Uygulama Tipi | Yenileme Araligi | Kuyruk |
|---------------|------------------|--------|
| Takip Edilen iOS | 24 saat | `sync-tracked-ios` |
| Takip Edilen Android | 24 saat | `sync-tracked-android` |
| Rakip / Birikmis iOS | 24 saat | `sync-tracked-ios` |
| Rakip / Birikmis Android | 24 saat | `sync-tracked-android` |

Uygulamalar yalnizca `last_synced_at` degeri yapilandirilmis yenileme araligindan eskiyse yeniden senkronize edilir.

### Talep Uzerine Yenileme Kuyrugu

`AppController::show()` ve `AppController::listing()`, ziyaret edilen bir uygulamanin verisi eskiyse `SyncAppJob`'u `sync-on-demand-ios` / `sync-on-demand-android` kuyruguna gonderir. Arayuz ilerlemeyi `GET /apps/{platform}/{externalId}/sync-status` ile sorgular; kullanici `POST /apps/{platform}/{externalId}/sync` uzerinden de aciktan tetikleyebilir. Bu, kullanici tetikli yenilemelerin kendi worker havuzunda calismasini ve olagan takip kuyruklarini beklememesini saglar.

## Benzersizlik Korumalari

Pipeline, tekrar veriyi onlemek icin veritabani benzersizlik kisitlamalarini kullanir:

| Tablo | Benzersizlik Kriteri |
|-------|---------------------|
| `apps` | `(platform, external_id)` |
| `app_store_listings` | `(app_id, version_id, locale)` |
| `app_versions` | `(app_id, version)` |
| `app_metrics` | `(app_id, country_code, date)` |
| `sync_statuses` | `app_id` |

Ek olarak, `SyncAppJob` uygulama ID'si basina 1 saatlik pencere ile `ShouldBeUnique` uygular.

## Hata Yonetimi

- **Identity basarisiz:** Pipeline durur, `sync_statuses.status = failed` yazilir. Sonraki fazlar calistirilmaz.
- **404 `empty_response`:** Ilgili ulke/locale kalici olarak "mevcut degil" olarak isaretlenir; yeniden denenmez.
- **Gecici hatalar (5xx, zaman asimi):** `failed_items` icine reason etiketi ile yazilir; `ReconcileFailedItemsJob` neden basina maksimum deneme kuralina gore yeniden dener.
- **Job seviyesinde yeniden deneme:** `[30, 60, 120]` saniye geri cekilme ile 3 deneme.
- **Basarisiz job'lar:** Tum denemelerden sonra, job'lar inceleme icin `failed_jobs` tablosuna gider.
- **Throttle asildi:** Job bir slot icin bekler (en fazla 300 saniye).
