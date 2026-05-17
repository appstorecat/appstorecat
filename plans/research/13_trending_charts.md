# `trending_charts` Tablosu — Audit

## Genel Bakış

`trending_charts`, günlük olarak çekilen App Store / Google Play sıralama (chart) snapshot'larının başlığıdır. Her satır; tek bir `(platform, collection, country_code, category_id, snapshot_date)` tuple'ına karşılık gelir ve o snapshot'a ait gerçek sıralama satırlarını `trending_chart_entries` çocuk tablosunda tutar (sıralama satırları bu auditin dışında, `14_trending_chart_entries.md`'de ele alınacak).

Tablo iki ayrı job tarafından yazılır (`SyncChartSnapshotJob` — günlük cron; `FetchChartSnapshotJob` — UI tetikli senkron fetch). Eloquent modeli `App\Models\ChartSnapshot` ismiyle bu tabloya bağlıdır (`protected $table = 'trending_charts'`).

Migration dosyası: `server/database/migrations/2026_04_10_000001_create_chart_tables.php`.

## Şema

| Kolon | Tip | Açıklama |
|---|---|---|
| `id` | `bigint unsigned` PK | Auto increment |
| `platform` | `unsignedTinyInteger` | `1=iOS`, `2=Android` (`App\Enums\Platform`) |
| `collection` | `string(30)` | `top_free`, `top_paid`, `top_grossing` (`App\Enums\ChartCollection`) |
| `category_id` | `foreignId` → `store_categories.id` | Chart kategorisi; "overall" için kategorinin "all" bucket'ı kullanılır (`external_id IS NULL`) |
| `country_code` | `char(2)` default `'us'` | FK → `countries.code` |
| `snapshot_date` | `date` | UTC takvim günü; chart başına günde bir snapshot |
| `created_at` / `updated_at` | `timestamp` | Standart Laravel timestamps |

`down()` migrasyonu önce `trending_chart_entries` sonra `trending_charts` drop ediyor (FK sırası doğru).

## Index'ler & FK'lar

- **Unique:** `uniq_snapshot` = `(platform, collection, country_code, category_id, snapshot_date)` — aynı chart için aynı gün iki kez kayıt girilemez.
- **Lookup index:** `idx_lookup` = `(platform, collection, country_code, snapshot_date)` — `category_id` kolonu yok; kategoriye bakılmadan "bu chart family için son N gün" tarama paterni için.
- **FK `category_id`** → `store_categories(id)` `cascadeOnDelete`.
- **FK `country_code`** → `countries(code)` (silme davranışı belirtilmemiş; varsayılan `RESTRICT`).
- `platform`, `collection`, `snapshot_date` üzerinde tekil index yok — sadece compound'lar üzerinden hizalanıyor.

## Model — `App\Models\ChartSnapshot`

- `protected $table = 'trending_charts'`
- `#[Fillable([...])]`: `platform`, `collection`, `category_id`, `country_code`, `snapshot_date`
- `use HasPlatform` — `platform()` scope ve `Platform` cast desteğini getirir
- **Casts:**
  - `platform` → `App\Casts\PlatformCast`
  - `collection` → `App\Enums\ChartCollection` (string-backed enum)
  - `snapshot_date` → `date`
- **İlişkiler:**
  - `entries(): HasMany<ChartEntry>` — `trending_chart_id` FK üzerinden, `orderBy('rank')` ile.
  - `category(): BelongsTo<StoreCategory>` — `category_id` üzerinden.
- **Scope `scopeForChart($platform, $collection, $countryCode, $categoryId)`** — `platform()` + `collection` + `country_code` + `category_id` filtresini tek noktada toplar.

## Yazan Yerler

| Yer | Kullanım |
|---|---|
| `app/Jobs/Chart/SyncChartSnapshotJob.php` | Günlük cron job. Önce `forChart(...)->where('snapshot_date', today)->exists()` ile çift-yazımı engeller, Redis throttle sonrası `ChartSnapshot::create([...])` yapar; ardından `ChartEntry::create` ile entries yazar. `ShouldBeUnique`, `uniqueFor = 3600`. |
| `app/Jobs/Chart/FetchChartSnapshotJob.php` | UI tetikli senkron job (`ChartController::index` içinde `dispatchSync`). Aynı `exists()` guard'ı + `ChartSnapshot::create([...])`. Throttle yok. |
| `app/Console/Commands/Charts/SyncDailyChartsCommand.php` | Yazmaz; mevcut günün snapshot'larını sorgulayıp `(collection:country:category_id)` setini çıkarıyor ve eksik kombinasyonlar için `SyncChartSnapshotJob::dispatch` ediyor. |

**Cron tetikleyici:** `server/routes/console.php`:
```php
Schedule::command('appstorecat:charts:sync-daily --ios')->dailyAt('00:30');
Schedule::command('appstorecat:charts:sync-daily --android')->dailyAt('00:30');
```

Yazma kombinatoriği günde başına: `aktif country` × `3 collection` × `aktif kategori (app type)` (her platform için bağımsız).

## Okuyan Yerler

- `app/Http/Controllers/Api/V1/ChartController.php`
  - `ChartSnapshot::forChart(...)->orderByDesc('snapshot_date')->orderByDesc('created_at')->first()` — en taze snapshot.
  - Stale ise `FetchChartSnapshotJob::dispatchSync(...)`, sonra tekrar `forChart(...)->first()`.
  - `previousSnapshot` için aynı `forChart(...)` + `where('snapshot_date', '<', $current->snapshot_date)`.
  - Entries `with(['app.publisher', 'app.category'])` ile eager-load ediliyor; `previous_rank` dynamic property olarak ekleniyor.
- `app/Http/Controllers/Api/V1/App/AppRankingController.php`
  - `ChartEntry::query()->join('trending_charts', ...)->where('trending_charts.platform', ...)->where('trending_charts.snapshot_date', $selectedDate)` paterni — entries üzerinden join.
  - Her entry için `ChartSnapshot::forChart(...)` ile bir önceki günün snapshot'ı çekiliyor (N+1 riski, bkz. "Gözlemler").
- `app/Console/Commands/Charts/SyncDailyChartsCommand.php` — sadece "bugün bu kombinasyon zaten var mı?" kontrolü için okuma.
- `ChartSnapshot::scopeForChart` — yukarıdaki tüm okumalarda ortak filtre.

## API Yüzeyi

**`GET /api/v1/charts`** — `ChartController@index` (`routes/api.php:68`)

Query parametreleri:
- `platform` (required) — `ios` | `android`
- `collection` (required) — `top_free` | `top_paid` | `top_grossing`
- `country_code` (optional, default `us`)
- `category_id` (optional; verilmezse platformun `external_id IS NULL` olan "all" kategorisi kullanılır)

Response: `ChartEntryResource` koleksiyonu + meta (`snapshot_date`, `updated_at`, `platform`, `collection`, `country_code`).

**`GET /api/v1/apps/{platform}/{externalId}/rankings`** — `AppRankingController@index` (`routes/api.php:52`)

Query parametreleri:
- `date` (optional, default bugün)
- `collection` (optional, `top_free|top_paid|top_grossing|all`)

Bu endpoint `trending_charts` üzerinde join yaparak filtreler; doğrudan `trending_chart_entries`'i tarar.

## Resource'lar

- `app/Http/Resources/Api/Chart/ChartEntryResource.php` — entry tabanlı, ama `meta` alanında snapshot bilgileri (`snapshot_date`, `updated_at`) controller'dan basılıyor. `ChartSnapshotResource` ayrı yok; snapshot identity meta'ya gömülüyor.
- `app/Http/Resources/Api/App/AppRankingResource.php` — app perspektifinden ranking satırı; snapshot bilgileri (country, collection, category) içeride flatten ediliyor.

## Bağımlı Tablolar

- `trending_chart_entries.trending_chart_id` → `trending_charts.id`, `cascadeOnDelete`. Bir snapshot silindiğinde tüm entry'leri kaybeder. Ayrı audit: `14_trending_chart_entries.md`.

İlişkili plan: **`plans/database/trending-chart-entries-sparse-storage.md`** mevcut; `trending_chart_entries`'i sparse interval modeline çevirmeyi öneriyor ve bu kapsamda `trending_charts`'ın "kayıtlı chart registry"sine düşürülmesi / tamamen kaldırılması öneriliyor. Detay tekrar edilmiyor.

## Gözlemler & Kokular

1. **Cron büyüme oranı kombinatoryel.** Günde dağıtılan job sayısı = `platforms (2) × collections (3) × aktif country × aktif kategori`. `trending_charts` her gün bu kombinasyon kadar yeni satır üretiyor. Tablo "başlık" olduğu için entries'e kıyasla küçük kalır ama büyüme yine de doğrusal ve sınırsız.
2. **`category_id` üzerinde `cascadeOnDelete` riski.** `store_categories` satırı silinirse, o kategoriye ait tüm `trending_charts` (ve cascade ile tüm `trending_chart_entries`) tarihsel veri kaybolur. `restrictOnDelete` veya `SET NULL` daha güvenli olabilir; chart geçmişi audit/analitik açısından "yeniden üretilemez" veridir.
3. **`country_code` FK silme davranışı belirsiz.** Migration `cascadeOnDelete`/`restrictOnDelete` belirtmemiş; MySQL default'u `RESTRICT`. Niyet açık değil; comment eklenmesi yararlı olur.
4. **`collection` string olarak 30 char ile saklı.** Enum (`ChartCollection`) sadece 3 değer içeriyor: `top_free` (8), `top_paid` (8), `top_grossing` (12). 30 char overhead küçük ama tipsel olarak `tinyint` + cast (Platform'da yapıldığı gibi) daha tutarlı olurdu. Şu an `platform` int, `collection` string — heterojen.
5. **`idx_lookup`'ta `category_id` yok.** Sadece `(platform, collection, country_code, snapshot_date)` — gerçek okuma paterni `scopeForChart` ise `category_id`'yi de filtreliyor. `uniq_snapshot` unique index `category_id`'yi içerdiği için `forChart` sorguları onun üzerinden gidebilir; `idx_lookup` tamamen "kategori-agnostik" sorgular için duruyor, ama kodda böyle bir sorgu paterni görünmüyor. Potansiyel olarak ölü index.
6. **Boş chart skipping.** Her iki job da `empty($results)` durumunda hiçbir şey yazmıyor — yani "bugün chart boştu" bilgisi kayıt değil. Bir sonraki gün cron `exists()`'i kontrol ettiğinde "yok" görüp tekrar deneyecek; bu day-of çift fetch'e yol açabilir (Redis throttle yumuşatır ama maliyetli).
7. **Çift yazıcı (Sync + Fetch) duplicate guard'a güvenir.** İki ayrı job aynı snapshot'ı yazabilir. Hem `exists()` ön kontrolü hem de `uniq_snapshot` unique index var; ama `dispatchSync` ile cron tam aynı dakikada çakışırsa unique violation exception fırlatabilir. Yakalanmıyor.
8. **`AppRankingController` N+1.** Her entry için ayrı `ChartSnapshot::forChart(...)->first()` çağrısı yapılıyor (önceki günün snapshot'ı için). Entry sayısı arttıkça doğrusal sorgu maliyeti.
9. **`forChart` scope `created_at` üzerinde tiebreaker kullanıyor.** `orderByDesc('snapshot_date')->orderByDesc('created_at')` — `uniq_snapshot` aynı gün için zaten tek satır garanti ediyor; `created_at` tiebreaker savunma amaçlı ama gerçek bir senaryosu yok.
10. **`StoreCategory::find($this->categoryId)` her job'da.** Aynı kategori her gün × her country × her collection için tekrar tekrar çekiliyor; cache yok.

## Refactor / İyileştirme Fırsatları

1. **`category_id` FK davranışını gözden geçir.** `cascadeOnDelete` → `restrictOnDelete` veya `SET NULL`; tarihsel chart verisi kategori silinmesiyle düşmemeli.
2. **`collection` kolonunu typed yap.** Ya `tinyint` + cast (Platform pattern'i) ya da en azından `string(20)`'ye düşürüp comment'te enum kısıtını belirt.
3. **`idx_lookup`'ı revize et.** `category_id` ekleyerek `(platform, collection, country_code, category_id, snapshot_date)` yap (zaten `uniq_snapshot` aynısı — bu durumda lookup'ı silebilirsin). Veya `(platform, snapshot_date)` gibi "gün bazlı tüm chart'lar" için kullanışlı bir varyant oluştur.
4. **Empty-result kaydı.** Boş chart'larda placeholder snapshot (entries'siz) yazıp, ertesi gün cron'un boş chart'ı tekrar dövmesini önle; veya `sync_statuses` benzeri bir "denendi-boştu" sinyali ekle.
5. **`AppRankingController`'da previous snapshot N+1'ı kaldır.** Sorgudaki snapshot'ları bir kerede `(platform, collection, country, category)` gruplarına göre toplayıp önceki günün snapshot'larını tek `whereIn` ile çek.
6. **`SyncChartSnapshotJob` + `FetchChartSnapshotJob` ortak kod.** İki job da neredeyse aynı `fetchAndStore` mantığını taşıyor (biri throttle'lı + logging zengin, diğeri sade). Tek bir service/action'a (`StoreChartSnapshotAction`) çıkarılabilir; throttling job seviyesinde kalır.
7. **`StoreCategory` lookup'ını cache'le.** Job içinde `StoreCategory::find($categoryId)?->external_id` — request-scoped veya Redis cache ile birkaç kat daha ucuz olur.
8. **`forChart` scope `category_id` nullable kabul etmiyor.** "Overall" charts için controller'da `whereNull('external_id')` ile "all" kategori id'si bulunuyor; `category_id` her zaman dolu. Niyet açık olsa da, ilerideki "overall = NULL" geçişine kapı açmak isteyenler için scope'u nullable yapmak düşünülebilir.
9. **`trending-chart-entries-sparse-storage.md` planı ile koordinasyon.** O plan başarılı olursa `trending_charts` "registry" rolüne düşer (~20K satır) veya tamamen kaldırılır; bu tabloyu refactor etmeden önce o plan kararlaştırılmalı.
10. **Unique violation handling.** İki job çakışmasına karşı `Schema::create` unique index'inden yakalanan exception'ı job içinde `exists()` retry ile yumuşatmak veya `firstOrCreate` kullanmak.
