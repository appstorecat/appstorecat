# `apps` Tablosu Audit

Üretim kodundan (`server/`, `web/`) doğrudan okunarak hazırlanmıştır.

## Genel Bakış

`apps` tablosu, AppStoreCat'in store-bağımsız uygulama kataloğunun çekirdeğidir. (platform, external_id) ikilisini benzersiz hale getirip her satıra; kanonik kimlik (display_name/icon), zayıf bağlı meta (publisher/category), keşif (discover) izlerini ve sync döngüsünün durdurma noktalarını (`last_synced_at`, `is_available`) yazar. Üzerine bağlı bütün analitik tablolar (`app_versions`, `app_store_listings`, `app_metrics`, `app_store_listing_changes`, `trending_chart_entries`, `user_apps`, `app_competitors`, `sync_statuses`) bu tablodaki tek bir `id`’ye `cascadeOnDelete` ile zincirlenir.

Yazma yolları iki yöne ayrılır:
- **Discovery** (kullanıcı/storefront tarama, chart sync, publisher import, search): `App::discover()` üzerinden idempotent insert/backfill — `discovered_from`, `discovered_at` damgalar.
- **Identity sync** (kuyruklu `SyncAppJob` → `AppSyncer::syncIdentity`): app kimliğini canlı store cevabına göre yeniler; `last_synced_at` ve `is_available`’ı pipeline’ın geri kalanı için yetkili (authoritative) hale getirir.

Okuma tarafı tüm controller'larda `BaseController::resolveApp($platform, $externalId)` ile (platform, external_id) benzersiz indeksinden geçer.

---

## Şema

Kaynak: `server/database/migrations/2026_04_06_000001_create_apps_table.php`

| Sütun | Tip | Nullable | Default | Yorum |
|---|---|---|---|---|
| `id` | `bigint unsigned` (auto-inc) | Hayır | — | Primary key. |
| `platform` | `tinyint unsigned` | Hayır | — | `1=iOS`, `2=Android`. `App\Enums\Platform` ile haritalı. `external_id` ile birlikte unique. |
| `external_id` | `string` (varchar 255) | Hayır | — | Store ID: iTunes trackId / Play packageName. |
| `publisher_id` | `bigint unsigned` | Evet | — | FK → `publishers.id`. Publisher silinirse null. |
| `category_id` | `bigint unsigned` | Evet | — | FK → `store_categories.id` (primary genre). Kategori silinirse null. |
| `display_name` | `string` | Evet | — | UI'da gösterilen kanonik isim; default-locale store başlığından türetilir. |
| `icon_url` | `text` | Evet | — | Store CDN'den en güncel ikon URL'i. |
| `origin_country_code` | `char(2)` | Hayır | `'us'` | FK → `countries.code`. Keşfedildiği storefront. Identity sync fallback olarak kullanır (`AppSyncer.php:107-108`). |
| `supported_locales` | `json` | Evet | — | BCP-47 locale dizisi. |
| `original_release_date` | `date` | Evet | — | Uygulamanın ilk store yayını. |
| `is_free` | `boolean` | Hayır | `true` | Origin country için son bilinen ücretsizlik durumu. |
| `discovered_from` | `tinyint` | Evet | — | `App\Enums\DiscoverSource`. |
| `discovered_at` | `timestamp` | Evet | — | İlk insert anı. `created_at` ile aynı zaman, ayrı kolon. |
| `last_synced_at` | `timestamp` | Evet | — | Pipeline'ın başarıyla bittiği son an. Staleness sorgusunun pivotu. |
| `is_available` | `boolean` | Hayır | `true` | 404/“not found” geldiğinde false (`AppSyncer.php:115-116`). |
| `created_at` / `updated_at` | `timestamp` | Evet | — | Laravel timestamps. |

---

## Index'ler & FK'lar

Kaynak: `server/database/migrations/2026_04_06_000001_create_apps_table.php:45-52`

| Tür | Kolon(lar) | Not |
|---|---|---|
| UNIQUE | `(platform, external_id)` | Kataloğun semantik PK'sı. Tüm `resolveApp()` çağrıları, `App::discover()` ve `AppRegistrar::ensureExists()` bunu kullanır. |
| INDEX | `(last_synced_at)` | `SyncTrackedCommand::baseQuery()` (line 117-118, 138-140) için. |
| INDEX | `(discovered_from)` | Aktif olarak hiçbir yerde filtre olarak kullanılmıyor — bkz. Gözlemler. |
| INDEX | `(platform, is_available, last_synced_at)` | `SyncTrackedCommand` üçlüsünü tek index'le karşılamak için (compound). |
| FK | `publisher_id` → `publishers.id` | `nullOnDelete`. |
| FK | `category_id` → `store_categories.id` | `nullOnDelete`. |
| FK | `origin_country_code` → `countries.code` | `cascadeOnUpdate`, `restrictOnDelete`. |

---

## Model

Dosya: `server/app/Models/App.php`

`#[Fillable]` (PHP attribute, satır 60-67):
`platform, external_id, publisher_id, category_id, display_name, icon_url, origin_country_code, supported_locales, original_release_date, is_free, discovered_from, discovered_at, last_synced_at, is_available`

Casts (`casts()` method, satır 257-269):
- `platform` → `App\Casts\PlatformCast` (slug/int/enum → int kaydet, geri okurken `Platform` enum)
- `supported_locales` → `array`
- `is_free`, `is_available` → `boolean`
- `original_release_date` → `date`
- `discovered_from` → `DiscoverSource` enum
- `discovered_at`, `last_synced_at` → `datetime`

`HasPlatform` trait (satır 22-29): `Model::platform($value)` scope sağlar, slug/int/enum birleşik normalize eder.

### İlişkiler

| Yön | Hedef | Method | Pivot/Detay |
|---|---|---|---|
| BelongsToMany | `User` | `users()` | `user_apps` pivot, `withPivot('created_at')` |
| BelongsTo | `Publisher` | `publisher()` | `publisher_id` FK |
| BelongsTo | `StoreCategory` | `category()` | `category_id` FK |
| HasMany | `StoreListing` | `storeListings()` | — |
| HasMany | `StoreListingChange` | `storeListingChanges()` | — |
| HasMany | `AppMetric` | `metrics()` | — |
| HasMany | `AppVersion` | `versions()` | `latest('id')` order'lı (newest-first) |
| HasOne | `SyncStatus` | `syncStatus()` | unique FK |
| HasMany | `AppCompetitor` | `competitors()` | `app_id` FK (parent perspective) |

Yardımcılar: `displayName()` (display_name yoksa external_id'yi döner — satır 152-155), `displayIcon()`, `isIos()`, `isAndroid()`, `isTrackedBy(User)`.

---

## Yazan Yerler

### `App::discover(string $platform, string $externalId, array $data, DiscoverSource $source, string $country)`
`server/app/Models/App.php:167-245`
- **Tek idempotent giriş noktası.** Önce `(platform, external_id)` üzerinden bakar (line 169).
- Mevcutsa **selective backfill** (line 171-208): yalnızca boş/değişmiş alanları (`display_name`, `icon_url`, eksik `publisher_id`, eksik `category_id`) günceller. `is_free`, `original_release_date`, `discovered_from`, `discovered_at` **dokunulmaz**.
- Yoksa: `DiscoverSource::isEnabled($platform)` (config gate) geçemezse `null` döner. Geçerse yeni satır oluşturur (line 230-242) — yazılan kolonlar: `platform, external_id, publisher_id, category_id, display_name, icon_url, origin_country_code, is_free, original_release_date, discovered_from, discovered_at`.

### `AppRegistrar::ensureExists(string $externalId, Platform $platform)`
`server/app/Services/AppRegistrar.php:31-36`
- `App::firstOrCreate(['platform' => $platform->value, 'external_id' => $externalId])`. Hiçbir metadata yazmaz — yalnızca anchor satır. Competitor flow'unun “track etmeden referans aç” yolu.

### `AppRegistrar::register(User $user, string $externalId, Platform $platform)`
`server/app/Services/AppRegistrar.php:15-24`
- `ensureExists()` çağırır + `user_apps`’a attach eder. UI'daki Track butonu ve MCP `track_app` aracının kullandığı yol.

### `AppSyncer::syncIdentity(App $app, ?SyncStatus)`
`server/app/Services/AppSyncer.php:100-163`
- Pipeline'ın Phase 1'i. Identity fetch başarısız + 404/“not found” → `is_available = false` (line 115-116).
- Başarılı + `is_available` daha önce false ise true'ya yükseltir (line 130-132).
- `update($appData)` (line 160) yazdıkları: `display_name`, `icon_url`, `supported_locales`, `original_release_date`, `is_free`, `publisher_id` (resolve edilirse), `category_id` (resolver üzerinden).
- **`content_rating`, `store_url`, `price_model` de `$appData`'ya konuyor (line 138-140) ama bu kolonlar şemada yok ve `fillable`'da değil → sessizce drop ediliyor.** Bkz. Gözlemler.

### `AppSyncer::syncAll(App $app, ?SyncStatus)`
`server/app/Services/AppSyncer.php:39-88`
- `last_synced_at = now()` set'i: identity başarısız olsa bile (line 59), pipeline başarıyla tamamlandığında da (line 81). Yani staleness her zaman güncellenir.

### `Jobs\Chart\SyncChartSnapshotJob`
`server/app/Jobs/Chart/SyncChartSnapshotJob.php:117`
- Her chart entry için `App::discover($platform, $entry['app_id'], $entry, DiscoverSource::Trending, $countryCode)`. Throttle'lı (Redis), unique-for 3600s.

### `Jobs\Chart\FetchChartSnapshotJob`
`server/app/Jobs/Chart/FetchChartSnapshotJob.php:82`
- Aynı `App::discover(... DiscoverSource::Trending ...)` çağrısı; throttle yok, manual fetch yolunda kullanılır.

### `AppSearchController::__invoke`
`server/app/Http/Controllers/Api/V1/App/AppSearchController.php:59`
- Her search hit'i için `App::discover($platform, $result['app_id'] ?? '', [...], DiscoverSource::Search, $countryCode)`. `$result['app_id']` boşsa boş `external_id` ile çağrılır (bkz. Gözlemler).

### `PublisherController::storeApps`
`server/app/Http/Controllers/Api/V1/PublisherController.php:176-184`
- Yayıncının tüm uygulamaları için `App::discover(..., DiscoverSource::PublisherApps)` — side-effect olarak katalogu zenginleştirir.

### `PublisherController::groupByPublisher`
`server/app/Http/Controllers/Api/V1/PublisherController.php:264-272`
- Publisher search içinde her item için `App::discover(..., DiscoverSource::Search)`.

### `AppController::store/track/untrack`
`server/app/Http/Controllers/Api/V1/App/AppController.php:84-95, 274-282, 299-310`
- `store`: `AppRegistrar::register` → satır oluşturur + attach.
- `track`: yalnızca `user_apps`’a attach (apps tablosuna yazmaz; satır zaten var olmalı).
- `untrack`: detach + `AppCompetitor` temizliği (apps tablosuna yazmaz).

### `AppController::sync / syncStatus`
`server/app/Http/Controllers/Api/V1/App/AppController.php:192-258`
- Doğrudan `apps`’a yazmaz — `SyncAppJob` dispatch eder ve `SyncStatus`’u set eder.

---

## Okuyan Yerler

| Controller / Sınıf | Konum | Hangi sütun/yön | Index/Scope |
|---|---|---|---|
| `BaseController::resolveApp` | `Api/BaseController.php:29-33` | `(platform, external_id)` | UNIQUE `(platform, external_id)` |
| `AppController::index` | `App/AppController.php:51-67` | `display_name`, `external_id` (LIKE); `$user->apps()` üzerinden tracked filtresi + `platform` scope | Pivot `user_apps` üzerinden join; LIKE → full scan |
| `AppController::show` | `App/AppController.php:111-136` | `last_synced_at` (staleness), `is_available` dolaylı; ilişkiler: `storeListings`, `versions`, `storeListingChanges`, `syncStatus`, ad-hoc `competitors` set | `resolveApp` UNIQUE; sonra eager-load |
| `AppController::listing` | `App/AppController.php:154-176` | `last_synced_at` (staleness check) | — |
| `AppRankingController::index` | `App/AppRankingController.php:35-86` | `apps.id` (join); platform `trending_charts.platform` üzerinden alınıyor — `apps`'tan platform filtrelenmiyor | — |
| `KeywordController::index` | `App/KeywordController.php:57-112` | `resolveApp` ile id, sonra `StoreListing`ler okunur | UNIQUE `(platform, external_id)` |
| `KeywordController::compare` | `App/KeywordController.php:149-157` | `App::whereIn('id', $compareAppIds)` + `versions` ilişkisi; **`$a->name` okunuyor (line 154) — model'de `name` accessor'u yok**. | PK |
| `RatingController::*` | `App/RatingController.php:39-201` | `resolveApp`; sonra `app_metrics` okunur | UNIQUE |
| `CompetitorController::index` | `App/CompetitorController.php:44-55` | `resolveApp` + `isTrackedBy` | UNIQUE + pivot |
| `CompetitorController::all` | `App/CompetitorController.php:78-138` | `$user->apps()` (platform scope, eager `publisher`/`category`); ardından parent app'ler için `display_name` LIKE eşleştirmesi | LIKE → full scan |
| `CompetitorController::store` | `App/CompetitorController.php:160-195` | `resolveApp` + `AppRegistrar::ensureExists` (rakip için) | UNIQUE |
| `PublisherController::index` | `V1/PublisherController.php:84-94` | `$user->apps()->pluck('apps.id')`; Publisher'lar `apps` üzerinden withCount + whereHas | join |
| `PublisherController::show` | `V1/PublisherController.php:115-128` | `$publisher->apps()->whereIn('id', $userAppIds)` | FK |
| `PublisherController::storeApps` | `V1/PublisherController.php:152-195` | `$user->apps()->pluck('apps.external_id')` + side-effect `App::discover` | — |
| `DashboardController::__invoke` | `V1/DashboardController.php:48-73` | `$user->apps()->pluck('apps.id')`, `$user->apps` (collection); **`$c->app->name` okunuyor (line 61) — accessor yok**. | — |
| `ChangeMonitorController::apps/competitors` | `V1/ChangeMonitorController.php:41-127` | `apps.id` join'leri; `display_name` LIKE (line 117); `platform()` scope (line 115) | LIKE → full scan |
| `ExplorerController::screenshots` | `V1/ExplorerController.php:45-81` | `App::query()` tüm-katalog; `category_id` filtresi, `display_name` (dolaylı `storeListings.title` üzerinden), `last_synced_at` sort | `category_id` filtresi indexsiz |
| `ExplorerController::icons` | `V1/ExplorerController.php:110-142` | `App::query()` tüm-katalog; `display_name` LIKE (line 134), `category_id` filtre, `discovered_at` sort | `discovered_at` indexsiz, `display_name` LIKE indexsiz |
| `SyncTrackedCommand` | `Console/Commands/Apps/SyncTrackedCommand.php:117-191` | `is_available`, `platform`, `last_synced_at` filtreleri; `whereHas('users')` | Compound `(platform, is_available, last_synced_at)` — tasarlanan use case |
| `AppResource` | `Resources/Api/App/AppResource.php:27-56` | `displayName()`, `displayIcon()`, `metrics()->orderByDesc('date')->first()`, `versions()->latest()->first()` | N+1 riski (her satır için 2 sorgu) |
| `AppDetailResource` | `Resources/Api/App/AppDetailResource.php:39-110` | Aynısı + `latestUnavailableCountries()` (group by + whereIn) | — |

---

## API Yüzeyi

Tüm aşağıdaki endpoint'ler `auth:sanctum` ile korunur (kaynak: `server/routes/api.php`).

| HTTP | Path | Controller | Resource | Web hook (Orval) |
|---|---|---|---|---|
| GET | `/v1/apps` | `AppController::index` | `AppResource` (collection) | `useListApps` |
| POST | `/v1/apps` | `AppController::store` | `AppDetailResource` | `useStoreApp` |
| GET | `/v1/apps/search` | `AppSearchController` | `AppSearchResultResource` | `useSearchApps` |
| GET | `/v1/apps/{platform}/{externalId}` | `AppController::show` | `AppDetailResource` | `useShowApp` |
| GET | `/v1/apps/{platform}/{externalId}/listing` | `AppController::listing` | `ListingResource` | `useAppListing` |
| POST | `/v1/apps/{platform}/{externalId}/sync` | `AppController::sync` | `SyncStatusResource` | `useSyncApp` |
| GET | `/v1/apps/{platform}/{externalId}/sync-status` | `AppController::syncStatus` | `SyncStatusResource` | `useAppSyncStatus` |
| POST | `/v1/apps/{platform}/{externalId}/track` | `AppController::track` | 204 | `useTrackApp` |
| DELETE | `/v1/apps/{platform}/{externalId}/track` | `AppController::untrack` | 204 | `useUntrackApp` |
| GET | `/v1/apps/{platform}/{externalId}/competitors` | `CompetitorController::index` | `CompetitorResource` | `useListCompetitors` |
| POST | `/v1/apps/{platform}/{externalId}/competitors` | `CompetitorController::store` | `CompetitorResource` | `useStoreCompetitor` |
| DELETE | `/v1/apps/{platform}/{externalId}/competitors/{competitor}` | `CompetitorController::destroy` | 204 | `useDeleteCompetitor` |
| GET | `/v1/apps/{platform}/{externalId}/keywords` | `KeywordController::index` | `KeywordDensityResource` (paginated) | `useAppKeywords` |
| GET | `/v1/apps/{platform}/{externalId}/keywords/compare` | `KeywordController::compare` | `KeywordCompareResource` | `useCompareKeywords` |
| GET | `/v1/apps/{platform}/{externalId}/rankings` | `AppRankingController::index` | `AppRankingResource` | `useListAppRankings` |
| GET | `/v1/apps/{platform}/{externalId}/ratings/summary` | `RatingController::summary` | `RatingSummaryResource` | `useGetRatingSummary` |
| GET | `/v1/apps/{platform}/{externalId}/ratings/history` | `RatingController::history` | `RatingHistoryPointResource` | `useGetRatingHistory` |
| GET | `/v1/apps/{platform}/{externalId}/ratings/country-breakdown` | `RatingController::countryBreakdown` | `RatingByCountryResource` | `useGetRatingCountryBreakdown` |
| GET | `/v1/competitors` | `CompetitorController::all` | `CompetitorGroupResource` | `useListAllCompetitors` |
| GET | `/v1/dashboard` | `DashboardController` | `DashboardResource` | `useDashboard` |
| GET | `/v1/changes/apps` | `ChangeMonitorController::apps` | `ChangeResource` (paginated) | `useAppChanges` |
| GET | `/v1/changes/competitors` | `ChangeMonitorController::competitors` | `ChangeResource` (paginated) | `useCompetitorChanges` |
| GET | `/v1/explorer/screenshots` | `ExplorerController::screenshots` | `ExplorerScreenshotResource` | `useExploreScreenshots` |
| GET | `/v1/explorer/icons` | `ExplorerController::icons` | `ExplorerIconResource` | `useExploreIcons` |
| GET | `/v1/publishers/{platform}/{externalId}/store-apps` | `PublisherController::storeApps` | `StoreAppResource` | `usePublisherStoreApps` |

Web tarafı tip eşlemeleri: `web/src/api/models/app.ts` (`App`), `appResource.ts` (`AppResource = App & {...}`), `appDetailResource.ts` (`AppDetailResource = App & {...}`). Orval üretiyor; kaynak Swagger annotations (`App\Models\App` + `App\Http\Resources\*`).

---

## Bağımlı Tablolar (apps.id'e FK)

Hepsi `cascadeOnDelete` — `apps` satırı silinince zincir baştan aşağı silinir.

| Tablo | Kolon | Migration | OnDelete |
|---|---|---|---|
| `user_apps` | `app_id` | `2026_04_06_000001b_create_user_apps_table.php:16-18` | cascade |
| `app_versions` | `app_id` | `2026_04_06_000002_create_app_versions_table.php:13-15` | cascade |
| `app_store_listings` | `app_id` | `2026_04_06_000003_create_app_store_listings_table.php:13-15` | cascade |
| `app_store_listing_changes` | `app_id` | `2026_04_06_000004_create_app_store_listing_changes_table.php:13-15` | cascade |
| `app_metrics` | `app_id` | `2026_04_06_000005_create_app_metrics_table.php:13-15` | cascade |
| `app_competitors` | `app_id` ve `competitor_app_id` | `2026_04_06_000007_create_app_competitors_table.php:16-21` | cascade (her iki yön) |
| `trending_chart_entries` | `app_id` | `2026_04_10_000001_create_chart_tables.php:38-40` | cascade |
| `sync_statuses` | `app_id` (unique) | `2026_04_20_170000_create_sync_statuses_table.php:13-15` | cascade |

Pratikte bir app silindiğinde 8 tablo tetiklenir; production'da hard-delete neredeyse yapılmaz (yerine `is_available=false` flag'i tercih edilir).

---

## Gözlemler & Kokular

### 1. `App` modelinde olmayan `name` attribute’una erişim (bug)
- `KeywordController::compare` `App/KeywordController.php:154` → `'name' => $a->name`. `App`'te ne `name` kolonu ne accessor var; sadece `displayName()` metodu mevcut (`App.php:152-155`). Sonuç: payload'ta `name` her zaman `null` döner.
- `DashboardController::__invoke` `V1/DashboardController.php:61` → `'app_name' => $c->app->name`. Aynı sorun: dashboard'daki son değişiklik listesi `app_name: null` döner.

### 2. `AppSyncer::syncIdentity` şemada olmayan kolonlara yazmaya çalışıyor
`AppSyncer.php:137-140` `collect($data)->only([...])` ile `content_rating`, `store_url`, `price_model` alanlarını `$appData`'ya alıyor; sonra `$app->update($appData)` (line 160). Bu üç kolon ne migration’da var ne de `App::$fillable`’da. Eloquent fillable koruması sayesinde sessizce drop ediliyor — ama niyet açık değil: ya bu kolonlar planlanıp eklenmemiş ya da AppSyncer eski şemadan kalmış kod tutuyor.

### 3. `discovered_from` indexi var ama hiçbir sorgu kullanmıyor
Migration `index('discovered_from')` (line 47) tanımlıyor. `grep -rn discovered_from` taraması yalnızca yazma yollarını (`App::discover`) gösteriyor — okuma tarafında filtre/agg yok. Index dead.

### 4. AppResource her satır için N+1
`AppResource.php:29-30`:
```php
$latestMetric = $this->resource->metrics()->orderByDesc('date')->first();
$latestVersion = $this->resource->versions()->latest()->first();
```
`AppController::index` (line 64) `get()` ile çağrılıp `AppResource::collection()`’a veriliyor. Eager-load yok. Tracked liste başına 2N sorgu ek.

### 5. `AppController::show` listing/versions/changes eager-load ediliyor ama `metrics` ve `versions` resource katmanında tekrar sorgulanıyor
`AppController.php:121` → `load(['storeListings', 'versions', 'storeListingChanges', 'syncStatus'])`. `AppDetailResource.php:41-42` yine `metrics()->orderByDesc('date')->first()` ve `versions()->latest()->first()` çağırıyor. `versions` zaten yüklü; resource ilişkiyi yeniden tetikliyor → ek query.

### 6. `AppSearchController` boş `external_id` ile `App::discover` çağırabilir
`AppSearchController.php:59` → `App::discover($platform, $result['app_id'] ?? '', ...)`. `app_id` yoksa `''` geçiyor. `App::discover()` boş external_id için guard etmiyor; eğer `DiscoverSource::Search` config'te etkinse `('ios', '')` veya `('android', '')` ile satır oluşturabilir. UNIQUE `(platform, external_id)` index ikinci denemede patlamaz ama tek bir “boş app” satırı kalır.

### 7. `App::discover` backfill mantığı `discovered_from` ve `discovered_at`’i hiç güncellemiyor
Satır 171-208: existing branch yalnızca `display_name`, `icon_url`, `publisher_id`, `category_id` backfill ediyor. Yeni bir kaynak (örn. ilk Search ile keşfedildi, sonradan Register’la track edildi) için ne `discovered_from` ne `discovered_at` revize ediliyor. Bu kasten olabilir (audit trail) ama dokümante değil ve sezgi-aykırı.

### 8. `is_free` ülke bazında değişebilir ama tek bir boolean
Migration yorumu: “True if the app is listed free in its origin country”. Identity sync (`AppSyncer.php:138`) `'is_free'`'u tüm uygulamada yazıyor. `app_metrics` zaten ülke bazlı `price`/`currency` tutuyor; `apps.is_free` ile çakışan tek-doğruluk noktası riski var.

### 9. `display_name` LIKE filtreleri full-scan
`AppController::index` (line 60), `CompetitorController::all` (line 98-99), `ChangeMonitorController` (line 117), `ExplorerController::icons` (line 134). `display_name` üzerinde index yok; data set büyüdükçe sıralama+filtre yavaşlar.

### 10. `ExplorerController` `category_id` filtresi indexsiz
`ExplorerController.php:67, 130` → `where('category_id', ...)`. Migration’da `category_id` üzerinde standalone index yok (yalnızca FK constraint). Discovery sayfaları büyük katalogla yavaşlar.

### 11. `AppController::sync` tarafında ufak yarış koşulu
`AppController::ensureSyncJob` (line 235-258) `SyncStatus`’ü `forceFill([... 'status' => QUEUED ...])` ile yazıp `dispatch` ediyor. `ShouldBeUnique` sayesinde job-katmanı çift dispatch'i engelliyor ama iki paralel istek arasında `STATUS_PROCESSING` kontrolü (line 243) ile dispatch arasında yarış var. Pratikte etkisi minimal — sadece SyncStatus’ün `queued`’a iki kez basılması.

### 12. `discovered_at` ile `created_at` kasıtlı ayrılmış ama nadiren ayrışıyor
`App::discover` (line 230-242) ikisini de `now()` ile yazıyor. Migration yorumu “separate from created_at for clarity” diyor ama pratik kullanım yok. Tek bir kolon yetebilir.

### 13. `AppCompetitor` benzersizlik koruması API katmanında değil
`CompetitorController::store` (line 185-190) doğrudan `AppCompetitor::create` çağırıyor — aynı `(user_id, app_id, competitor_app_id)` ikilisinin tekrar eklenmesini engelleyen DB constraint veya kontrol görünmüyor (bu tablo audit dışı ama `apps` ile yakından çalışıyor).

### 14. `last_synced_at` identity başarısızlığında bile güncelleniyor
`AppSyncer.php:59`: identity boşsa bile `$app->update(['last_synced_at' => now()])`. Bu, “app erişilemez” durumu için staleness baskısını azaltıyor (tekrar deneme kuyruğa girmiyor). Niyetli görünüyor (sürekli 404 alan app'in pipeline'ı tıkamaması için) ama `SyncTrackedCommand::baseQuery` zaten `is_available=true` filtresi koyuyor — duruma göre tekrar tartışmaya değer.

### 15. iOS `external_id` regex'i app paket adlarını dışlıyor
Route `apps/{platform}/{externalId}` regex’i `[a-zA-Z0-9._]+` (`routes/api.php:42`). iOS trackId'leri saf rakam, Play packageName'leri `com.foo.bar` formatında — uyumlu. Ancak Play tarafında nadir görülen tire (`-`) içeren paket adları regex'e takılıp 404 alır. Publisher tarafı `[a-zA-Z0-9._%+ -]+` kullanıyor — tutarsızlık.

### 16. `AppDetailResource::latestUnavailableCountries` her show isteğinde 2 ek sorgu çalıştırıyor
`AppDetailResource.php:94-110`: önce `MAX(id)` group-by, sonra `whereIn`. Kullanım payı sınırlı; ama `app_metrics` üzerinde compound index `(app_id, country_code, date)` haricinde gözükmüyorsa büyük app'ler için maliyetli.

---

## Refactor / İyileştirme Fırsatları

1. **Bug fix #1**: `KeywordController::compare` line 154 → `$a->displayName()`; `DashboardController` line 61 → `$c->app->displayName()` (ya da `display_name`).
2. **Bug fix #2**: `AppSyncer::syncIdentity` ya bu üç alanı (`content_rating`, `store_url`, `price_model`) için kolon ekleyip fillable’a koy, ya da `only([...])` listesinden çıkar — şu anki kod sessizce iş kaybediyor.
3. **N+1 düzeltme**: `AppResource`’u tek bir `latestMetric`/`latestVersion` eager-load deseni ile çalıştır; `AppController::index` çağrısında `->with(['publisher', 'category'])` zaten yapılmıyor — eklenirse `AppResource` içindeki publisher/category dolaylı sorguları da düşer.
4. **`display_name` araması**: FULLTEXT index veya en azından `(platform, display_name)` prefix index — Explorer, Apps, Changes, Competitors ekranları bundan faydalanır.
5. **`category_id` standalone index** — Explorer query'leri için.
6. **`discovered_at` dead-code**: Ya `created_at` ile birleştir ya da `App::discover` backfill branch'inde de set et (re-discovery anlamlıysa).
7. **`discovered_from` index'ini ya kullan ya at**: Analitik dashboard'da “discovery breakdown” göstermek için bir endpoint açılırsa kullan; aksi halde drop et.
8. **`apps.is_free`’u kaldırıp `app_metrics`’ten beslemek**: Veri çakışmasını ortadan kaldırır. UI'lar zaten `latestMetric`’i çekiyor.
9. **`App::discover` boş `external_id` guard**: `if ($externalId === '') return null;` ekle — `AppSearchController` line 59 ve future caller’lar için savunma.
10. **Route regex tutarlılığı**: `apps/{externalId}` ile `publishers/{externalId}` regex’lerini hizala (`[a-zA-Z0-9._-]+` yeterli).
11. **`AppController::show` resource’u doğrudan ilişkiyi yeniden sorgulamasın**: `versions()->latest()->first()` yerine `$this->whenLoaded('versions') ? $this->versions->first() : ...`. `AppController` zaten `versions` ilişkisini eager yüklüyor.
12. **`AppRegistrar::ensureExists`**: `firstOrCreate` race koşulu için DB-level unique constraint (mevcut) güvence veriyor; ama `discovered_from` ve `discovered_at` create branch’inde set edilmiyor → MCP veya competitor flow'undan gelen yeni satırlar “bilinmeyen kaynak”ta kalıyor. `DiscoverSource::Register` ile bir bayrak basmak daha temiz.
