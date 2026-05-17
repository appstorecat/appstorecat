# `store_categories` Tablo Audit'i

## Genel Bakış

`store_categories` tablosu, AppStoreCat'in iOS App Store ve Google Play kategori taksonomilerini tek bir referans tablo altında birleştirir. Tablo **statik / referans** karakterdedir: tek yazıcı `StoreCategorySeeder`'dır, runtime'da yazma yoktur. Tüm scraper / app sync / chart akışları kategoriyi `StoreCategoryResolver` üzerinden **okur** ve `apps.category_id` ile `trending_charts.category_id` foreign key'lerine resolved id yazar.

Toplam 100 satır seedlenir (iOS 42 + Android 58). Self-FK (`parent_id`) Android `GAME` / `FAMILY` ve iOS `Games` altındaki alt türleri ağaca bağlar.

## Şema

Migration: `server/database/migrations/2026_04_06_000000_create_store_categories_table.php`

| Sütun | Tip | Açıklama |
|---|---|---|
| `id` | `bigint unsigned PK` | Birincil anahtar |
| `external_id` | `string nullable` | Mağaza-tarafında genre id (iTunes `genreId`, Play kategori slug'ı). `null` = custom bucket (örn. `All`) |
| `name` | `string` | İnsan okunur ad, İngilizce |
| `slug` | `string` | `Str::slug(name)` sonucu, URL/filter için |
| `platform` | `unsignedTinyInteger` | `Platform` enum (1=iOS, 2=Android), `PlatformCast` ile cast |
| `type` | `string default 'app'` | `app`, `game`, `magazine` — uniqueness scope'una giriyor |
| `parent_id` | `foreignId nullable` | Self-FK → `store_categories.id`; `nullOnDelete` |
| `priority` | `smallInteger default 0` | UI sıralama ağırlığı; chart sync'te `orderByDesc('priority')` |
| `timestamps` | | `created_at`, `updated_at` |

## Index'ler & FK'lar

- **Unique:** `(platform, slug, type)` — aynı slug iOS app + iOS game olarak yan yana var olabilir (örn. iOS'ta `music` app + `music` game).
- **Index:** `(platform, type)`, `(platform, external_id)`, `(platform, parent_id)`.
- **Self-FK:** `parent_id → store_categories.id` (`nullOnDelete`).
- **Bağımlı FK'lar (gelen):**
  - `apps.category_id → store_categories.id` (`nullOnDelete`) — `2026_04_06_000001_create_apps_table.php`
  - `trending_charts.category_id → store_categories.id` (`cascadeOnDelete`) — `2026_04_10_000001_create_chart_tables.php`

Not: `external_id` üstünde **unique constraint yok**, sadece index. `(platform, external_id)` tekilliği veritabanı düzeyinde garanti edilmiyor; pratikte seeder'ın `updateOrCreate` anahtarı bu çiftle çalışıyor.

## Model

`server/app/Models/StoreCategory.php`

- `HasPlatform` trait → `scopePlatform()` ve `normalizePlatform()`.
- `$casts`: `platform => PlatformCast`.
- `$fillable`: `name, slug, platform, external_id, type, parent_id` (`priority` fillable değil — sadece migration default'undan geliyor).
- İlişkiler:
  - `apps(): HasMany<App>` — `category_id` üzerinden.
  - `parent(): BelongsTo<self>` — `parent_id`.
  - `children(): HasMany<self>` — `parent_id` ters yön.
- OA şeması (`#[OA\Schema(schema: 'StoreCategory')]`) — Swagger için.

Tek scope: trait'ten gelen `platform`. Type / parent için dedicated scope yok; sorgular ad-hoc `where('type', ...)` ile yazılıyor (controller, command).

## Seeder Davranışı

`server/database/seeders/StoreCategorySeeder.php` + veri kaynağı `server/database/data/store_categories.json`.

- Dış kaynak: tek JSON dosyası, manuel maintained.
- Akış: her iki platform için `apps` ve `games` listelerini sırayla `updateOrCreate` ile yazar. Eşleştirme anahtarı: `(platform, external_id)`. Güncellenen alanlar: `name, slug, type, parent_id`.
- **Games parent çözümü:** önce platform'a göre `GAMES` parent bucket'ı yüklenir (iOS `external_id='6014'`, Android `external_id='GAME'`). Sonra her game satırı için varsa `parent_key` (Android'de `FAMILY` veya `GAME`) ile spesifik parent çözülür; yoksa platform-default games bucket'ı kullanılır.
- iOS'ta game satırlarında `parent_key` yok — hepsi iOS `GAMES` (`6014`) altına bağlanır.
- Android `FAMILY` ve `GAME` kendileri parent olarak top-level seedleniyor (`parent_key` yok); altlarına `GAME_*` ve `FAMILY_*` çocuklar bağlanıyor.
- `priority` seeder veya JSON tarafından **set edilmiyor** — tüm satırlarda 0 kalıyor.
- `slug` her zaman `Str::slug(name)` ile türetiliyor — JSON'daki `key` alanı (iOS apps'te var) okunmuyor.

### Satır sayıları (JSON kaynaktan)

| Platform | Type | Adet |
|---|---|---|
| iOS | app | 26 (`All` dahil) |
| iOS | game | 16 |
| Android | app | 33 (`All` dahil) |
| Android | game | 25 (`GAME`, `FAMILY` + 23 alt kategori) |
| **Toplam** | | **100** |

`type` değerleri uygulamada sadece `app` ve `game`. Migration/Resource/Request `magazine` enum değerini de bildiriyor ama seeder bu type'ı üretmiyor (iOS'taki `Magazines & Newspapers` `type='app'` olarak yazılıyor).

`All` bucket'ları (iOS apps + Android apps) `external_id = null` ile yazılıyor. Bu bucket chart "overall" snapshot'ları için kullanılıyor (`ChartController` aşağıda).

## Yazan Yerler

`grep -rn "StoreCategory::(create|update|firstOrCreate|updateOrCreate)" server/app` boş döndü.

→ **Runtime'da yazan tek yer yok.** Tüm yazma `database/seeders/StoreCategorySeeder.php` içinde, `updateOrCreate` ile, idempotent. Yani:

- Yeni bir mağaza kategorisi eklenirse JSON + seeder yeniden çalıştırılmadan resolver bu kategoriyi tanımayacak.
- Scraper'ın döndürdüğü `genre_id` / `genre` seed'de yoksa `category_id` `null` kalır + `unknown_categories` kanalına error log düşer.

## Okuyan Yerler

- **`App\Services\StoreCategoryResolver::resolveId()`** — birincil okuma noktası. İki static cache map:
  - `mapByExternalId(platform)`: `external_id => id`, `whereNotNull('external_id')` ile.
  - `mapByName(platform)`: `mb_strtolower(name) => id`, tüm satırlar.
  - Önce external_id, sonra name fallback. Eşleşme yoksa `Log::channel('unknown_categories')->error(...)` ve `null` döner.
- **`App\Models\App::discover()`** (`server/app/Models/App.php:191`, `:222`) — yeni app oluştururken ve eksik `category_id` için backfill ederken `StoreCategoryResolver` çağırıyor.
- **`App\Services\AppSyncer`** (`:153`) — identity sync sırasında `category_external_id` + `category_primary` ile resolver çağırıyor.
- **`App\Http\Controllers\Api\V1\StoreCategoryController::index`** — `platform`, `type` filtreleri; `orderBy('name')`.
- **`App\Http\Controllers\Api\V1\ChartController`** (`:57-59`) — `category_id` query param verilmediğinde **default**: `StoreCategory::platform($platform)->whereNull('external_id')->where('type', 'app')->value('id')` → `All` bucket'ını seçiyor.
- **`App\Console\Commands\Charts\SyncDailyChartsCommand`** (`:53`) — günlük chart job dispatch'i için `platform`+`type='app'`, `orderByDesc('priority')->orderBy('name')`. **`type='game'` kategorileri günlük chart sync'e dahil değil** (yalnız `app` type).
- **`App\Jobs\Chart\FetchChartSnapshotJob`** (`:55`) ve **`SyncChartSnapshotJob`** (`:74`) — scraper'a göndermeden önce `StoreCategory::find($id)?->external_id` ile dış id'yi çözüyor.
- **`App\Models\ChartSnapshot`** — `category_id` fillable ve `category(): BelongsTo<StoreCategory>` ilişkisi.

Resolver cache static property — request lifecycle boyunca taze, queue worker'larında uzun ömürlü olabilir (memory'de tutulur). Veri statik olduğu için bu güvenli; seeder re-run sonrası worker restart gerekir.

## API Yüzeyi

- **Route:** `server/routes/api.php:78` → `GET /store-categories` (auth bearer, `v1` group içinde).
- **Controller:** `App\Http\Controllers\Api\V1\StoreCategoryController` — tek `index` aksiyonu.
- **Request:** `StoreCategoryIndexRequest` — `platform: in:ios,android`, `type: in:app,game,magazine` (her ikisi `sometimes`).
- **Resource:** `StoreCategoryResource` — `id, name, slug, platform, type, external_id, parent_id` döner. `priority`, `children`, `apps_count` döndürmüyor.
- **Pagination yok**, tüm satırlar tek seferde döner (100 satır → kabul edilebilir).
- **Hiyerarşi serialize edilmiyor**: `parent_id` ham id olarak gidiyor, `children` ilişkisi resource'a eklenmemiş. Tüketici (web) bu hiyerarşiyi kullanmıyor.

### MCP

`mcp/src` içinde `StoreCategory` referansı yok — MCP araçları kategori yüzeyi sunmuyor (chart araçları kategoriyi tüketebilir ama kategori list endpoint'i MCP'ye expose edilmemiş).

## Bağımlı Tablolar

| Tablo | Sütun | FK kuralı |
|---|---|---|
| `apps` | `category_id` | `nullOnDelete` — kategori silinirse app `category_id` null'a düşer |
| `trending_charts` | `category_id` | `cascadeOnDelete` — kategori silinirse snapshot'lar da silinir |
| `store_categories` | `parent_id` (self) | `nullOnDelete` — parent silinirse child top-level olur |

Davranış farkı dikkat çekici: `apps` koruyucu (`nullOnDelete`), `trending_charts` yıkıcı (`cascadeOnDelete`). Snapshot'lar idempotent biriktiği için cascade kabul edilebilir, ama prod'da bir kategori yanlışlıkla silinirse tüm tarihsel snapshot'lar gider. Pratikte silme yok (sadece seeder upsert).

## Web Tüketimi

- Endpoint: `web/src/api/endpoints/store-categories/store-categories.ts` (orval generated) — `useListStoreCategories(params)`.
- Kullanım yerleri:
  - `web/src/pages/discovery/Trending.tsx:62` — trending charts kategori dropdown'ı (`platform` filtresi, `type` filtresi yok).
  - `web/src/pages/explorer/Icons.tsx:59` — `{ platform, type: 'app' }`.
  - `web/src/pages/explorer/Screenshots.tsx:59` — `{ platform, type: 'app' }`.
- Hepsinde basit dropdown — `parent_id` / hiyerarşi tüketimi yok, flat list.

## Gözlemler & Kokular

1. **Sessiz kategori kaybı.** Scraper'ın döndürdüğü bir `genre_id` (örn. yeni eklenen `7020 GAMES_STICKERS` gibi) seed'de yoksa app `category_id = null` ile yaratılır; sadece `storage/logs/unknown-categories.log`'da error olarak görünür. Operasyonel uyarı yok (alert / dashboard / Filament panel yok). MEMORY'deki "organic data collection" tonuyla uyumlu ama gözden kaçma riski yüksek.

2. **`unknown_categories` log kanalı tek dosya, rotasyon yok.** `config/logging.php:68-73` → `driver: single`, rotasyonsuz. Uzun süreçte şişebilir.

3. **`type` enum'u API'de `magazine`, veritabanında yok.** Request/Resource/Controller `magazine` değerini kabul ediyor ama seeder bu değeri üretmiyor → `type=magazine` filtresi her zaman boş dönüyor. Ya seeder eksik ya enum fazla.

4. **`priority` ölü alan.** Migration default 0, seeder set etmiyor, JSON'da yok. `SyncDailyChartsCommand` `orderByDesc('priority')` yapıyor ama tüm değerler 0 olduğu için sıralama `name`'e düşüyor. UI'da ülkelerdeki gibi (`Country::priority`) önemli kategorileri öne çekmek için tasarlanmış ama hiç kullanılmıyor.

5. **`slug` external olarak garanti tekil değil.** Migration unique anahtarı `(platform, slug, type)`. iOS'ta `music` slug'ı hem app (`MUSIC`) hem game (`GAMES_MUSIC`) için var; `type` ayrımıyla geçiyor. Bu doğru ama API'de `slug` ile arama yapan tüketici de `type`'ı vermek zorunda — şu an API filtre olarak `slug` desteklemiyor (sadece `platform`+`type` filtresi).

6. **`game` type'lı kategoriler chart sync'e dahil değil.** `SyncDailyChartsCommand:54` sadece `type='app'`. App Store / Play "Top Free Games" gibi alt-chart'lar üretilmiyor. Bilinçli bir tercih olabilir (volüm tasarrufu) ama dokümante değil.

7. **Resolver name fallback case-insensitive ama lokale duyarsız değil.** `mb_strtolower` Türkçe `İ → i̇` gibi locale-spesifik durumlarda farklı davranabilir; veri İngilizce olduğu için risk düşük.

8. **`StoreCategory::find` `FetchChartSnapshotJob` ve `SyncChartSnapshotJob`'ta her dispatch'te DB hit yapıyor** — küçük tablo ama her chart job'da 1 ekstra query. Resolver cache'i bu jobs'larda kullanılmıyor.

9. **`parent_id` veri zenginliği yetersiz.** Sadece Android'de gerçek hiyerarşi var (GAME→GAME_*, FAMILY→FAMILY_*). iOS games'in tümü tek `Games` parent'ı altında, ama iOS'ta `Magazines & Newspapers` gibi alt-collection'lar parent ağacına dahil değil. Hiyerarşi simetrik değil.

10. **Resource `children` / `apps_count` döndürmüyor**, yine de tüm tüketiciler flat list ile mutlu. Hiyerarşik UI olmadığı sürece sorun değil.

11. **`external_id` üzerinde DB unique yok.** Pratikte seeder bunu korur ama runtime'da koruma katmanı yok — gelecekte runtime write eklenirse veri kirliliği riski.

12. **Magazines & Newspapers iOS'ta `type='app'`** ama API enum `magazine` ayrımı bekliyor. Tutarsızlık.

## Refactor / İyileştirme Fırsatları

- **`magazine` type'ını ya seedle ya da enum'dan kaldır.** Mevcut hali kafa karıştırıcı.
- **`priority` alanını JSON'a taşı**, seeder upsert payload'una ekle; popüler kategorileri (`Photo & Video`, `Social Networking`, `Games`) öne çekmek chart sync kuyruğunda ve UI dropdown'ında değer katar.
- **`(platform, external_id)` için DB unique** — seeder'ın anahtarını veritabanı seviyesinde de zorla.
- **`unknown_categories` için daily channel + alert.** Yeni kategori sinyalini iş sürecine entegre et (örn. eşik aşılırsa Filament panel uyarısı / GitHub issue otomasyonu).
- **`StoreCategoryFactory` ve unknown-category fixture** — testler için seeder'a bağımlılık azaltılabilir (test'ler şu an seeder'a güveniyor olabilir).
- **Resource'a `children_count` / `apps_count` (lazy) ekle**, MCP / API üzerinden daha zengin tüketim için.
- **`SyncChartSnapshotJob` ve `FetchChartSnapshotJob` `StoreCategory::find` çağrılarını dispatch tarafında payload'a `external_id` ekleyerek kaldır**, böylece her job DB hit'i tasarruf eder.
- **`game` type için ayrı chart sync seçeneği** — config flag ile opsiyonel açılabilir (`appstorecat.charts.{platform}.sync_games`).
- **`StoreCategoryResolver` cache'ini singleton + Redis backed yap**, queue worker restart'larından bağımsız olarak.
- **Self-FK simetrisini düzelt:** iOS games'i alt-tree'ye taşı veya doc'la "iOS games hiyerarşi flat" diye açıklamayı netleştir.
- **`type` ve `parent_id` için scope ekle** (`scopeApps()`, `scopeGames()`, `scopeTopLevel()`) — controller / command ad-hoc `where`'lerini değiştirir.
- **Trending API `category_id` default'unu controller'dan resolver'a taşı** (`StoreCategoryResolver::getAllBucketId($platform)` gibi) — DRY.
