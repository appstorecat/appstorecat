# Senkronizasyon Pipeline'i

Senkronizasyon pipeline'i temel veri toplama motorudur. `AppSyncer` tarafindan yonetilir ve her uygulama icin tam veri yasam dongusunu orkestra eder.

## Pipeline Akisi

```
SyncAppJob
    │
    ▼
AppSyncer::syncAll(App)
    │
    ├─ 1. syncIdentity()      → Uygulama metadata'si (ad, yayinci, kategori, diller)
    │       └─ findOrCreate Publisher
    │       └─ findOrCreate StoreCategory
    │
    ├─ 2. saveVersion()        → AppVersion kaydi
    │
    ├─ 3. syncListing()        → Varsayilan dil icin StoreListing
    │       └─ detectChanges() → StoreListingChange kayitlari (checksum tabanli)
    │
    ├─ 4. detectLocaleChanges() → Eklenen/kaldirilan dilleri takip et
    │
    ├─ 5. syncMetrics()        → AppMetric (gunluk goruntusu)
    │       └─ rating_delta hesapla
    │
    ├─ 6. syncReviews()        → Review kayitlari (sayfalanmis)
    │
    └─ 7. updateVersionDetails() → Liste metni uzerinde KeywordAnalyzer
            └─ AppKeywordDensity kayitlari (1/2/3-gram)
```

## Adim Detaylari

### 1. Kimlik Senkronizasyonu

Uygulamanin temel metadata'sini magazadan getirir.

- Ilk olarak `us` ulkesini dener, basarisiz olursa `app.origin_country`'ye doner
- 404 durumunda: uygulamayi `is_available = false` olarak isaretler ve durur
- Gunceller: `display_name`, `display_icon`, `supported_locales`, `original_release_date`, `is_free`
- `Publisher` ve `StoreCategory` kayitlarini olusturur veya baglar

### 2. Surum Kaydi

Mevcut surum kaydini olusturur veya bulur.

- `(app_id, version)` ile `firstOrCreate` kullanir — tekrar olmaz
- Kimlik verisinden `release_date` ayarlar
- Sonraki adimlarda kullanilmak uzere surumu dondurur

### 3. Liste Senkronizasyonu

Varsayilan dil icin magaza listesini getirir.

- `StoreListing` kaydi olusturur (`app_id` + `language` bazinda benzersiz)
- Liste iceriginden bir `checksum` olusturur
- Checksum oncekinden farkliysa: her alani tek tek karsilastirir
- Degisen alanlar icin `StoreListingChange` kayitlari olusturur:
  - `title`, `subtitle`, `description`, `whats_new`, `screenshots`

### 4. Dil Degisiklik Tespiti

Mevcut `supported_locales` degerini onceki senkronizasyonla karsilastirir.

- Yeni eklenen dilleri tespit eder → `field_changed: locale_added` ile `StoreListingChange` olusturur
- Kaldirilan dilleri tespit eder → `field_changed: locale_removed` ile `StoreListingChange` olusturur

### 5. Metrik Senkronizasyonu

Guncel puanlari ve metrikleri getirir.

- `AppMetric` kaydi olusturur (`app_id` + `date` bazinda benzersiz)
- Depolar: `rating`, `rating_count`, `rating_breakdown`, `file_size_bytes`
- `rating_delta` hesaplar (onceki gunden bu yana rating_count degisimi)

### 6. Inceleme Senkronizasyonu

Magazadan kullanici incelemelerini getirir.

- Sayfalanmis: sayfa basina 200'e kadar inceleme
- `Review` kayitlari olusturur (`app_id` + `external_id` bazinda benzersiz)
- Yakalar: author, title, body, rating, review_date, app_version

### 7. Anahtar Kelime Analizi

Liste metnini anahtar kelime yogunlugu acisindan analiz eder.

- Birlestir: title + subtitle + description + whats_new
- Dil duyarli stop kelime filtrelemesi ile tokenize eder (50 dil)
- N-gram cikarir: 1 kelimelik, 2 kelimelik ve 3 kelimelik kombinasyonlar
- Frekans ve yogunluk yuzdesi hesaplar
- `AppKeywordDensity` kayitlarini depolar

## Senkronizasyon Zamanlamasi

Laravel zamanlayicisi `last_synced_at` zaman damgasina gore senkronizasyon job'larini gonderir:

| Uygulama Tipi | Yenileme Araligi | Kuyruk |
|---------------|------------------|--------|
| Takip Edilen iOS | 24 saat | `sync-tracked-ios` |
| Takip Edilen Android | 24 saat | `sync-tracked-android` |
| Kesfedilen iOS | 72 saat | `sync-discovery-ios` |
| Kesfedilen Android | 72 saat | `sync-discovery-android` |

Uygulamalar yalnizca `last_synced_at` degeri yapilandirilmis yenileme araligindan eskiyse yeniden senkronize edilir.

## Benzersizlik Korumalari

Pipeline, tekrar veriyi onlemek icin veritabani benzersizlik kisitlamalarini kullanir:

| Tablo | Benzersizlik Kriteri |
|-------|---------------------|
| `apps` | `(platform, external_id)` |
| `app_store_listings` | `(app_id, language)` |
| `app_versions` | `(app_id, version)` |
| `app_metrics` | `(app_id, date)` |
| `app_reviews` | `(app_id, external_id)` |

Ek olarak, `SyncAppJob` uygulama ID'si basina 1 saatlik pencere ile `ShouldBeUnique` uygular.

## Hata Yonetimi

- **404 (Uygulama kaldirilmis):** `is_available = false` olarak isaretler, senkronizasyonu durdurur
- **Ag/zaman asimi hatalari:** Job `[30, 60, 120]` saniye geri cekilme ile yeniden dener (3 deneme)
- **Basarisiz job'lar:** Tum denemelerden sonra, job'lar inceleme icin `failed_jobs` tablosuna gider
- **Throttle asildi:** Job bir slot icin bekler (en fazla 300 saniye)
