# `app_versions` — Audit

## Genel Bakış

`app_versions`, bir uygulamanın store tarafından bildirilen **versiyon kimliklerini** (örn. `3.14.0`) ve o versiyona ait özet alanları (release notu, binary boyutu, release tarihi) tutar. App başına versiyon string'i benzersizdir; `apps` ile **1:N** ilişki kurulur.

Tablo, üç bağımlı tabloya **referans noktası** olarak hizmet eder:

- `app_store_listings.version_id` — listing snapshot'ı hangi versiyona ait
- `app_metrics.version_id` — bir günün metric satırı hangi versiyon canlıyken alındı
- `store_listing_changes.version_id` — diff hangi versiyon geçişinde algılandı

Pipeline akışı (`AppSyncer::sync`):
1. Phase 1 — Identity → `saveVersion` (yeni satır veya mevcut)
2. Phase 2 — Listings → `version_id` ile birlikte yazılır
3. Phase 3 — Metrics → `version_id` ile birlikte yazılır
4. Phase 4 — Finalize → `updateVersionDetails` (whats_new + file_size_bytes geri yazılır)

## Şema

Migration: `/Users/ismail/Projects/opensource/appstorecat/server/database/migrations/2026_04_06_000002_create_app_versions_table.php`

| Kolon | Tip | Null | Açıklama |
|---|---|---|---|
| `id` | `bigint unsigned PK` | hayır | Auto-increment |
| `app_id` | `bigint unsigned` | hayır | FK → `apps.id`, cascade on delete |
| `version` | `string` | hayır | Store-reported version string (örn. `3.14.0`) |
| `release_date` | `date` | evet | Store'un bildirdiği release tarihi |
| `whats_new` | `text` | evet | Varsayılan locale'deki release notu; per-locale variant `app_store_listings.whats_new`'da |
| `file_size_bytes` | `unsigned bigint` | evet | Binary boyutu byte cinsinden; raporlanmadığında null |
| `created_at` / `updated_at` | `timestamp` | evet | Standart Eloquent timestamp'leri |

## Index'ler & FK'lar

- **Unique**: `(app_id, version)` — bir app için aynı versiyon string'i iki kez yazılamaz
- **Index**: `release_date`
- **Index**: `(app_id, release_date)`
- **FK**: `app_id` → `apps.id`, `cascadeOnDelete`

Tabloyu referans alan tablolar (hepsi `nullOnDelete`):

| Tablo | Kolon | Davranış |
|---|---|---|
| `app_store_listings` | `version_id` | Versiyon silinirse listing kalır, FK null'a düşer |
| `app_metrics` | `version_id` | Versiyon silinirse metric kalır, FK null'a düşer |
| `app_store_listing_changes` | `version_id` | Change kaydı korunur, FK null'a düşer |

## Model

Dosya: `/Users/ismail/Projects/opensource/appstorecat/server/app/Models/AppVersion.php`

- `$fillable`: `app_id`, `version`, `release_date`, `whats_new`, `file_size_bytes`
- **Casts**: `release_date => date`
- **İlişkiler**:
  - `app()` — `BelongsTo<App>`
  - `metrics()` — `HasMany<AppMetric>` (`version_id` FK üzerinden)
  - `storeListings` ilişkisi **tanımlanmamış** (kullanılan yerler ya `App::storeListings()->orderByDesc('version_id')` gibi joinli yazılıyor ya da `StoreListing::version()` belongsTo üstünden geriye doğru gidiliyor)
- OpenAPI: `#[OA\Schema(schema: 'AppVersion')]` — `id`, `version`, `release_date`, `whats_new`, `file_size_bytes`, `created_at` expose edilir

`App` modelindeki karşılık (`/Users/ismail/Projects/opensource/appstorecat/server/app/Models/App.php:131-134`):

```php
public function versions(): HasMany
{
    return $this->hasMany(AppVersion::class)->latest('id');
}
```

İlişki **default olarak `id DESC`** sıralı dönüyor — semver değil insert sırası (aşağıdaki kokular bölümüne bakın).

## Yazan Yerler

### `AppSyncer::saveVersion` — yeni satır (firstOrCreate)

`/Users/ismail/Projects/opensource/appstorecat/server/app/Services/AppSyncer.php:165-179`

```php
public function saveVersion(App $app, array $identityData): ?AppVersion
{
    $versionString = $identityData['version'] ?? null;
    if (! $versionString) {
        return null;
    }
    return AppVersion::firstOrCreate(
        ['app_id' => $app->id, 'version' => $versionString],
        ['release_date' => $identityData['current_version_release_date'] ?? null],
    );
}
```

- Identity verisinde versiyon string yoksa `null` döner; Phase 2/3 versiyonsuz devam eder
- Mevcut satır için **sadece `release_date` doldurulur** (`whats_new`, `file_size_bytes` boş kalır)
- Sync sırasında `Phase 1`'in en sonunda çağrılır (`AppSyncer::sync` line 64)

### `AppSyncer::updateVersionDetails` — Phase 4 finalize update

`/Users/ismail/Projects/opensource/appstorecat/server/app/Services/AppSyncer.php:546-562`

```php
public function updateVersionDetails(App $app, AppVersion $version): void
{
    $defaultLocale = $this->defaultLocaleForCountry($app, $app->origin_country_code ?? 'us');
    $listing = StoreListing::where('app_id', $app->id)
        ->where('locale', $defaultLocale)
        ->orderByDesc('fetched_at')
        ->first();

    $metric = AppMetric::where('app_id', $app->id)
        ->where('version_id', $version->id)
        ->first();

    $version->update([
        'whats_new' => $listing?->whats_new,
        'file_size_bytes' => $metric?->file_size_bytes,
    ]);
}
```

- `whats_new` → `app_store_listings.whats_new` (default locale, en son `fetched_at`)
- `file_size_bytes` → `app_metrics.file_size_bytes` (aynı `version_id`'li ilk satır)
- Her sync sonunda **idempotent şekilde tekrar yazılır** — listing/metric değişmedikçe değer aynı kalır

## Okuyan Yerler

| Yer | Erişim deseni | Not |
|---|---|---|
| `App::versions()` | `hasMany(...)->latest('id')` | Default DESC by id |
| `AppController::show` | `$app->load(['versions', ...])` | Tüm versiyonlar `AppDetailResource`'a serbest bırakılır |
| `AppController::listing` | `$app->versions()->orderByDesc('id')->first()` | Son versiyonu listing filtreleme için kullanır |
| `AppDetailResource::getResourceData` | `$this->resource->versions()->latest()->first()` | Top-level `version` alanı için |
| `AppResource::getResourceData` | `$this->resource->versions()->latest()->first()` | Search/list payload için |
| `AppSearchResultResource` | `$app->versions()->latest()->first()` | Aynı pattern |
| `KeywordController::index` | `$app->versions()->value('id')` | `latest('id')` scope'u sayesinde en son versiyonun id'sini alır |
| `KeywordController::compare` | `$compareApp->versions()->value('id')` | Aynı |
| `ChangeResource` | `app->versions()->where('id', $version_id)->value('version')` ve `where('id', '<', ...)->orderByDesc('id')` | Bir change'in versiyon string'i + bir önceki versiyon string'i |
| `AppSyncer::detectLocaleChanges` | `AppVersion::where(...)->where('id', '<', $currentVersion->id)->orderByDesc('id')` | Bir önceki versiyonla locale diff |
| `AppSyncer::retryFailedItem` | `AppVersion::where('app_id', ...)->orderByDesc('id')->first()` | Son versiyon |
| `DashboardController::index` | `AppVersion::whereIn('app_id', $appIds)->count()` | "Total versions" sayacı |
| `ExplorerController::screenshots/icons` | `orderByDesc('version_id')` `app_store_listings` üstünde | En son listing'in proxy'si olarak version_id |

## API Yüzeyi

`VersionResource` (`/Users/ismail/Projects/opensource/appstorecat/server/app/Http/Resources/Api/App/VersionResource.php`):

```json
{
  "id": 1,
  "version": "1.2.4",
  "release_date": "2026-01-15",
  "whats_new": "...",
  "file_size_bytes": 73822208,
  "created_at": "2026-01-15T10:00:00+00:00"
}
```

- Tek başına endpoint yok — sadece `AppDetailResource.versions[]` içinden döner
- Top-level `AppDetailResource.version` (string) → `latestVersion?->version`
- `AppDetailResource.file_size_bytes` → `latestMetric?->file_size_bytes` (versiyondan değil **metric'ten** okunur)

Web tarafı:

- Type: `/Users/ismail/Projects/opensource/appstorecat/web/src/api/models/appVersion.ts` (Orval generated)
- Alias: `/Users/ismail/Projects/opensource/appstorecat/web/src/api/models/versionResource.ts` → `type VersionResource = AppVersion`
- Tab: `/Users/ismail/Projects/opensource/appstorecat/web/src/components/tabs/VersionsTab.tsx` — version listesi + `release_date` badge + `file_size_bytes` MB formatı + `whats_new` paragrafı
- `Show.tsx`: `versions[0]` = "latest" (backend DESC sıralı döndüğü için); version selector tüm tab'lere `selectedVersion` prop'unu geçirir

## Bağımlı Tablolar

Üç tablo `version_id` üstünden bağlanır; tümünde **`nullOnDelete`** davranışı tanımlı:

| Tablo | Yazım kaynağı | Anlam |
|---|---|---|
| `app_store_listings.version_id` | `AppSyncer::saveListing` | Listing snapshot hangi version canlıyken alındı |
| `app_metrics.version_id` | `AppSyncer::saveMetric` | O günün metric satırı hangi version canlıyken alındı |
| `store_listing_changes.version_id` | `AppSyncer::detectChanges`, `detectLocaleChanges` | Diff hangi version geçişinde algılandı |

`app_metrics` ve `app_store_listings` her ikisi de `(... version_id ...)` üzerine unique constraint koymuyor — sadece `app_store_listings`'te `(app_id, version_id, locale)` unique var.

## Gözlemler & Kokular

### 1. `latest('id')` semver değil insert sırası

`App::versions()` ilişki tanımı `->latest('id')` kullanıyor. `AppDetailResource`, `AppResource`, `AppSearchResultResource`, `KeywordController` hepsi bu sıralamayı **"en son versiyon"** olarak kabul ediyor. Pratikte bu çoğunlukla doğru çalışır çünkü scraper yeni versiyonu store'un duyurduğu sırayla görür — ama:

- Backfill / manuel insert / out-of-order sync senaryolarında **id sırası ≠ release sırası**
- `release_date` index'i var ama hiçbir sorgu bu sütunu sıralama için kullanmıyor
- `AppController::listing` (line 158): yorum yok, "latest" varsayımına dayanıyor
- `detectLocaleChanges` (line 452): `id < $currentVersion->id` — eski versiyon karşılaştırması da insert sırasına bağlı

### 2. `whats_new` çift kaynak

`whats_new` iki yerde yaşıyor:

- `app_versions.whats_new` — default locale (origin country'nin locale'i)
- `app_store_listings.whats_new` — per-locale snapshot

`updateVersionDetails` her sync sonunda `app_versions.whats_new`'i default locale listing'inden **kopyalar**. Bu, denormalize bir cache:

- Listing tarafında değer değiştiğinde, version satırı bir sonraki sync'e kadar stale kalır
- `VersionResource`/`VersionsTab` her zaman `app_versions.whats_new`'i okur — locale picker yok
- Default locale `origin_country_code` üstünden bulunur, kullanıcının görüntülediği locale'le ilgisiz

### 3. `file_size_bytes` çift kaynak

Aynı kolon hem `app_versions` hem `app_metrics`'te:

- `app_metrics.file_size_bytes` — country bazlı, her sync'te yazılır (snapshot)
- `app_versions.file_size_bytes` — `updateVersionDetails` ile **aynı `version_id`'li ilk metric satırından** kopyalanır

`AppDetailResource.file_size_bytes` `$latestMetric?->file_size_bytes`'tan okur (en son `date` sıralı, country-agnostik). `VersionsTab` ise `version.file_size_bytes`'tan okur. **Aynı veri iki farklı yoldan ekrana gelir** — country/version filtresi farklı olduğunda tutarsız değerler üretebilir.

### 4. `version` string için tip yok

`version` `string` olarak saklanıyor. Store'lar tutarsız format döndürebilir (`3.14`, `3.14.0`, `3.14.0-beta`, `v3.14`). Semver karşılaştırma kodu yok — sorting/diff her yerde `id` üstünden yapılıyor.

### 5. `release_date` partial fill

`saveVersion` `firstOrCreate` ile `release_date`'i sadece **yeni satır** açtığında yazıyor. Identity ilk çağrıda `release_date` döndürmediyse (bazı edge case'lerde mümkün), sonraki sync'ler **mevcut satıra geri yazmaz** — release_date null kalır.

### 6. Eager load eksikliği

`ChangeResource::getResourceData` her change için iki ayrı `$app->versions()->where(...)` sorgusu yapıyor (line 43, 46). N change × 2 query = N+1 problemi. `with(['app.versions'])` + collection üzerinde lookup yapılabilir.

### 7. `metrics()` ilişki tanımlı ama kullanılmıyor

`AppVersion::metrics()` HasMany ilişkisi var, ama `updateVersionDetails` ham `AppMetric::where('version_id', $version->id)` query'si yazıyor. Tutarlılık için `$version->metrics()->first()` kullanılabilir.

### 8. OpenAPI'de `updated_at` eksik

OA Schema `created_at` expose ediyor ama `updated_at` yok. `whats_new` ve `file_size_bytes` `updateVersionDetails` ile sonradan değiştiği için `updated_at` aslında istemci için anlamlı bir freshness sinyali.

## Refactor / İyileştirme Fırsatları

- **`App::versions()` sıralamasını netleştir**: ilişki default'unu `->orderByDesc('release_date')->orderByDesc('id')` yap, ya da iki ayrı scope sun (`->latestByRelease()` vs `->latestByInsert()`). Tüketici tarafında niyet açık olur.
- **`whats_new` denormalize'i kaldır**: `VersionResource.whats_new`'i `app_store_listings`'ten compute et, ya da `whats_new` için ayrı `app_version_locales` tablosu aç (per-locale release notes).
- **`file_size_bytes` denormalize'i kaldır**: `VersionResource`'tan `file_size_bytes`'i çıkarıp `app_metrics`'i query'le; ya da `app_metrics.file_size_bytes`'i sil ve sadece `app_versions`'te tut (country bazlı değişim seyrek).
- **`detectLocaleChanges` previous-version sorgusunu güçlendir**: `id < $currentVersion->id` yerine release_date temelli karşılaştırma.
- **`ChangeResource` N+1 düzelt**: controller'da `$apps->load('versions')` + resource içinde collection lookup.
- **`saveVersion` release_date backfill**: `firstOrCreate`'i `updateOrCreate` veya `firstOrCreate` + post-fetch `release_date` null kontrolüne çevir.
- **Semver-aware comparison util**: en azından store'un döndürdüğü string için `version_compare` wrapper'ı; UI'da sıralama göstergesi.
- **`AppVersion::storeListings()` ilişkisi ekle**: `HasMany<StoreListing>` — `ExplorerController`'daki `orderByDesc('version_id')` workaround'larını sadeleştirir.
- **OpenAPI'ye `updated_at` ekle**: client'ların stale version detection yapabilmesi için.
- **`(version_id)` üstünde index**: `app_metrics` ve `app_store_listing_changes` `version_id` üstünde tek başına index'e sahip; `app_metrics` aynı index'i taşıyor mu kontrol et (migration'da `(app_id, date)` ve `(country_code, date)` var, `version_id` standalone yok — `updateVersionDetails` `where('version_id', ...)` full scan'e düşebilir).
