# `countries` Tablosu Audit

## Genel Bakış

`countries` tablosu, App Store ve Google Play storefront'larının kanonik referans listesidir.
Doğal birincil anahtar olarak ISO 3166-1 alpha-2 kodu (lowercase, 2 karakter) kullanır.
Tablo neredeyse tamamen statiktir: yalnızca `CountrySeeder` tarafından yazılır, runtime'da
hiçbir yazma noktası yoktur. Üç tablo (`apps`, `app_metrics`, `trending_charts`) bu tabloya
yabancı anahtar üzerinden bağlıdır ve `restrictOnDelete` ile korunur.

Tablonun birincil işlevleri:

- Sync pipeline (`AppSyncer`) için ülke listesi ve dil haritası kaynağı.
- `CountryController` üzerinden web client'a aktif ülke listesi sağlamak.
- `exists:countries,code` validasyonu ile API girdilerini doğrulamak.
- Charts pipeline (`SyncDailyChartsCommand`) için platform-aktif ülke kümesi.

---

## Şema

Migration: `server/database/migrations/2026_04_06_000000b_create_countries_table.php`

| Kolon | Tip | Default | Açıklama |
|---|---|---|---|
| `code` | `string(2)` PK | — | ISO-3166-1 alpha-2, lowercase. Natural PK. |
| `name` | `string(100)` | — | UI'da gösterilen İngilizce ülke adı. |
| `emoji` | `string(10)` | — | Bayrak emoji'si (UI ipucu olarak). |
| `is_active_ios` | `boolean` | `false` | App Store bu ülke için scrape ediliyor mu? |
| `is_active_android` | `boolean` | `false` | Google Play bu ülke için scrape ediliyor mu? |
| `priority` | `smallInteger` | `0` | Sıralama ağırlığı; büyük olan önce. |
| `ios_languages` | `json` nullable | `null` | iOS storefront için BCP-47 locale dizisi. |
| `ios_cross_localizable` | `json` nullable | `null` | iOS'ta aynı storefront altında ek olarak dizinlenen locale'ler. |
| `android_languages` | `json` nullable | `null` | Android storefront için BCP-47 locale dizisi. |
| `created_at`, `updated_at` | timestamps | — | Standart timestamp'lar. |

---

## Index'ler & FK'lar

- Birincil anahtar: `code` (string, non-incrementing).
- Index: `is_active_ios`, `is_active_android`, `priority`.
- Bu tabloda dış FK yok (referans tablo).
- **Diğer tablolardan gelen FK'lar** (Bağımlı Tablolar bölümünde detaylı):
  - `apps.origin_country_code` → `countries.code` (cascade update, restrict delete)
  - `app_metrics.country_code` → `countries.code` (cascade update, restrict delete)
  - `trending_charts.country_code` → `countries.code` (default davranış — restrict)

---

## Model

`server/app/Models/Country.php`

- `$primaryKey = 'code'`, `$incrementing = false`, `$keyType = 'string'`.
- Mass-assignable kolonlar: 9 kolon tamamı `$fillable` içinde.
- Cast'ler:
  - `is_active_ios` / `is_active_android` → `boolean`
  - `ios_languages`, `ios_cross_localizable`, `android_languages` → `array`
- Scope: `scopeActiveForPlatform(string $platform)` → `$platform === 'ios'` ise
  `is_active_ios = true`, aksi halde `is_active_android = true` filtresi uygular.
- OpenAPI annotation (`#[OA\Schema]`) doğrudan modelde tanımlı (3 public alan: code, name, emoji).

---

## Seeder Davranışı

`server/database/seeders/CountrySeeder.php`

- Tanımlı toplam ülke: **125** (124 gerçek ülke + 1 sentinel `zz` = "Global").
- iOS aktif olmayan ülke: **14** (iOS sütununda `null` olanlar — örn. `bd`, `bo`, `cr`, `gt`, `hn`, `ir`, `la`, `ao`, `sv`, `ni`, `pa`, `py`, `lk`, `zz`).
- Android tüm 125 ülkede aktif (`android` hiçbir kayıtta `null` değil).
- `zz` (Global): iOS = `null`, Android = `['en-US']`. Reserved user-assigned ISO kodu;
  Android için ülke-bazlı olmayan metric'lerin hedefi.
- Üç yapı sabiti seeder içinde inline tanımlı:
  - `COUNTRIES` — code/name/ios/android.
  - `IOS_CROSS_LOCALIZABLE` — 69 ülke için Apple search dizinleme map'i.
  - `PRIORITIES` — 18 ülke için sayısal öncelik (`us` = 100, `gb/de/fr/jp` = 80, `tr/br/in` = 60, ...).
  - `EMOJIS` — 122 ülke için bayrak emoji (sentinel `zz` için emoji **yok**, seeder `?? ''` ile boş string yazar).
- Yazma stratejisi: `Country::whereNotIn('code', $validCodes)->delete()` ile fazlalıkları siler
  (FK `restrictOnDelete` olduğu için bağımlı satır varsa exception fırlar), ardından
  `Country::upsert()` ile tüm kayıtları upsert eder.
- Upsert güncellenen sütunlar: `name`, `emoji`, `is_active_ios`, `is_active_android`,
  `priority`, `ios_languages`, `ios_cross_localizable`, `android_languages`, `updated_at`.

---

## Yazan Yerler

`grep` çıktısı (`Country::create|update|insert|upsert|firstOrCreate|updateOrCreate`):

- **Tek yazma noktası:** `CountrySeeder::run()` — `Country::whereNotIn(...)->delete()` ve `Country::upsert()`.
- `app/` altında çalışma zamanında çağrılan **hiçbir** yazma operasyonu yok.

Pratik sonuç: tablo "kod ile yönetilen" referans tablodur. Yeni ülke / dil eklemek için
seeder güncellenip yeniden çalıştırılmalıdır.

---

## Okuyan Yerler

### Sync pipeline — `app/Services/AppSyncer.php`

- `iosLocaleMap()` (satır 662): `Country::where('is_active_ios', true)
  ->whereNotNull('ios_languages')->orderByDesc('priority')` ile `language → country` map
  oluşturur. Sonuç `ios_locale_map` cache anahtarında 3600 sn TTL ile tutulur.
- `androidLocaleMap()` (satır 693): aynı mantık `is_active_android` üzerinde,
  `android_locale_map` cache anahtarı.
- `iosActiveCountries()` (satır 724): `is_active_ios = true` ülkelerin `code` listesi,
  `priority` desc sıralı, `ios_active_countries` cache anahtarı.
- `defaultLocaleForCountry()` (satır 578): `Country::find($countryCode)` → platforma göre
  `ios_languages` veya `android_languages` dizisinin ilk elemanını döner.
- Phase 2 (Listings) iOS/Android map'lerinden locale döngüsü kurar (satır 185-200).
- Phase 3 (Metrics) iOS için `iosActiveCountries()`, Android için `[GLOBAL_COUNTRY]` (`zz`) kullanır (satır 306).

### Charts pipeline

- `app/Console/Commands/Charts/SyncDailyChartsCommand.php` (satır 48):
  `Country::activeForPlatform($platform)->orderByDesc('priority')->orderBy('name')->pluck('code')`
  ile o platformun aktif ülkeleri üzerinde chart sync job'ları enqueue eder.

### API Filtreleri / Validation

- `app/Http/Requests/Api/Chart/ChartIndexRequest.php`: `country_code` → `exists:countries,code`.
- `app/Http/Requests/Api/App/AppSearchRequest.php`: `country_code` → `exists:countries,code`.
- `app/Http/Requests/Api/App/ListingRequest.php`: `country_code` → `exists:countries,code` (+ `AppAvailableCountry` rule).
- `app/Http/Requests/Api/Publisher/PublisherSearchRequest.php`: `country_code` → `exists:countries,code`.
- `app/Rules/AppAvailableCountry.php`: `countries` tablosunu doğrudan sorgulamaz, ama
  `country_code` parametresinin app store müsaitliğini `app_metrics` üzerinden doğrular.

### Controller

- `app/Http/Controllers/Api/V1/CountryController.php` — tek endpoint (`GET /countries`).
  `code != 'zz'` ve (`is_active_ios = true` veya `is_active_android = true`) filtresi,
  `name` asc sıralı, sadece `code, name, emoji, ios_languages, android_languages` alanları.

---

## API Yüzeyi

### Route

`server/routes/api.php:75`

```php
Route::get('countries', V1\CountryController::class);
```

`/api/v1/countries` altında auth-protected `Sanctum` route'u (üst grup `auth:sanctum` middleware'i).

### Resource

`app/Http/Resources/Api/Country/CountryResource.php`

- 5 alan döner: `code`, `name`, `emoji`, `ios_languages`, `android_languages`.
- `ios_cross_localizable` ve `priority` API'ye **expose edilmez**.
- OpenAPI schema `CountryResource` adıyla `Country` schemasını extend eder.

### Web Client

- Generated TS client: `web/src/api/endpoints/countries/countries.ts` — Orval, tek
  `useListCountries()` hook.
- `web/src/components/CountrySelect.tsx` — popover combobox, `useCountries()` wrapper'ı,
  `staleTime: Infinity` ile cache'lenir; `zz` sentinel UI'dan filtrelenir.
- Tüketiciler: `RatingsTab`, `KeywordsTab`, `StoreListingTab`, `RankingsTab`,
  `Trending`, `Apps`, `Publishers`, `pages/apps/Show.tsx`, `pages/publishers/Index.tsx`,
  `SyncingOverlay`, `Landing` — yani ülke seçici uygulamanın geneline yayılmış durumda.

---

## Bağımlı Tablolar

| Tablo | Kolon | FK Davranışı | Migration |
|---|---|---|---|
| `apps` | `origin_country_code` (`char(2)`, default `'us'`) | `cascadeOnUpdate`, `restrictOnDelete` | `2026_04_06_000001_create_apps_table.php:50` |
| `app_metrics` | `country_code` (`char(2)`) | `cascadeOnUpdate`, `restrictOnDelete` | `2026_04_06_000005_create_app_metrics_table.php:54` |
| `trending_charts` | `country_code` (`char(2)`, default `'us'`) | default davranış (`restrict`) | `2026_04_10_000001_create_chart_tables.php:22` |

`restrictOnDelete` nedeniyle bir ülkenin satırı silinmek istenirse, o ülkeye referans veren
herhangi bir app/metric/chart varsa DB exception fırlatır. Seeder'in
`whereNotIn(...)->delete()` çağrısı da bu durumda patlar — pratikte ülke listesinden
çıkarma işlemi ancak hiçbir bağımlı satır kalmamışsa mümkündür.

Not: `app_metrics.country_code` için **GLOBAL sentinel** `'zz'` rezervedir
(`AppMetric::GLOBAL_COUNTRY` sabiti). Android sync sadece bu tek satıra metric yazar.

---

## Gözlemler & Kokular

1. **`zz` sentinel'in tablo şemasıyla çelişkisi.** `code` kolonu "ISO-3166-1 alpha-2"
   olarak yorumlanır; `zz` user-assigned reserved kod olsa da `is_active_ios = false`,
   `is_active_android = true` olarak veriye karıştırılıyor. Hem
   `CountryController` hem `CountrySelect` bu satırı explicit olarak filtreliyor —
   yani "yarı public" bir kayıt. Şema bunu tip seviyesinde yansıtmıyor.

2. **`ios_cross_localizable` runtime'da hiç okunmuyor.** `grep -rn "cross_localizable"`
   yalnızca migration, model property, seeder yazma noktası ve cast sonucunu döndürdü.
   `app/` altında bir okuma yok; yani veri tutuluyor ama duplicate scrape önleme mantığı
   **henüz devrede değil**. Şu anda `iosLocaleMap()` Apple'ın cross-localizable
   davranışını dikkate almıyor; `ios_languages`'in ilk elemanı üzerinden language→country
   eşlemesi yapıyor. Bu, aynı locale'in birden fazla storefront'ta scrape edilmesine yol
   açabilir.

3. **`priority` API'ye expose edilmiyor**, sadece arka uçta sıralama için kullanılıyor.
   `CountrySelect.tsx` ülkeleri istemcide `localeCompare` ile A-Z sıralıyor; backend
   tarafındaki priority sıralaması UI'a yansımıyor. `iosLocaleMap()` ve charts pipeline
   tarafında ise kritik (örn. `us` ilk işlenir).

4. **JSON kolonları sorgulamak zor.** `ios_languages` ve `android_languages`
   sıralı dizilerdir ve `whereJsonContains` ile sorgulanabilir olsalar da çoğu kullanım
   alanında tüm satır PHP'ye çekilip iterate ediliyor (`iosLocaleMap`, `androidLocaleMap`,
   `defaultLocaleForCountry`). Cache 1 saat olduğundan pratik bir darboğaz değil; ancak
   normalize bir `country_locale` pivot tablosu daha sorgulanabilir olurdu.

5. **Locale ön-sıralaması "primary" varsayımına dayalı.** `iosLocaleMap()` ve
   `androidLocaleMap()` `langs[0]`'ı primary kabul ediyor. Seeder dizi sırası bu varsayımı
   karşılıyor (örn. `tr` → `['tr', 'en-GB']`), ama bu kontrat schema'da değil yalnızca
   seeder kodu konvansiyonunda yaşıyor.

6. **Seeder'ın `delete()` çağrısı FK kısıtı nedeniyle kırılgan.** Seeder yeniden
   çalıştırıldığında `validCodes` listesinden çıkarılmış bir ülke için var olan bir
   `apps.origin_country_code` veya `app_metrics.country_code` kaydı varsa
   `restrictOnDelete` exception fırlatır — sessiz başarısızlık değil, ama
   beklenmeyen ortamlarda upgrade'i durdurabilir.

7. **Emoji eksikleri.** `EMOJIS` map'i `zz` için kayıt içermez, seeder `?? ''` ile
   boş string yazar. UI flag image'i `flagcdn.com/w40/{code}.png` üzerinden çekiyor
   (emoji kullanmıyor) — yani emoji alanı pratikte dead-weight olabilir.

8. **`name` İngilizce, lokalizasyon yok.** Tek dilli; çoklu dil UI senaryosu için
   ek bir `country_translations` yapısı veya client-side i18n gerekecektir.

9. **Cache invalidation manuel.** `ios_locale_map`, `android_locale_map`,
   `ios_active_countries` cache anahtarları seeder yeniden çalıştırıldığında otomatik
   temizlenmiyor. Operasyonel olarak `make cache-clear` çağrısı gerekir.

---

## Refactor / İyileştirme Fırsatları

1. **`ios_cross_localizable` ya devreye alınmalı ya kaldırılmalı.** Eğer Apple'ın
   cross-localizable davranışıyla duplicate scrape önlenecekse, `iosLocaleMap()`
   bu kolonu okuyacak şekilde genişletilmeli; aksi halde migration + seeder
   sadeleştirilmelidir.

2. **`zz` sentinel'i kolon seviyesinde işaretlemek.** Ek bir `is_sentinel` boolean
   veya `kind` enum (`country` / `global`) ekleyerek hem `CountryController` hem
   `CountrySelect` filtre mantığı tek bir flag üzerinden tek hatta toplanabilir.
   Alternatif: `zz` satırını tamamen `countries`'ten çıkarıp özel bir konstant ile
   `app_metrics`'e yazmak (ama FK `restrictOnDelete` o zaman bozulur — özel bir
   migration yolu gerekir).

3. **JSON dilleri normalize etmek.** `country_locales` pivot tablosu
   (`country_code, platform, locale, position, is_cross_localizable`) sorgulanabilirliği
   artırır, primary locale kavramını `position = 0` ile schema'da ifade eder ve
   `iosLocaleMap()` gibi PHP-side loop'ları SQL'e taşır.

4. **Seeder'ı küçük dosyalara bölmek.** 311 satırlık tek dosyada 125 ülke + 122 emoji +
   69 cross-localizable + 18 priority sabiti karışık. Ayrı veri dosyaları (örn. JSON
   `database/data/countries.json`) seeder'ı 30 satıra indirir ve diff okumayı kolaylaştırır.

5. **Cache invalidation hook.** `CountrySeeder::run()` sonunda `Cache::forget()` ile
   `ios_locale_map`, `android_locale_map`, `ios_active_countries` anahtarlarını
   temizlemek operasyonel sürprizleri kaldırır.

6. **`priority` API'ye expose edilebilir.** `CountrySelect` priority'ye göre öne
   çıkanları en üstte gösterebilir (mevcut `us/gb/de/fr/jp...` öncelikleri zaten
   tanımlı). UI'da daha iyi defaults sağlar.

7. **`emoji` kolonunun durumu netleştirilmeli.** UI flag CDN kullanırken kolon ölü
   yük taşıyor. Ya UI emoji'ye geçirilmeli (offline-friendly), ya kolon silinmeli.

8. **`AppAvailableCountry` rule'unu kuvvetlendirmek.** Şu anda yalnızca metric varsa
   `is_available` bakılıyor; metric hiç yoksa "optimistic accept" davranışı sessiz
   hatalara yol açabilir. `Country::where('code', $value)->where('is_active_{platform}', true)`
   kontrolü erken kapı niteliğinde olabilir.

9. **`name` lokalizasyonu** için `country_translations` tablosu — kısa vadede aşırı
   olabilir; relaunch sonrası i18n gündeme alınırsa hatırlanmalı.
