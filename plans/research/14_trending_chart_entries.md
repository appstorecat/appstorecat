# `trending_chart_entries` Audit

**Tarih:** 2026-05-15
**Kapsam:** `trending_chart_entries` tablosu — şema, model, yazan/okuyan kod yolları, indexler, büyüme profili ve gözlemler.

---

## Genel Bakış

`trending_chart_entries`, trending chart snapshot'larının satır-bazlı içeriğini tutar. Her gün her chart için (platform × collection × country × category) bir `trending_charts` satırı oluşturulur ve o chart'ın o günkü tüm rank'leri ayrı ayrı `trending_chart_entries` satırları olarak yazılır.

Yapı **naive daily snapshot** modelidir: bir uygulamanın rank'i günler boyunca aynı kalsa bile her gün için yeni bir satır insert edilir. Rank değişimi, çıkış-giriş veya "out of chart" durumu için herhangi bir özel temsil yoktur — tüm bilgi gün × rank kesişiminden türetilir.

Tablo, üretim ortamında veritabanının açık ara en büyük tablosudur (DB'nin yaklaşık %69'u). Detaylar aşağıda "Boyut/Büyüme" bölümünde.

---

## Şema

Kaynak: `server/database/migrations/2026_04_10_000001_create_chart_tables.php` (satır 31-50).

| Kolon | Tip | Not |
|---|---|---|
| `id` | `bigint unsigned` PK | Auto-increment. |
| `trending_chart_id` | `bigint unsigned` FK | `trending_charts.id` → `cascadeOnDelete`. Snapshot bu FK ile bağlanır; tarih, platform, collection, country, category bu üst tabloda durur. |
| `rank` | `unsignedSmallInteger` | 1-indexed chart pozisyonu. |
| `app_id` | `bigint unsigned` FK | `apps.id` → `cascadeOnDelete`. |
| `price` | `decimal(10,2)` default `0` | Snapshot anındaki fiyat (storefront para birimi cinsinden). |
| `currency` | `varchar(3)` nullable | ISO-4217. |
| `created_at`, `updated_at` | timestamps | Laravel default. |

Tabloda `snapshot_date` doğrudan bulunmaz — bu bilgi `trending_charts` üzerinden join ile gelir.

---

## Index'ler & Foreign Key'ler

Migration'da tanımlı index'ler:

| Index | Sütunlar | Amaç |
|---|---|---|
| `trending_chart_entries_trending_chart_id_rank_index` | `(trending_chart_id, rank)` | "Bu snapshot'taki top N" sorgusu (chart_id eşitliği + rank sırası). |
| `trending_chart_entries_app_id_trending_chart_id_index` | `(app_id, trending_chart_id)` | "Bu app hangi chart'larda göründü" sorgusu (per-app rank geçmişi). |
| `trending_chart_entries_app_id_index` | `(app_id)` | **REDUNDANT** — leftmost-prefix kuralıyla yukarıdaki `(app_id, trending_chart_id)` compound index'i `app_id` tekil aramayı zaten kapsar. |

Foreign key'ler:
- `trending_chart_id` → `trending_charts.id` `ON DELETE CASCADE`
- `app_id` → `apps.id` `ON DELETE CASCADE`

Notlar:
- `(trending_chart_id, rank)` üzerinde **unique** kısıtı **yoktur**. Aynı chart içinde aynı rank'in tekrar yazılması veritabanı tarafından engellenmiyor; sadece scrape mantığı tekrarı engelliyor.
- `(trending_chart_id, app_id)` üzerinde de unique yok — aynı app'in aynı snapshot içinde iki kere insert edilmesi şema seviyesinde engellenmemiş.

---

## Model

Dosya: `server/app/Models/ChartEntry.php`.

```php
#[Fillable(['trending_chart_id', 'rank', 'app_id', 'price', 'currency'])]
class ChartEntry extends Model
{
    protected $table = 'trending_chart_entries';

    public function snapshot(): BelongsTo  // → ChartSnapshot via trending_chart_id
    public function app(): BelongsTo       // → App
}
```

İlişkiler:
- `snapshot()` — `BelongsTo ChartSnapshot` (`trending_chart_id` üzerinden).
- `app()` — `BelongsTo App`.
- Ters yönden: `ChartSnapshot::entries()` — `HasMany ChartEntry` (`'trending_chart_id'` FK, `orderBy('rank')`). Bkz. `server/app/Models/ChartSnapshot.php:28-31`.

Cast yok; timestamp'ler default. `previous_rank` controller'larda **dinamik property** olarak entry üstüne yapıştırılıyor (resource'lar onu okuyor).

---

## Yazan Yerler

İki job, aynı insert mantığını taşıyor:

### `app/Jobs/Chart/SyncChartSnapshotJob.php`

Scheduler tarafından `charts-ios` / `charts-android` queue'larına itilen async job. Aynı gün için snapshot varsa erken döner. Redis throttle ile dakika başı `appstorecat.connectors.{appstore|gplay}.throttle.chart_jobs` kadar job çalışır.

Akış (satır 103-134):
1. `ChartSnapshot::create([...])` — yeni snapshot satırı.
2. Scraper sonuçlarındaki her entry için:
   - `App::discover($platform, $entry['app_id'], $entry, DiscoverSource::Trending, $countryCode)` — app yoksa shell olarak oluştur, varsa getir.
   - `ChartEntry::create(['trending_chart_id' => $snapshot->id, 'rank' => ..., 'app_id' => ..., 'price' => ..., 'currency' => ...])`.
3. `wasRecentlyCreated` üzerinden `new_apps` sayısı log'lanır.

### `app/Jobs/Chart/FetchChartSnapshotJob.php`

UI'dan tetiklenen senkron varyant (controller `dispatchSync` ile çağırıyor — bkz. ChartController). Throttle yok, retry sayısı 3. Insert mantığı `SyncChartSnapshotJob` ile bire bir aynı (satır 69-95): snapshot oluştur → results'u dolaş → `App::discover` → `ChartEntry::create`.

İki job arasındaki tek fark: zamanlama/throttle/uniqueness sözleşmesi. Insert davranışı duplicate.

**Önemli noktalar:**
- Her insert **tek tek** yapılıyor (`ChartEntry::create` per loop iteration). Bulk insert/upsert yok.
- `App::discover` her satır için çağrılıyor — chart sync'in yan etkisi olarak `apps` tablosuna "shell" satırlar düşürülüyor (chart'a giren hiç görülmemiş app'ler için).
- Aynı gün için snapshot zaten varsa job baştan dönüyor (idempotency); ama snapshot kısmi yazıldıysa (örn. job ortada düşmüşse) re-run karmaşık çünkü kısmi entries kalır, snapshot var, "exists" check geçer.

---

## Okuyan Yerler

### `app/Http/Controllers/Api/V1/ChartController.php` — `index()`

Endpoint: `GET /charts`. Akış (satır 51-119):

1. Filtreleri çöz: `platform`, `collection`, `country_code`, `category_id` (yoksa platform'un "All" kategorisi).
2. `ChartSnapshot::forChart(...)->orderByDesc('snapshot_date')->orderByDesc('created_at')->first()` — en güncel snapshot.
3. Snapshot stale ise (bugün değilse veya yoksa) `FetchChartSnapshotJob::dispatchSync(...)` ile anlık fetch + tekrar oku.
4. `$snapshot->entries()->with(['app.publisher', 'app.category'])->get()` — entries + app eager loading.
5. **Rank change için ikinci snapshot:** `ChartSnapshot::forChart(...)->where('snapshot_date', '<', $snapshot->snapshot_date)->orderByDesc('snapshot_date')->first()`.
6. Önceki snapshot varsa: `ChartEntry::where('trending_chart_id', $previousSnapshot->id)->pluck('rank', 'app_id')->toArray()` — `[app_id => rank]` map'i.
7. Her bugünkü entry'ye `previous_rank` dinamik property olarak yapıştırılır (`ChartEntryResource` onu okur).

### `app/Http/Controllers/Api/V1/App/AppRankingController.php` — `index()`

Endpoint: `GET /apps/{platform}/{externalId}/rankings`. Akış (satır 35-86):

1. App'i çöz (`resolveApp`).
2. `selectedDate` = query param ya da bugün; `collection` opsiyonel (`all` ise filtre yok).
3. Sorgu:
   ```php
   ChartEntry::query()
     ->select('trending_chart_entries.*')
     ->join('trending_charts', 'trending_charts.id', '=', 'trending_chart_entries.trending_chart_id')
     ->where('trending_chart_entries.app_id', $app->id)
     ->where('trending_charts.platform', Platform::fromSlug($platform)->value)
     ->where('trending_charts.snapshot_date', $selectedDate)
     ->when($collection !== 'all', fn ($q) => $q->where('trending_charts.collection', $collection))
     ->with(['snapshot.category'])
     ->get();
   ```
4. **Her entry için ayrı sorgu (N+1):**
   - O snapshot'ın chart_key'i ile bir önceki `ChartSnapshot`'ı bul.
   - `ChartEntry::where('trending_chart_id', $previous->id)->where('app_id', $app->id)->value('rank')` ile önceki rank'i çek.
   - `previous_rank` dinamik property olarak yapıştır.
5. `country_code, collection, rank` üzerinden sort.

Eager loading `snapshot.category` var ama `previous_rank` lookup'ı **her entry için iki ekstra sorgu** yapıyor (önceki snapshot + önceki entry rank). Bir app birden fazla chart'ta görünüyorsa (örn. multi-country, multi-collection) bu lineer büyür.

### `ChartSnapshot::entries` ilişkisi

`server/app/Models/ChartSnapshot.php:28-31` — `HasMany(ChartEntry::class, 'trending_chart_id')->orderBy('rank')`. `ChartController` bunu kullanıyor.

---

## API Yüzeyi

| Endpoint | Controller | Resource |
|---|---|---|
| `GET /api/v1/charts` | `ChartController::index` | `ChartEntryResource` |
| `GET /api/v1/apps/{platform}/{externalId}/rankings` | `AppRankingController::index` | `AppRankingResource` |

`ChartEntryResource` (`server/app/Http/Resources/Api/Chart/ChartEntryResource.php`) alanları: `rank`, `rank_change`, `app_id`, `app_external_id`, `app_name`, `icon_url`, `platform`, `publisher`, `category_name`, `price`, `currency`, `is_free`. `rank_change` = `previous_rank - rank` (yukarı çıkış pozitif).

`AppRankingResource` (`server/app/Http/Resources/Api/App/AppRankingResource.php`) alanları: `country_code`, `collection`, `category`, `rank`, `previous_rank`, `rank_change`, `status` (`new` | `up` | `down` | `same`), `snapshot_date`.

Web tüketicileri:
- `web/src/pages/discovery/Trending.tsx` — `useGetCharts` hook'u → `/charts`. Filtreler URL search params ile yönetiliyor (`platform`, `collection`, `country_code`, `category_id`). Filtre değişimlerinde skeleton gösteriliyor (stale veri yanıltıcı olmasın diye).
- `web/src/components/tabs/RankingsTab.tsx` — `useListAppRankings` hook'u → `/apps/.../rankings`. `selectedDate` ve `rankType` state'i, sonuçlar `(collection, category)` ikilisi başına kolon olarak gruplanıyor.

---

## Bağımlı Tablolar

Yok. Hiçbir başka tablo `trending_chart_entries.id`'ye FK ile bağlı değil. Migration'lar arasında bu tabloya referans veren ikinci bir tanım bulunamadı.

Cascade davranışı tek yönlü:
- `trending_charts` silinirse → entries `cascadeOnDelete` ile silinir.
- `apps` silinirse → o app'in tüm entries'i `cascadeOnDelete` ile silinir.

---

## Boyut/Büyüme

Üretim dump (2026-05-15) ölçümleri — kaynak: `plans/database/trending-chart-entries-sparse-storage.md`.

| Metrik | Değer |
|---|---|
| Satır sayısı | 43.681.656 |
| Toplam boyut | 7.7 GB (2.8 GB data + 4.9 GB index) |
| DB içindeki pay | ~%69 |
| Kapsanan gün | 25 |
| Günlük büyüme | ~1.75M satır / ~310 MB |
| 90 gün projeksiyonu | ~30 GB |
| 365 gün projeksiyonu | ~120 GB |
| Distinct chart snapshot | 25 × ~19.900 ≈ 497.717 |

`index_length` (4.9 GB) `data_length`'ten (2.8 GB) büyük. Naive snapshot modelinde index maliyeti veri maliyetini geçmiş durumda.

---

## Gözlemler & Kokular

1. **Redundant `(app_id)` index** — `(app_id, trending_chart_id)` compound'u zaten `app_id` tekil sorgularını leftmost-prefix kuralıyla karşılıyor. Tek başına `(app_id)` index'i ~700 MB disk alanı tüketiyor ve her insert'te ekstra index yazımı yaratıyor. Bağımsız bir quick-win.

2. **Naive snapshot redundansı** — Bir app rank'inde günlerce sabit kaldığında her gün için aynı `(trending_chart_id ≠, app_id, rank)` üçlüsünü tekrarlayan satırlar üretiliyor. Top-listeler ampirik olarak günden güne büyük ölçüde stabil; tablonun büyük çoğunluğu fiilen tekrar.

3. **`rank_change` server hesabı pahalı:**
   - `ChartController::index` — bugünkü snapshot + bir önceki snapshot için iki ayrı sorgu, sonra `pluck('rank', 'app_id')` ile in-memory map. Snapshot başına ~200 satır olduğundan tek bir chart için tolere edilebilir ama her chart request'inde tekrar.
   - `AppRankingController::index` — her entry için **N+1** pattern: önceki snapshot lookup + önceki entry rank lookup. Bir app çok sayıda chart × ülkede görünüyorsa lineer büyür.

4. **`cascadeOnDelete` zinciri** — `apps.id` silinirse o app'in tüm tarihsel chart geçmişi sessizce siliniyor; `trending_charts.id` silinirse o snapshot'ın tüm entries'i kaybediliyor. Geri dönüş yok, soft delete yok.

5. **Unique constraint eksikliği** — `(trending_chart_id, rank)` ve `(trending_chart_id, app_id)` üzerinde DB-level unique yok. Job retry/partial-failure senaryolarında duplicate insert'ler veritabanı tarafından yakalanmıyor; tek koruma `ChartSnapshot::create` ile başlangıçtaki `exists` check.

6. **Tek tek insert, bulk yok** — Hem `SyncChartSnapshotJob` hem `FetchChartSnapshotJob`'da `foreach ($results) { ChartEntry::create(...) }`. ~200 satırlık snapshot için ~200 ayrı INSERT statement. `App::discover` çağrısı da her satır için tek tek yapılıyor.

7. **`App::discover` chart yan etkisi** — Chart sync'in her satırı `apps` tablosuna potansiyel olarak shell satırı düşürebiliyor. Chart'a bir kez giren her app `apps` tablosunda kalıcı satır oluşturuyor (bu konu ayrı planda — sparse storage planının "Out of Scope" kısmında "Shell apps" maddesi).

8. **`snapshot_date` entry tablosunda yok** — Tarih bilgisine ulaşmak için her sorgu `trending_charts` ile join'lemek zorunda. `AppRankingController` zaten bu join'i yapıyor; sparse refactor planında bu alan entry-level'a embed ediliyor.

---

## Refactor / İyileştirme Fırsatları

Detaylı refactor planı mevcut: **`plans/database/trending-chart-entries-sparse-storage.md`** — `valid_from` / `valid_to` interval modeline geçiş, `~%80` boyut azaltma hedefi, gaps-and-islands ile batched migrate, dual-write cutover. Detayları burada tekrar etmiyorum; tek bilgi noktası o plan.

Bu plandan bağımsız çalıştırılabilecek tek quick-win: redundant `(app_id)` index'inin düşürülmesi (~700 MB geri kazanım, write maliyeti azalır, okuma planları etkilenmez).
