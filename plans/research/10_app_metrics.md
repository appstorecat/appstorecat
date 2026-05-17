# app_metrics — Audit

## Genel Bakis

`app_metrics` her takipli app icin **gunluk per-country snapshot** tutar. iOS'ta her aktif storefront (Country.is_active_ios) icin bir satir, Android'de tek bir global satir (`country_code = 'zz'`) yazilir. Satir; o gunun rating, rating_count, rating_breakdown, fiyat, dosya boyutu ve "store'da gorunur mu" durumunu icerir.

Tablonun amaci tek bir "current rating" alani yerine zaman serisi tutmak: 30 gunluk trend, gunluk yeni yorum dagilimi (`delta_breakdown`) ve country-bazli karsilastirma `Ratings` sekmesini besler.

Yazici tek bir noktada toplanmistir: `AppSyncer::saveMetric`. Okuyucular `RatingController`'in 3 endpoint'i, `AppResource`, `AppDetailResource`, `AppAvailableCountry` rule ve `App::metrics()` iliskisidir.

## Sema

Migration: `server/database/migrations/2026_04_06_000005_create_app_metrics_table.php`.

| Sutun | Tip | Notlar |
|---|---|---|
| `id` | bigint PK | |
| `app_id` | FK -> `apps.id` | cascadeOnDelete |
| `version_id` | FK -> `app_versions.id` nullable | nullOnDelete; snapshot anindaki live version |
| `country_code` | char(2) | FK -> `countries.code`; Android'de `'zz'` |
| `date` | date | UTC, gunluk |
| `rating` | decimal(3,2) default 0 | 0.00 - 5.00 |
| `rating_count` | unsignedInteger default 0 | |
| `rating_breakdown` | json nullable | `{"1": n, ..., "5": n}` |
| `rating_delta` | int nullable | `rating_count`'in onceki snapshot'tan farki |
| `price` | decimal(10,2) nullable | iOS'ta country bazli |
| `currency` | string(3) nullable | ISO-4217 |
| `installs_range` | string(30) nullable | Google Play bucket (`"1,000,000+"`); iOS'ta null |
| `file_size_bytes` | unsignedBigInt nullable | snapshot anindaki binary boyutu |
| `is_available` | boolean default true | store o ulkede app'i unavailable raporladiysa false |
| `created_at`, `updated_at` | timestamps | |

## Index'ler & FK'lar

- **Unique:** `(app_id, country_code, date)` — `app_metrics_app_country_date_unique`. `updateOrCreate` bu key uzerinden calisir; gun ici tekrar sync idempotent.
- **Index:** `(app_id, date)` — app + tarih sorgulari (history/summary).
- **Index:** `(country_code, date)` — country-bazli analiz icin tanimli ama kod tabaninda bu yonde sorgu yok.
- **FK `app_id`** -> `apps.id` cascadeOnDelete.
- **FK `version_id`** -> `app_versions.id` nullOnDelete.
- **FK `country_code`** -> `countries.code` cascadeOnUpdate, restrictOnDelete.

## Model

`server/app/Models/AppMetric.php`:

- **Casts:** `rating` decimal:2, `price` decimal:2, `rating_breakdown` array, `date` date, `is_available` boolean.
- **Sabit:** `AppMetric::GLOBAL_COUNTRY = 'zz'` — Android storefront'unun yer tutucu kodu.
- **Iliskiler:** `app(): BelongsTo<App>`, `version(): BelongsTo<AppVersion, 'version_id'>`.
- **Fillable:** tum data alanlari (id ve timestamps haric).
- **OA\Schema:** Orval `web/src/api/models/appMetric.ts` icin bu sema kullaniliyor.

Karsi yon iliski: `App::metrics(): HasMany<AppMetric>` (`server/app/Models/App.php:123`) ve `AppVersion::metrics(): HasMany<AppMetric, 'version_id'>` (`server/app/Models/AppVersion.php:55`).

## Yazan Yerler

Tek yazici: `AppSyncer::saveMetric` (`server/app/Services/AppSyncer.php:365-396`).

Algoritma:
1. `today = now()->format('Y-m-d')`.
2. Ayni `(app_id, country_code)` icin `date < today` olan en yeni satir cekilir -> `previousMetric`.
3. `updateOrCreate((app_id, country_code, date) => today)` ile yazim:
   - `rating_delta = previousMetric ? (data.rating_count - previousMetric.rating_count) : null`
   - `is_available` parametresi (default true) tabloya yazilir; `false` durumunda diger alanlar `null`/`0` ile yazilir cunku scraper bos veri donmustur.

Cagrildigi yerler:
- `AppSyncer::fetchAndSaveMetric` (satir 325-363) — Phase 3 (Metrics) icindeki normal scrape. `iosActiveCountries()` veya `['zz']` listesinde her ulke icin scraper denenir. `classifyError === REASON_EMPTY_RESPONSE` ise `saveMetric(..., isAvailable: false)` yazilir, retry tuketildiyse `pushFailedItem` ile failed_items kuyruguna eklenir.
- `AppSyncer::retryFailedItem` (satir 420-431) — `ReconcileFailedItemsJob` (`server/app/Jobs/Sync/ReconcileFailedItemsJob.php`) tarafindan basarisiz `metric` item'lari yeniden cekilirken.

Onemli: `is_available=false` durumu da tabloya yazilir, yani "yok" bilgisi bos satir yerine acik kayittir. Ancak `is_available=false` satirinda `rating_count = 0` oldugu icin `rating_delta` hesabi onceki satira gore negatif buyuk bir deger uretir (ornek: 10000 -> 0 = -10000); bu satir history aggregate'ine girmez (asagiya bakiniz).

## Okuyan Yerler

### `RatingController` (`server/app/Http/Controllers/Api/V1/App/RatingController.php`)

1. **`summary` (GET ratings/summary):**
   - `MAX(date)` ile en son snapshot tarihi.
   - `aggregateSnapshot(app, platform, latestDate)` -> `latest`.
   - 30 gun once <= olan en buyuk tarih -> baseline. Yine `aggregateSnapshot`.
   - `RatingSummaryResource` `rating`, `rating_count`, `breakdown` ve `trend.rating_delta_30d / rating_count_delta_30d` doner.

2. **`history` (GET ratings/history?days=N):**
   - Pencerenin bir gun oncesinden baseline snapshot.
   - Pencere icindeki gunlerin **unique tarih** listesi cekilir, her tarih icin `aggregateSnapshot` ile o gunun snapshot'i hesaplanir.
   - Her takvim gunu icin loop: snapshot yoksa placeholder `AppMetric` (rating/rating_count null, breakdown sifirli) push edilir; varsa `delta_breakdown` ve `delta_total` onceki gozlemden farkla hesaplanir.
   - `RatingHistoryPointResource::collection` doner.

3. **`countryBreakdown` (GET ratings/country-breakdown):**
   - Android icin bos koleksiyon + `supported: false`, `message: ...`.
   - iOS icin `latestMetricPerCountry(app)`: TUM satirlari (`app_id` filtreli) PHP'ye cekip `groupBy('country_code')` + `->first()` ile en yeni satiri secer. Sorgu order: `country_code asc, date desc, id desc`. `RatingByCountryResource` `country_code`, `rating`, `rating_count` doner.

`aggregateSnapshot`:
- Android: tek bir `country_code = 'zz'` satiri okur.
- iOS: O gunun `rating_count > 0` olan tum country satirlari toplanir; `rating` weighted average (her satirin `rating * rating_count` toplami / `sum(rating_count)`), `breakdown` bucket-bucket toplam. Sentetik bir `AppMetric` ornegi doner (DB'ye yazilmaz).

### `AppResource` (`server/app/Http/Resources/Api/App/AppResource.php`)
- `metrics()->orderByDesc('date')->first()` -> latest metric. `rating` ve `rating_count` buradan doner.
- **Country filtresi yok**: iOS'ta bu, en son hangi country yazildiysa o satirin rating'i (her run sonunda olasilikla son yazilan ulke) anlamina gelir.

### `AppDetailResource` (`server/app/Http/Resources/Api/App/AppDetailResource.php`)
- `metrics()->orderByDesc('date')->first()` -> `rating`, `rating_count`, `file_size_bytes`. Country filtresi yok (yukaridaki sorunla ayni).
- `latestUnavailableCountries()` — `selectRaw('MAX(id) as id') ... groupBy('country_code')` ile her country icin en son satirin id'sini bulur, sonra ikinci sorguda `whereIn(id, ...)` ile `is_available=false` olanlari `country_code` listesi olarak verir. Sonuc `unavailable_countries` alaninda yayinlanir.

### `AppAvailableCountry` rule (`server/app/Rules/AppAvailableCountry.php`)
- Bir country-code parametresinin gecerli olup olmadigini dogrularken `AppMetric` icin `MAX(id)` -> `is_available` okur. Hic satir yoksa optimist gecer.

### `CountryController` (`server/app/Http/Controllers/Api/V1/CountryController.php:33`)
- `'zz'` rezerve kodunu listeden hariclamak icin `AppMetric::GLOBAL_COUNTRY` sabitini referans alir (veri okumaz, sadece sabit).

### `DashboardController`
- `AppMetric` referansi **yok**. Dashboard sadece app/version/change sayar.

## API Yuzeyi

`server/routes/api.php:53-55`:
- `GET /apps/{platform}/{externalId}/ratings/summary` -> `RatingController@summary`
- `GET /apps/{platform}/{externalId}/ratings/history?days=N` -> `RatingController@history`
- `GET /apps/{platform}/{externalId}/ratings/country-breakdown` -> `RatingController@countryBreakdown`

Resource'lar: `RatingSummaryResource`, `RatingHistoryPointResource`, `RatingByCountryResource`. Hepsi swagger'a `OA\Schema` ile yansiyor, Orval bunlari `web/src/api/endpoints/apps/apps.ts` icinde `useGetRatingSummary`, `useGetRatingHistory`, `useGetRatingCountryBreakdown` olarak uretiyor.

Not: `RatingsTab.tsx` Orval hook'larini kullanmiyor; dogrudan `axios.get(...)` ile elle fetch ediyor ve kendi `interface RatingSummary / RatingHistoryPoint / RatingByCountry` tiplerini deklare ediyor. `AppMetric` orval modeli (`web/src/api/models/appMetric.ts`) uretiliyor ama suanda hicbir bilesen tarafindan kullanilmiyor.

## Bagimli Tablolar

Hicbir tablo `app_metrics.id`'ye FK tutmuyor. Tablonun child tablosu yok; cocugu olmayan, sade bir snapshot tablosudur.

## Gozlemler & Kokular

1. **Gunluk satir buyumesi cok hizli.** iOS'ta `iosActiveCountries()` x 1 satir/gun yaziliyor. Tracked app sayisi K, aktif iOS ulke sayisi C ise gunluk artis ~K*C satir. C ~30-150 araliginda olabilir. Retention politikasi yok; tablo zamanla cisirir.

2. **`rating_delta` yarim sutun.**
   - `saveMetric` hesapliyor ama hicbir okuyucu bu sutunu kullanmiyor. Aggregate delta'lar (`history`/`summary`) anlik PHP'de yeniden hesaplaniyor (`RatingController::history` icindeki `prev/curr` farki ve `RatingSummaryResource::trend`).
   - Daha kotusu: `previousMetric` `whereDate('date', '<', $today)` ile bir onceki **takvim gunu** degil, herhangi bir onceki gun. Eger 5 gun once yazilmissa delta 5 gunluk olur ama isim "delta" — yaniltici.
   - `is_available=false` gununden sonra ertesi gun availability geri donerse `rating_delta` `rating_count - 0` (ani sicrama) yazar; gercekligi yansitmaz.

3. **`AppResource` / `AppDetailResource` ulke filtresiz "latest metric" cekiyor.**
   - `metrics()->orderByDesc('date')->first()` — ayni tarihte birden cok country satiri var; hangisi gelecek deterministik degil (DB'nin id/insert sirasina bagli). iOS'ta gosterilen "rating" ulkeye gore degisebilir.
   - `'us'` veya `aggregateSnapshot` mantigi burada da uygulanmali; suanda bu bilgi `ratings/summary` ile cakisiyor (latest UI rating'i ile detay sayfasindaki rating ayni veri kaynagina dayanmamis olabilir).

4. **`latestUnavailableCountries` N+1 degil ama 2 sorgu + MySQL spesifik bir kalip.**
   - `selectRaw('MAX(id) as id') ... groupBy('country_code')` — `id`'nin tarih ile monoton olduguna guveniyor. Backfill (gecmise donuk satir ekleme) varsa kirilir. Daha guvenli kalip: window function / `JOIN (latest per group)`. Suanki form basit ama dayaniksiz.

5. **`installs_range` string ve sadece Android.** Sayisal analiz yapilamiyor (range buckets). iOS'ta her zaman null. Sema seviyesinde aciklayici comment var ama tutarli bir "installs_min/installs_max" gibi sayisal alan yok.

6. **`file_size_bytes` cift kayit.** Ayni alan hem `app_versions.file_size_bytes` (`server/database/migrations/2026_04_06_000002_create_app_versions_table.php:22`) hem `app_metrics.file_size_bytes` icinde tutuluyor. `AppDetailResource` metrics'tekini okuyor (`$latestMetric?->file_size_bytes`), ama snapshot anindaki version zaten `version_id` ile bagli — versions'taki deger yeterli. Cift kaynak driftirme riski tasiyor.

7. **`is_available=false` satirlarinin aggregate etkisi.**
   - `aggregateSnapshot` iOS'ta `rating_count > 0` filtresiyle bu satirlari elerken, Android'de `'zz'` satirinin `is_available=false` ile yazilmasi durumunda `rating=0/rating_count=0` doner; UI sifir rating gosterir. `rating_count = 0` filtresi Android dalinda yok.
   - `history`'de placeholder atilan gun ile gercekten yazilmis `is_available=false` (rating_count=0) satiri farkli davranir — eski tarafta delta 0, digerinde ani dusus.

8. **`GLOBAL_COUNTRY = 'zz'` semantik tasmasi.**
   - Sabit `AppMetric` modeli icinde tanimli ama 4 yerde kullaniliyor (`AppSyncer`, `RatingController`, `CountryController`). Aslinda platform-bazli storefront kavrami; `AppMetric`'in disinda da gecerli (orn. `countries` tablosunda gercek bir kayit gibi gozukuyor cunku FK `country_code -> countries.code`). Bu, `countries` tablosunda `'zz'` adli sahte bir kayit oldugunu ima ediyor.

9. **`rating_breakdown` null vs sifirli object.** Saver `! empty($data['rating_breakdown'])` ile null veya array yaziyor. Okuyucular (`RatingHistoryPointResource`, `aggregateSnapshot`, `RatingSummaryResource`) hep `0` fallback ile cevriliyor; sema null kabul ediyor. Tutarli ama "null breakdown" ile "tum yildizlar 0" ayrim kaybolur.

10. **`country_code` FK + `restrictOnDelete` ile country silinemez.** `'zz'` ozelinde silme isteminde kilitlenir; aslinda istenen davranis muhtemelen budur ama dokumante degil.

11. **`updateOrCreate` ayni gunde defalarca cagrildiginda `rating_delta` her seferinde yeniden hesaplanir** ve `previousMetric` "dunkun" satirina bakar; gun ici idempotent kalir. Ama gun degisirken iki yazici (orn. on-demand + scheduled) yaris ederse `rating_delta` ilk yazici dunkune, ikinci yazici (eger ayni gunde tekrar yazarsa) hala dunkune bakar — sorun degil.

12. **`metrics()` iliskisi `orderByDesc('date')->first()` ile 3 ayri yerden cagriliyor** (`AppResource`, `AppDetailResource`, App show flow). N+1 riski: app listesinde her satir icin ayrica metric sorgusu cikar. Eager loading (`with('metrics')`) bu endpointlerde gozukmuyor — listede skor potansiyel olarak yuksek.

13. **`AppVersion` -> `AppMetric` iliskisi (`version_id`) tanimli ama hicbir yerde okunmuyor.** Snapshot'in hangi versiyona ait oldugu kaydediliyor fakat raporlanmiyor.

## Refactor / Iyilestirme Firsatlari

1. **Aggregate column** — iOS icin gunluk weighted average ve toplam rating_count'u tekrar tekrar hesaplamak yerine, per-app per-day bir "global" satir (`country_code='zz'` veya ayri `app_daily_stats` tablosu) tutulabilir. Hot path'te `aggregateSnapshot` PHP loop'undan kurtarir.

2. **Latest-per-country materialization** — `latestUnavailableCountries` ve `latestMetricPerCountry` her detay/breakdown isteginde MAX-groupBy yapiyor. `app_country_latest` adli son satirin id'sini tutan kucuk bir tablo veya `apps.latest_metric_id` snapshot referansi ekstra sorgu tasarrufu saglar.

3. **`rating_delta` ya hesaplanma kuralini netlestir ya da kaldir.** Suanda yarim. Eger sutun kalacaksa: (a) sadece bir onceki **takvim gunu** ile fark, (b) `is_available` true->true gecislerinde anlamli, (c) okuyucular bu sutunu kullansin ve PHP'deki anlik delta hesabi kaldirilsin. Aksi halde kaldirip yerine "previous_snapshot_date" tutmak da bir secenek.

4. **`AppResource`/`AppDetailResource` icin "global" rating semantigini netlestir.**
   - Ya `country_code='us'` (Android'de `'zz'`) seciliyor ya da `aggregateSnapshot` cagriliyor. Suanki `orderByDesc('date')->first()` belirsiz ve hatali.
   - Detail sayfasinda RatingsTab zaten dogru aggregate'i cekiyor; detail header ile arasinda farklilik kullaniciyi sasirtir.

5. **`file_size_bytes` ve `price`/`currency` icin tek kaynak sec.**
   - `file_size_bytes`: `app_versions`'da kalsin, `app_metrics`'ten cikar (snapshot zaten version_id ile baglar).
   - Alternatif olarak `app_versions`'tan cikarip sadece metrics'te tutmak da olur cunku boyut country/storefront ile degisebilir (iOS region binary). Karar yapilmali, cift tutmak drift kaynagi.

6. **`is_available=false` satirlarinda diger data alanlarini sema seviyesinde `null` zorla.** Suanda `0`/`null` karisik yaziliyor. Migration'a `CHECK` ya da model `setAvailabilityAttribute` mutator'i konabilir.

7. **`installs_range`'i sayisallastir.** `installs_min` ve `installs_max` unsignedBigInt ekleyip "1,000,000+" stringini parse edip yazmak Android trend grafiklerini mumkun kilar.

8. **Retention politikasi.** Ornek: 365 gunden eski satirlari haftalik rollup'a (ortalama + min/max) cevirip silen bir job. Tablo sinirsiz buyumeye karsi korumasiz.

9. **`(app_id, country_code, date desc)` index'i.** `latestMetricPerCountry` PHP'de tum satirlari cektigi icin pahali. Composite ve sirali index ile + `whereIn` subquery ile DB'de yapilabilir.

10. **`(app_id, is_available, date)` partial/filtered index** (MySQL 8'de filtered index dogal yok ama `(app_id, is_available)` standart index isi gorur) — `latestUnavailableCountries` icin yararli olabilir.

11. **`country_code` FK + `'zz'` sahte ulkesi yerine `platform` enum'u** — `app_metrics`'e `storefront` (country veya 'global-android') gibi semantigi acik bir kolon eklemek `GLOBAL_COUNTRY` patch'inden temizler. Veya `app_metrics_android` ve `app_metrics_ios` ayri tablo (heterojen sema icin yararli) tasarim secenegi olabilir.

12. **Orval-uretilmis `AppMetric` modelini RatingsTab'ta kullanmak veya orval'dan kaldirmak.** Suanda 30 satirlik dead code; `RatingsTab.tsx` kendi interface'ini tutuyor.

13. **Eager loading `with('metrics')` audit'i.** App list endpoint'lerinde N+1'i onlemek icin `latest('metrics')->one()` (Laravel 9+ HasOne of many) yardimci olur.

14. **`version_id`'yi okuyucularda kullan.** Snapshot'in hangi versiyona denk dustugunu UI'a tasimak (orn. "ratings since v1.2.4") veri zaten var.
