# `app_store_listings` Audit

## Genel Bakış

`app_store_listings`, bir uygulamanın store sayfasının **per-locale snapshot**'larını tutar. Her satır `(app_id, version_id, locale)` üçlüsüne özgüdür ve scraper'ın o locale için yakaladığı içerik alanlarını (`title`, `subtitle`, `description`, `promotional_text`, `whats_new`, `icon_url`, `screenshots`, `video_url`, `price`, `currency`) barındırır.

Diff tespiti için `checksum` sütunu kullanılır: `title + subtitle + description + whats_new` alanlarının MD5'i. Yeni sync turunda mevcut satırın checksum'ı yeni veri checksum'ı ile karşılaştırılır; fark varsa `app_store_listing_changes`'a alan-bazlı kayıt düşülür (bkz. `AppSyncer::detectChanges`).

Yazma her zaman `updateOrCreate(['app_id','version_id','locale'])` ile yapılır — yeni `version_id` geldiğinde yeni satır oluşur, aynı version için tekrar sync olduğunda satır güncellenir.

Migration: `/Users/ismail/Projects/opensource/appstorecat/server/database/migrations/2026_04_06_000003_create_app_store_listings_table.php`
Model: `/Users/ismail/Projects/opensource/appstorecat/server/app/Models/StoreListing.php`

## Şema

| Sütun | Tip | Null | Default | Açıklama |
|---|---|---|---|---|
| `id` | `bigint unsigned` (PK) | hayır | auto | Birincil anahtar. |
| `app_id` | `bigint unsigned` (FK) | hayır | — | `apps.id` → cascade on delete. |
| `version_id` | `bigint unsigned` (FK) | evet | `null` | `app_versions.id` → null on delete. Version tracking öncesi snapshot'larda `null`. |
| `locale` | `varchar(10)` | hayır | — | BCP-47 locale (örn. `en-US`, `tr`). |
| `title` | `varchar(255)` | hayır | — | Localized app title. |
| `subtitle` | `varchar(255)` | evet | `null` | iOS short tagline; Android'de veya boşsa `null`. |
| `description` | `text` | hayır | — | Tam localized açıklama. |
| `promotional_text` | `text` | evet | `null` | iOS promo text (yeni version'a gerek olmadan editlenebilir). |
| `whats_new` | `text` | evet | `null` | Per-locale release notes; gösterimde `app_versions.whats_new`'ı override eder. |
| `screenshots` | `json` | evet | `null` | Sıralı screenshot array'i (`{url, device_type, order}`). |
| `icon_url` | `text` | evet | `null` | Bu snapshot'taki icon URL (store localized icon'lara izin verir). |
| `video_url` | `text` | evet | `null` | App preview/promo video URL. |
| `price` | `decimal(10,2)` | hayır | `0.00` | Storefront currency'sinde fiyat; ücretsiz için `0.00`. |
| `currency` | `varchar(3)` | evet | `null` | ISO-4217 (örn. `USD`). |
| `fetched_at` | `timestamp` | hayır | — | Scraper'ın yakaladığı an; change detection penceresinde kullanılır. |
| `checksum` | `varchar(255)` | hayır | — | İçerik alanlarının MD5'i — diff tespiti. |
| `created_at`, `updated_at` | `timestamp` | evet | `null` | Laravel timestamps. |

## Index'ler & FK'lar

```
UNIQUE  app_store_listings_app_version_locale_unique (app_id, version_id, locale)
INDEX   app_store_listings_version_id_index (version_id)
INDEX   app_store_listings_app_id_locale_fetched_at_index (app_id, locale, fetched_at)
INDEX   app_store_listings_checksum_index (checksum)
FK      app_id    → apps.id           ON DELETE CASCADE
FK      version_id → app_versions.id  ON DELETE SET NULL
```

Notlar:
- Unique key `version_id` `null` değer alabildiği için, MySQL'de aynı `(app_id, locale)` için birden fazla `version_id IS NULL` satırı mümkündür (MySQL unique index'lerinde `NULL`'lar distinct kabul edilir).
- `version_id` ayrı index'i — version-only sorgular (örn. `detectLocaleChanges` içindeki `where('version_id', $v->id)`) için kullanılır.
- `(app_id, locale, fetched_at)` composite index — locale bazlı zaman serisi taraması için.
- `checksum` index'i — şu an okuyan bir sorgu **yok**; sadece yazıcı `saveListing` PHP-tarafında karşılaştırıyor (DB lookup yapılmıyor). Index ölü ağırlık (bkz. Gözlemler).

## Model

`App\Models\StoreListing` (`app_store_listings` tablosu).

Fillable:
```
app_id, version_id, locale, title, subtitle, description,
promotional_text, whats_new, screenshots, icon_url, video_url,
price, currency, fetched_at, checksum
```

Casts:
- `screenshots` → `array` (JSON decode/encode).
- `fetched_at` → `datetime` (Carbon).

İlişkiler:
- `app(): BelongsTo<App>`.
- `version(): BelongsTo<AppVersion, version_id>`.

Helper'lar:
- `screenshotUrls(): array` — `screenshots` JSON'unu `{url, device_type ?? 'phone', order ?? $i}` şekline normalize eder. **Fallback `device_type` `'phone'`** (Android konvansiyonu) — iOS scraper'ı `'iphone'` / `'ipad'` üretirken Android `'phone'` üretiyor (bkz. Gözlemler).
- `getDescriptionLengthAttribute(): int` — `mb_strlen($description)` accessor. `ListingResource`'da `description_length` olarak expose ediliyor (ASO için karakter sayımı).

OpenAPI schema `StoreListing`: `screenshots`'ı `{url, device_type, order}` array'i olarak deklare eder; client tipi `StoreListingScreenshotsItem`.

## Yazan Yerler

**1) `AppSyncer::saveListing(App $app, array $data, ?AppVersion $version): StoreListing`** — `server/app/Services/AppSyncer.php:244`

İş akışı:
1. Checksum'ı `md5(title.subtitle.description.whats_new)` olarak hesapla.
2. Aynı `(app_id, locale)` için **en yeni** satırı `orderByDesc('id')` ile çek (`$existing`).
3. **Field-diff koşulu** (`AppSyncer.php:263-271`):
   - `$existing` var,
   - `$version !== null`,
   - `$existing->version_id !== null`,
   - `$existing->version_id !== $version->id` (yani gerçekten **eski version**),
   - `$existing->checksum !== $checksum`,
   - hepsi sağlanırsa `detectChanges()` çağrılır.
   - Bu koşullar yorumda açıklandığı üzere "aynı version'ı sync iki kez çalıştırırsa phantom diff atma" amaçlıdır.
4. `updateOrCreate(['app_id','version_id','locale'], [...alanlar..., 'fetched_at' => now(), 'checksum' => $checksum])`.
5. Yan etki: `listing->icon_url` doluysa ve `app->icon_url` boşsa, `apps` tablosunu da günceller.

Çağrı yerleri:
- `AppSyncer::fetchAndSaveListing` (sync pipeline, Phase 2 listings — `iosLocaleMap` / `androidLocaleMap` üzerinden iterate).
- `AppSyncer::retryFailedItem` (reconcile job tarafından).
- `AppSyncer::syncListingForCountry` (ad-hoc tek-locale fetch; harici).

**2) `AppSyncer::detectChanges(App, StoreListing $existing, array $newData, ?AppVersion)`** — `AppSyncer.php:517`

`title, subtitle, description, whats_new` alanlarını tek tek karşılaştırır; her diff için `StoreListingChange::firstOrCreate(['app_id','version_id','locale','field_changed'], ['old_value','new_value','detected_at'])`. `screenshots`, `icon_url`, `promotional_text`, `price`, `currency` change-detection'a dahil **değil**.

**3) `AppSyncer::detectLocaleChanges(App, ?AppVersion $currentVersion)`** — `AppSyncer.php:446`

Sync sonrası finalize fazında çağrılır. Önceki version vs. mevcut version locale set'lerini karşılaştırır:
- `added` locales → `field_changed = 'locale_added'` (`new_value = listing.title`).
- `removed` locales → `field_changed = 'locale_removed'` (`old_value = previous listing.title`).

Bu fonksiyon `app_store_listings`'a yazmıyor; sadece okuyup `app_store_listing_changes`'a yazıyor.

**4) `AppSyncer::syncListingForCountry(App, string $countryCode, ?string $locale, ?AppVersion)`** — `AppSyncer.php:566`

Public helper: tek bir country/locale için connector'dan fetch edip `saveListing`'e yollar. Pipeline dışı tek-shot çağrılar için. Başarısız fetch'te `RuntimeException`.

## Okuyan Yerler

| Konum | Amaç | Sorgu şekli |
|---|---|---|
| `AppController::listing` (`AppController.php:154`) | Tek locale endpoint'i | `where(app_id, locale, version_id=latest)` → 1 satır veya 404 |
| `AppController::show` → `AppDetailResource` | App detail sayfası `listings` array'i | `App::with('storeListings')` (eager load, tüm locale'ler) |
| `AppDetailResource` (`AppDetailResource.php:71`) | API serialization | `$this->whenLoaded('storeListings')` → `ListingResource::collection` |
| `KeywordController::index` → `findListing` | Keyword density (n-gram) | `where(app_id, locale, version_id?)` → tek listing |
| `KeywordController::compare` | Çoklu app keyword karşılaştırma | Her app için `findListing` + ayrıca `storeListings()->orderByDesc('version_id')->value('icon_url')` |
| `KeywordAnalyzer::analyzeListing` (`KeywordAnalyzer.php:23`) | n-gram tokenize | `listing->title + subtitle + description + whats_new` |
| `ExplorerController::screenshots` | Screenshot grid | `whereHas('storeListings', locale LIKE 'en%' AND screenshots NOT NULL AND screenshots != '[]')` |
| `ExplorerController::icons` | Icon grid | `whereHas('storeListings', locale LIKE 'en%' AND icon_url NOT NULL)` |
| `AppSyncer::updateVersionDetails` (`AppSyncer.php:546`) | Version `whats_new`'ı doldurma | Default locale + `orderByDesc('fetched_at')->first()` |
| `App::storeListings()` (`App.php:107`) | HasMany ilişkisi | — |

Notlar:
- `ExplorerController` her iki feed'de de `orderByRaw("CASE WHEN locale = 'en-US' THEN 0 ELSE 1 END")` ile `en-US`'yi öncelikli, sonra herhangi bir `en*` locale'i fallback olarak alıyor → İngilizce storefront canonical preview.
- `AppController::listing` `version_id`'ı `latestVersion?->id` olarak kabul ediyor — `null` ise tüm `version_id IS NULL` satırları match olur (potansiyel sorun, bkz. Gözlemler).
- `screenshots != '[]'` filtresi: scraper boş array yazdığında (`$data['screenshots'] ?? []` fallback'i `saveListing`'de var) Explorer feed'i bu app'ı dışlar.

## API Yüzeyi

Route: `routes/api.php:44`
```
GET /api/v1/apps/{platform}/{externalId}/listing?country_code=tr&locale=tr
```
Controller: `AppController::listing` → `ListingResource`.

Davranış:
- `version_id = latestVersion?->id` ve `locale = request.locale` için satır bulunursa → 200 + `ListingResource`.
- Bulunamazsa: app stale ise (`isStale()` eşik: `config('appstorecat.sync.{platform}.tracked_app_refresh_hours', 24)`) sync job dispatch → **404** (`NotFoundHttpException: 'Listing not yet available for this locale — sync in progress.'`).

Ayrıca dolaylı:
- `GET /apps/{platform}/{externalId}` (`AppController::show`) — `storeListings` eager-load ederek tüm locale'leri `listings[]` array'inde döner.
- `GET /apps/{platform}/{externalId}/keywords` — keyword density, listing'den hesaplanır.
- `GET /apps/{platform}/{externalId}/keywords/compare` — çoklu listing.
- `GET /explorer/screenshots`, `GET /explorer/icons` — listing'e join.

`ListingResource` field çıktısı: `id, version_id, locale, title, subtitle, description, promotional_text, whats_new, icon_url, screenshots, video_url, price (float cast), currency, description_length, fetched_at`. **`checksum` API'ye sızmıyor** (sadece internal).

## Web

| Dosya | Rolü |
|---|---|
| `web/src/api/models/storeListing.ts` | TS interface `StoreListing` (Orval generated). |
| `web/src/api/models/storeListingScreenshotsItem.ts` | `{url, device_type, order}` (hepsi optional). |
| `web/src/api/models/listingResource.ts` | `ListingResource = StoreListing` (allOf alias). |
| `web/src/api/endpoints/apps/apps.ts` | `appListing(...)`, `useAppListing(...)`, `getAppListingQueryOptions(...)` — `/apps/{p}/{id}/listing` endpoint client'ı. |
| `web/src/pages/apps/Show.tsx:38,234,515,573` | `detail.listings ?? []` → `StoreListingTab listings={listings}`. |
| `web/src/components/tabs/StoreListingTab.tsx` | Locale seçici + screenshots galerisi. `currentListing.screenshots` üzerinden iterate. |

Show.tsx Tab, `detail.listings`'i (yani `AppDetailResource.listings` — eager-loaded `storeListings`) tüketiyor. **`/listing` endpoint'i** doğrudan tab tarafından kullanılmıyor — show endpoint'i zaten tüm locale'leri getiriyor. `useAppListing` hook'u generate edilmiş ama Show.tsx'te çağrılmıyor (per-locale lazy fetch için bir entry-point hazır ama bağlanmamış).

## Bağımlı Tablolar

`app_store_listing_changes` — Migration `2026_04_06_000004`. Field-bazlı change log:
- `field_changed` değerleri: `'title'`, `'subtitle'`, `'description'`, `'whats_new'` (içerik değişimi, `detectChanges`'ten), `'locale_added'`, `'locale_removed'` (locale-set değişimi, `detectLocaleChanges`'ten).
- `screenshots`, `icon_url`, `promotional_text`, `price`, `currency` için change kaydı **üretilmiyor**.
- `AppDetailResource.php:78` `screenshots` field_changed'ı için `old_value`/`new_value`'ı `null`'a maskeliyor — eski bir field_changed değeri için defansif kod (geçmiş migration'da yaratılmış olabilir veya gelecekteki bir genişleme için yer tutucu).

## Gözlemler & Kokular

1. **`screenshots.device_type` platform farkı.** iOS scraper `'iphone'` ve `'ipad'` üretiyor (`scraper-ios/src/scraper.ts:103,122,200`); Android scraper `'phone'` üretiyor (`scraper-android/src/scraper.py:107`). Model'in `screenshotUrls()` fallback'i `'phone'`. Web tarafı (`StoreListingTab.tsx`) device_type'ı **kullanmıyor** — sadece `screenshot.url` render ediyor. Yani değer kaydediliyor ama tüketilmiyor; ileride iPhone/iPad split UI'ı isterse hazır, fakat şu an dead-field.

2. **Aynı `(app_id, version_id, locale)` için checksum farkı = UPSERT, diff log YOK.** `saveListing`'in field-diff koşulu (`AppSyncer.php:263-271`) **eski version_id ≠ yeni version_id** şartını arar. Yani aynı version'da scraper içerik fark ederse satır `updateOrCreate` ile **yerinde güncellenir**, fakat `app_store_listing_changes`'a kayıt **atılmaz**. Bu kasıtlı (phantom diff koruması) ama gerçek "version bump olmadan publisher description'ı güncelledi" senaryosunu da kaçırıyor → iOS promotional_text gibi version'a bağlı olmayan değişiklikler tamamen görünmez. Eski satır ise `checksum` farkı + yeni `fetched_at` ile yerine yazılıyor — geçmiş kayıt korunmuyor.

3. **`version_id` null'lar ve unique behavior.** `AppController::listing` `latestVersion?->id` ile sorgu yapıyor; eğer app'ın hiç version'ı yoksa `version_id IS NULL` satırlarını arıyor. MySQL'de `(app_id, version_id, locale)` unique'i `null`'ları distinct saydığı için aynı locale için birden fazla "version_id null" satırı oluşabilir → `first()` deterministik değil. Pratikte `saveVersion` her sync'te version_id üretiyorsa az gözlenir, ama `identityData['version']` boş gelen edge case'lerde (Android'de bazen olur) yığılma riski var.

4. **`locale` `varchar(10)` ama BCP-47 daha uzun olabilir.** Sözleşme `'en-US'` (5), `'zh-Hans-CN'` (10), `'es-419'` (6). `zh-Hant-HK` da 10 karakter. Pratikte 10'a sığıyor ama `sr-Latn-RS` (10), `sr-Cyrl-RS` (10) sınırda. Region+script kombinasyonları için tampon yok — string truncate sessizce olabilir (MySQL strict mode'a göre).

5. **`description` TEXT, full-text index yok.** `ExplorerController::screenshots` `title` üzerinde `LIKE '%term%'` yapıyor (index kullanılamaz, `title` varchar olduğu için). `description` üzerinde herhangi bir LIKE araması **yok** — keyword density tamamen PHP-tarafında (`KeywordAnalyzer`). DB-side description araması istenirse FULLTEXT eklenmesi gerekir.

6. **`checksum` index okuyan yok.** Sadece `saveListing` PHP karşılaştırması yapıyor; DB'de `checksum`'a göre lookup atılmıyor. Index muhtemelen "aynı checksum'daki satırları paylaş / dedupe et" gibi bir gelecek için bırakılmış ama şu an boşa storage maliyeti.

7. **Field-diff seti tutarsız.** `detectChanges` 4 alana bakar (`title, subtitle, description, whats_new`). Checksum aynı 4 alandan üretilir. Ama `promotional_text`, `icon_url`, `screenshots`, `price`, `currency`, `video_url` upsert ediliyor → diff log yok, history yok. "Icon değişti" gözlemi imkansız (ayrı bir DNA tablosu yoksa).

8. **`updateVersionDetails`'da default locale fallback'i.** `defaultLocaleForCountry($app, $app->origin_country_code ?? 'us')` tek locale çekiyor; o locale o version için listing'i çekememişse (`fetchListings` 404) `whats_new` `null` kalıyor — sessizce, log yok.

9. **`saveListing`'de `description` ve `title` için boş string default'u** (`?? ''`). Migration'da `title` ve `description` NOT NULL; scraper'dan eksik veri gelirse boş satır yazılıyor — "veri yok" ile "veri boş" ayrımı kayboluyor.

10. **`fetched_at` her save'de `now()`** — yani aynı `(app_id, version_id, locale)` için art arda iki sync atılırsa, içerik aynı olsa bile `fetched_at` ileri taşınıyor. "Bu içerik en son ne zaman gerçekten değişti" sinyali yok; sadece "en son ne zaman scrape ettik".

11. **`storeListings` eager load N-locale.** `AppController::show` `with('storeListings')` ile **tüm locale'leri** payload'a basıyor. 40+ locale × full description (TEXT) JSON serialize ediliyor → büyük apps için detail endpoint'i ağır. Web'de tek bir locale gösteriliyor (tab'da seçili). Frontend tüm payload'u alıp lokal'de filtreliyor.

12. **Reconcile retry success'te change-detection.** `retryFailedItem` `saveListing`'e geçtiğinde, `existing` satır o sırada zaten yazılmış olabilir (önceki başarılı locale'de). Reconcile'da gelen 2. başarılı save aynı version_id'li `existing` ile karşılaşırsa field-diff koşulundaki `version_id !== version_id` testi engelliyor — sessizce upsert. Beklenen davranış olabilir ama doğrulanmamış.

## Refactor / İyileştirme Fırsatları

- **Content-addressable storage:** `description`, `screenshots`, `promotional_text` gibi büyük alanları ayrı `listing_contents` tablosuna `sha256(content)` PK ile çıkarmak; `app_store_listings` sadece foreign reference tutar. Locale × version × app product'ı oldukça büyük; aynı `description`'ın 40+ locale'de tekrar etmesi söz konusu değil ama version-to-version sabit kalan alanlar (örn. küçük güncellemelerde değişmeyen description) deduplike olur. Ayrıca change history alan-bazlı diff yerine "şu hash'ten şu hash'e" referansla daha temiz.
- **`screenshots` ayrı tabloya** (`app_store_listing_screenshots(listing_id, device_type, order, url)`) — device_type bazlı filtre, screenshot-bazlı change detection (eklenen/çıkarılan), ve Explorer feed'inde JSON parse yerine direkt JOIN.
- **Per-locale price farkı:** `price` ve `currency` listing'de tutuluyor ama `app_metrics`'te de var (`metric.price`, `metric.currency`, country bazlı). İkili kaynak çakışmasını çözmek gerek — listing locale-bazlı, metric country-bazlı. Şu an `ListingResource.price` ve `AppMetric.price` farklı zaman serilerinde — hangisini "official price" sayacağımız belirsiz. Listing'den price'ı çıkarıp metric'e konsolide etmek (metric zaten günlük) veya tersine listing'i tek source-of-truth yapmak.
- **`detectChanges` set'ini genişlet:** En azından `promotional_text` ve `icon_url` (hash karşılaştırması), `screenshots` (count + url-set diff). `field_changed` enum'una yeni değerler eklenmesi yeterli; `app_store_listing_changes.field_changed` zaten string.
- **Same-version diff politikasına karar ver:** "Aynı version içinde description değişti" senaryosunu kaybediyoruz. Çözüm: (a) `fetched_at`'a göre eski satırı historize et (`app_store_listing_history`), veya (b) checksum farkını her durumda log'la (phantom diff'leri başka türlü filtrele — örn. "ilk pass sonrası 5 dk içinde aynı locale" karantinası).
- **`locale` boyutunu `varchar(15)`'e çıkar** — BCP-47 script+region kombinasyonları için emniyet payı (`sr-Cyrl-RS`, `zh-Hant-HK` bugün 10'a denk; herhangi bir 3-letter region veya gelecek extension için yer açar).
- **`description_length` denormalize sütun** olarak migration'a ekle — accessor şu an her serialize'da `mb_strlen` çalıştırıyor; ASO sıralaması/filtresi DB-tarafında istenirse index'lenebilir.
- **`AppController::listing` 404 yerine "pending" yanıt:** Sync dispatch ettikten sonra 404 dönmek client'ta hata olarak gözüküyor; `202 Accepted` + sync_status linki daha doğru semantik.
- **`checksum` index'ini ya kullan ya kaldır:** Eğer dedupe niyeti yoksa storage tasarrufu için drop; varsa `saveListing`'i `where('checksum', $checksum)->first()` ile aramayı kapat (ama unique key ile çakışmamalı).
- **`storeListings` eager load'unu locale param'ına bağla:** `App::show` endpoint'i `?locales=en-US,tr` filtresi alıp sadece istenen locale'leri serialize etmeli; mevcut "tüm locale'leri patlat" pattern'i büyük apps'te payload'ı şişiriyor.
