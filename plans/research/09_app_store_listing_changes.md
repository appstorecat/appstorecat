# `app_store_listing_changes` — Tablo Auditi

## Genel Bakış

`app_store_listing_changes` tablosu, bir `App`'in mağaza listing alanlarındaki değişiklikleri tutan **diff event log**'udur. `AppSyncer`'ın bir sync turunda iki ardışık snapshot arasında fark tespit ettiği her alan (locale × field) bir satıra yazılır. Tablo append-only (idempotency'yi `firstOrCreate` sağlıyor) ve hiçbir aşamada güncellenmiyor. Update Timeline / Change Monitor feed'leri, dashboard'daki "recent changes" widget'ı ve `App` detail sayfasındaki `changes` ilişkisi tamamen bu tablodan beslenir.

## Şema

Migration: `server/database/migrations/2026_04_06_000004_create_app_store_listing_changes_table.php`

| Kolon | Tip | Notlar |
|---|---|---|
| `id` | `bigint` PK | auto-increment |
| `app_id` | `foreignId` → `apps.id` | `cascadeOnDelete` — app silinince tüm event'ler düşer |
| `version_id` | `foreignId?` → `app_versions.id` | `nullOnDelete`; belirli bir version'a bağlı değilse null |
| `locale` | `string(10)` | BCP-47 (örn. `en-US`) |
| `field_changed` | `string` | `title` / `subtitle` / `description` / `whats_new` / `locale_added` / `locale_removed` (gerçekte yazılanlar) |
| `old_value` | `text?` | Önceki değer (array'ler için stringified JSON yazılması niyetlenmiş; bkz. Kokular) |
| `new_value` | `text?` | Yeni değer |
| `detected_at` | `timestamp` | Diff tespit anı; yeni snapshot'ın `fetched_at`'i ile aynı dakika |
| `created_at` / `updated_at` | `timestamp` | Laravel default |

## Index'ler & FK'lar

- `(app_id, detected_at)` — app-scope chronological feed (controller `orderByDesc('detected_at')` + `whereIn('app_id', …)`).
- `(app_id, field_changed, detected_at)` — app + field filtresi için composite index.
- `version_id` — versiyon detayı join'leri için.
- FK: `app_id` cascade, `version_id` nullOnDelete.

Tabloda `(field_changed)` veya `(detected_at)` tek başına index yok; `(app_id, …)` prefix'i her sorguda mevcut olduğu için yeterli.

## Model

`server/app/Models/StoreListingChange.php`

- Tablo: `app_store_listing_changes` (sınıf adı plural'a uymadığı için açıkça override).
- Fillable: `app_id`, `version_id`, `locale`, `field_changed`, `old_value`, `new_value`, `detected_at`.
- Casts: `detected_at => datetime` (sadece bu — `old_value`/`new_value` her zaman string).
- İlişkiler: `app()` BelongsTo.
- OpenAPI `#[OA\Schema]` attribute'u `StoreListingChange` schema'sını export ediyor.
- Ters yön: `App::storeListingChanges()` → `HasMany` (`server/app/Models/App.php:115`).

## Yazan Yerler

Yazıcı tek modül: `app/Services/AppSyncer.php`. İki path var, ikisi de `firstOrCreate` ile idempotent.

### `AppSyncer::detectChanges(App, StoreListing $existing, array $newData, ?AppVersion $version)`

- Çağıran: `AppSyncer::saveListing()` — yeni snapshot kaydedilmeden önce.
- Tetiklenme guard'ı (`AppSyncer.php:263-271`): `existing` mevcut **ve** `version != null` **ve** `existing.version_id != null` **ve** `existing.version_id !== version.id` **ve** `existing.checksum !== checksum`. Aynı versiyonun iki kez upsert edildiği partial scrape turlarında phantom change yazılmaması için bilinçli olarak konmuş.
- Karşılaştırılan field set'i (hardcoded):

  | field_changed | old_value kaynağı | new_value kaynağı |
  |---|---|---|
  | `title` | `existing->title` | `$newData['title'] ?? ''` |
  | `subtitle` | `existing->subtitle` | `$newData['subtitle'] ?? null` |
  | `description` | `existing->description` | `$newData['description'] ?? ''` |
  | `whats_new` | `existing->whats_new` | `$newData['whats_new'] ?? null` |

- Uniqueness key (`firstOrCreate`): `(app_id, version_id, locale, field_changed)`. Aynı turun ikinci pass'ında yeniden çağrılırsa duplicate event yazılmaz.

### `AppSyncer::detectLocaleChanges(App, ?AppVersion $currentVersion)`

- Çağıran: `AppSyncer` sync akışı (line 76).
- Guard: hem `currentVersion` hem de `previousVersion` (id'si daha küçük olan en son `AppVersion`) gerekli.
- `StoreListing` tablosundaki iki versiyonun `locale` set'leri farklanıp `array_diff` ile karşılaştırılır.
- Yazılan field_changed değerleri:

  | field_changed | old_value | new_value |
  |---|---|---|
  | `locale_added` | `null` | yeni listing'in `title` alanı |
  | `locale_removed` | önceki listing'in `title` alanı | `null` |

- Locale event'leri her zaman `version_id = currentVersion.id` ile yazılır (locale_removed bile).

### Yazılmayan field'lar

Migration comment'i ve UI/Swagger enum'ları **`screenshots`**, **`icon_url`**, **`price`** field_changed değerlerini öngörüyor; ancak `AppSyncer`'da bunları yazan kod **yok**. Tabloya bugün sadece `title`, `subtitle`, `description`, `whats_new`, `locale_added`, `locale_removed` (6 değer) giriyor.

## Okuyan Yerler

1. **`ChangeMonitorController::apps()`** (`/api/v1/changes/apps`) — kullanıcının tracked (non-competitor) app ID'lerine scope'lanmış feed.
2. **`ChangeMonitorController::competitors()`** (`/api/v1/changes/competitors`) — kullanıcının tracked app'lerine bağlı competitor app ID'lerine scope'lanmış feed.
3. **`DashboardController::__invoke()`** — son 5 değişiklik (`limit(5)`) ve `total_changes` count widget.
4. **`AppController::show()`** — `App` modelini `'storeListingChanges'` ilişkisiyle eager-load eder, `AppDetailResource` üzerinden `changes` array'i olarak döner.
5. **`AppDetailResource`** — `whenLoaded('storeListingChanges', …)` ile inline transform; `version_id`, `locale`, `field_changed`, `old_value`/`new_value` (screenshots null'lanmış), `detected_at`.
6. **`ChangeResource`** — Change Monitor feed satırı; app özetini ve önceki/şimdiki versiyon string'lerini hesaplar.

## API Yüzeyi

İki endpoint, aynı `buildResponse()` yardımcı metoduna delege ediyor.

| Query param | Tip / Enum | Davranış |
|---|---|---|
| `per_page` | `int`, default 50 | Sayfa boyutu (üst sınır validation Form Request'te). |
| `page` | `int`, default 1 | Standart Laravel paginator. |
| `field` | enum: `title` / `subtitle` / `description` / `whats_new` / `screenshots` / `locale_added` / `locale_removed` | `where('field_changed', …)` — Swagger enum'ı tabloda hiç yazılmayan `screenshots`'ı da kabul ediyor. |
| `platform` | enum: `ios` / `android` | `whereHas('app', fn ($a) => $a->platform(...))`. |
| `search` | `string`, ≤ 100 | App display name `LIKE %term%`. |
| `app_id` | `int` | Scope app set'inden tek app'e indirir; scope dışında ise sonuç boş. |

Sıralama her zaman `detected_at DESC`. Response zarfı `PaginatedChangeResponse` + `meta_ext.has_scope_apps` (scope set'i boşsa frontend boş-durum mesajını ayırt ediyor).

Web tüketicileri:

- `web/src/pages/changes/AppChanges.tsx` — `<ChangesFeedPage mode="tracked" />` shim.
- `web/src/pages/changes/CompetitorChanges.tsx` — `<ChangesFeedPage mode="competitors" />` shim.
- `web/src/components/changes/ChangesFeedPage.tsx` — filtre bar, gruplama (`groupChanges`), tarih bucket'ları (`bucketByDateSection`), `keepPreviousData` ile load-more.
- `web/src/api/endpoints/change-monitor/change-monitor.ts` — Orval-generated `useAppChanges` / `useCompetitorChanges` hook'ları.

## Bağımlı Tablolar

Hiçbir tablo bu tabloya FK ile referans vermiyor — kendisi terminal event log. `apps` cascade silmesi tüm satırları temizler, `app_versions` silmesi `version_id`'yi null'a düşürür ama satırı korur (locale değişiklikleri yaşamaya devam eder).

## Gözlemler & Kokular

1. **`screenshots`/`icon_url`/`price` writer yok.** Migration comment'i ve UI dropdown'u bu field'ları vaat ediyor; `detectChanges` set'i ise dört string field ile sınırlı. Kullanıcı "Screenshots" filtresini seçtiğinde feed her zaman boş döner. Comment ile davranış arasında contract drift var.
2. **Defensive null'lama.** Hem `ChangeResource.php:50-51` hem `AppDetailResource.php:78-79` `field_changed === 'screenshots'` ise `old_value`/`new_value`'yu null'lıyor. Çünkü gelecekte stringified JSON yazılması niyetlenmiş ama validate edilmeden frontend'e döküldüğünde patlama riski var. Bugün ölü kod gibi görünüyor (writer yok) ama tablo manuel doldurulursa devreye girer.
3. **`old_value` / `new_value` TEXT'inin büyümesi.** Description için her diff iki tam description metnini tutar. Aktif locale'leri çok olan bir app sık sık description güncellerse satır başına onlarca KB → tablo lineer büyür.
4. **Retention politikası yok.** Hiçbir job / scheduler eski event'leri silmiyor. `DashboardController::__invoke()` `total_changes` için tam count alıyor (büyüyen sayfa).
5. **Casting eksik.** `screenshots` için JSON yazılması niyetlenmiş ama model'de cast yok; bugünkü writer set'i için zararsız ama yarın eklenirse her okuyucunun manuel `json_decode` etmesi gerekecek.
6. **`detectLocaleChanges` ile `detectChanges` semantik çakışması.** Bir locale yeni eklendiğinde `locale_added` event'i yazılır; aynı sync turunda `saveListing()` o locale için `existing` bulamayacağı için `detectChanges` çalışmaz — title/subtitle/description için ayrı event yazılmaz. Frontend "Title değişti" beklerken sadece "Locale Added" görür.
7. **`version_id` her zaman doğru değil.** `detectChanges` non-iOS akışlarında `version` null gelirse `version_id = null` yazılır; `ChangeResource::version` / `previous_version` alanları null döner. Android tarafında bu hat sık.
8. **Platform için index yok.** Composite index `(app_id, field_changed, detected_at)` field filtresi için var, ama `platform` filtresi `whereHas('app', …)` ile `apps` tablosuna join açıyor. Büyük feed'de N+1 değil ama join cost var; `apps.platform` üzerinde mevcut index'e güveniyor.
9. **`search` LIKE prefix'siz.** `display_name LIKE '%term%'` — index'siz scan. Az kullanıcı senaryosunda tolere edilebilir.
10. **`firstOrCreate` unique key constraint yok.** Idempotency'yi yalnız uygulama katmanı sağlıyor; iki paralel sync race condition'da duplicate yazabilir. Production'da pratikte queue platform-separated olduğu için aynı app'in iki sync'i paralel çalışmıyor, ancak DB-level garanti yok.
11. **`whats_new` her version'da değişir.** "Bug fixes" tarzı versiyon notları her release'de yeniden yazıldığı için tablonun en gürültülü field'ı olmaya aday. Feed'de noise yapıyor olabilir — UI bu field için badge sayımı / collapse stratejisi göstermiyor.

## Refactor / İyileştirme Fırsatları

1. **Field whitelist'i tek noktaya çek.** `AppSyncer::detectChanges` set'i, `ChangeMonitorController` Swagger enum'u, `useChangesFilters` FIELD_OPTIONS ve migration comment'i drift halinde. Backend tarafında `StoreListingChange::TRACKED_FIELDS` sabiti tanımlanıp her üç yere oradan beslenmesi (Swagger enum reflection ile) drift'i durdurur.
2. **`screenshots` / `icon_url` writer'ı ya ekle ya kaldır.** Eğer karar tutmak ise `saveListing`'de `screenshots` array fark hesabı (`array_diff` + JSON encode) eklenmeli; aksi halde UI dropdown'unda göstermek false advertising.
3. **Retention job.** `DeleteOldStoreListingChanges` scheduled command (örn. 365 gün'den eski + ana version'dan eski olanlar). Aksi takdirde tablo append-only olarak büyür ve dashboard count widget'ı yıllar içinde anlamını yitirir.
4. **Archive partition.** Yıl bazlı archive table (`app_store_listing_changes_2025`) — büyük deployment'ta `detected_at` üzerinde MySQL native partitioning daha temiz olur.
5. **Unique index ekle.** `(app_id, version_id, locale, field_changed)` üzerine DB-level unique → `firstOrCreate` race condition güvenliği.
6. **`platform` index'i join eliminasyonu.** Sık platform filtresi varsa `apps.platform`'u `app_store_listing_changes` üzerine denormalize edip `(platform, detected_at)` index'i eklemek feed sorgusunu join'siz çevirir. Tradeoff: write path'te platform tutarlılığı.
7. **`old_value` / `new_value` için `mediumtext` veya ayrı blob tablosu.** Description diff'lerinin 64KB TEXT limitine çarpma riski var.
8. **`field_changed`'i `enum`/`tinyint`'e çevir.** Index seçiciliği artar, disk azalır. Veya en azından app-level enum cast.
9. **`detected_at` ile `created_at` redundancy.** İkisi de timestamp; `detected_at` yoksa `created_at` aynı bilgiyi taşıyor. `timestamps()` çıkarılabilir.
10. **`AppDetailResource` `whenLoaded` inline closure'unu `ChangeResource`'a delege et.** Bugün iki ayrı yerde aynı screenshots null'lama logic'i kopyalanmış — single resource ile DRY.
11. **`ChangeResource::version` / `previous_version` N+1 riski.** Her satır için `$this->resource->app?->versions()->where(…)` query atılıyor. Eager-load + ön hesap (controller'da `app_versions`'ı id map'e dök) feed performansını düzeltir.
12. **Locale event semantiği.** `locale_added` durumunda title/subtitle/description için ayrıca event yazmamak bilinçli olabilir, fakat ürün açısından "yeni locale içinde Türkçe title şu" bilgisi kayboluyor. `detectLocaleChanges`'in `locale_added` event'iyle birlikte ilk listing field'larını `field_changed='title'` olarak yazıp `old_value=null` koyma seçeneği değerlendirilebilir.
