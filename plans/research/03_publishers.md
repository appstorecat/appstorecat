# `publishers` Tablosu Auditi

Üretim tarihi: 2026-05-15
Kapsam: `server/` (Laravel), `web/` (React) ve scraper sınırları.

---

## Genel Bakış

`publishers` tablosu store'larda görünen geliştirici/yayıncı varlığını (Apple
"artist", Google "developer") temsil eder. Her platform bağımsız bir satır
tutar — yani Meta Platforms iOS'ta ayrı, Android'de ayrı bir yayıncıdır.
Yayıncılar app sync pipeline'ı (`AppSyncer::syncIdentity`) ve discovery
yollarından (`App::discover`, `PublisherController::storeApps`) yazılır;
UI tarafında "Publishers" sayfası, yayıncı arama ve yayıncı bazlı toplu
"track" akışı bu tabloyu okur.

Tabloya `apps.publisher_id` (nullable, `nullOnDelete`) ile bağlanılır.

---

## Şema

Migration: `server/database/migrations/2026_04_06_000000_create_publishers_table.php`

| Kolon | Tip | Null | Açıklama |
|---|---|---|---|
| `id` | bigint PK | no | Auto-increment |
| `name` | string | no | Store'da görünen developer/publisher adı |
| `external_id` | string | yes | iTunes `artistId` / Play `developerId`; bilinmiyorsa null |
| `platform` | tinyint | no | 1=iOS, 2=Android (`App\Enums\Platform`) |
| `url` | text | yes | Store'daki publisher sayfa/website URL'i |
| `created_at`, `updated_at` | timestamps | yes | Standart Laravel timestamps |

Cast'lar (`Publisher::casts`):
- `platform` → `App\Casts\PlatformCast` (DB int <-> string slug)

Fillable: `name`, `external_id`, `platform`, `url`.

---

## Index'ler & FK'lar

Tanımlı tek index:

```php
$table->unique(['platform', 'external_id']);
```

Notlar:
- `external_id` **nullable** olmasına rağmen unique composite'in parçası. MySQL'de
  `NULL` değerleri unique kısıtını ihlal etmez — yani aynı platformda birden fazla
  `external_id = NULL` satırı mümkündür (iOS'ta external_id olmayan ikinci kayıt
  yaratılırsa duplicate olur). Bu durum modelde elle ele alınıyor (aşağıya bkz.).
- `name` üzerinde index yok. `findOrCreateByName` ihtimal dahilinde `name` ile
  arama yaptığı için tam tarama gerçekleşebilir (tablo küçük olduğu için
  pratikte sorun değil).
- `apps.publisher_id` → `publishers.id` FK'sı `apps` tablosunda tanımlı
  (`2026_04_06_000001_create_apps_table.php`, `nullOnDelete`).

---

## Model — `Publisher::findOrCreateByName`

Dosya: `server/app/Models/Publisher.php`

Kritik kod:

```php
public static function findOrCreateByName(string $name, string $platform, ?string $externalId = null): self
{
    // Android uses developer name as external_id (URL-encoded for safe linking)
    $resolvedExternalId = $externalId ?? ($platform === 'android' ? urlencode($name) : null);
    $platformValue = self::normalizePlatform($platform);

    if ($resolvedExternalId) {
        return static::firstOrCreate(
            ['platform' => $platformValue, 'external_id' => $resolvedExternalId],
            ['name' => $name],
        );
    }

    return static::firstOrCreate(
        ['platform' => $platformValue, 'name' => $name],
    );
}
```

Davranış matrisi:

| Platform | `$externalId` verildi mi | Çözümlenen `external_id` | Lookup anahtarı |
|---|---|---|---|
| `ios` | evet | `$externalId` | `(platform, external_id)` |
| `ios` | hayır | `null` | `(platform, name)` — `external_id` null bırakılır |
| `android` | evet | `$externalId` (genelde developer name) | `(platform, external_id)` |
| `android` | hayır | `urlencode($name)` | `(platform, urlencode(name))` |

`HasPlatform` trait'i:
- `scopePlatform($value)` — sorgu scope; `Publisher::platform('ios')` çağrısını
  destekler.
- `normalizePlatform($value)` — string/int/`Platform` → DB int.

İlişkiler:
- `apps(): HasMany<App>` — `publisher_id` üzerinden.

OpenAPI şeması `Publisher` modelde attribute olarak tanımlı; resource'lar bu
şemaya `allOf` ile referans veriyor.

---

## Yazan Yerler

`grep -rn "Publisher::" server/app` çıktısına göre yalnızca iki yazar var:

1. **`server/app/Services/AppSyncer.php:145`** — `syncIdentity` içinde, identity
   payload'unda `publisher_external_id` ve `publisher_name` doluysa:
   ```php
   $publisher = Publisher::firstOrCreate(
       ['platform' => Publisher::normalizePlatform($platform), 'external_id' => $data['publisher_external_id']],
       ['name' => $data['publisher_name'], 'url' => $data['publisher_url'] ?? null],
   );
   $appData['publisher_id'] = $publisher->id;
   ```
   Not: Burada `findOrCreateByName` kullanılmıyor — doğrudan `firstOrCreate`
   çağrılıyor. `publisher_url` yalnızca burada yazılıyor.

2. **`server/app/Models/App.php`** içinde iki çağrı (her ikisi de
   `App::discover`):
   - Satır 183 (backfill kolu): mevcut app'in `publisher_id` null'sa ve payload
     `developer` taşıyorsa `Publisher::findOrCreateByName($developer, $platform, $developer_id)`.
   - Satır 216 (yeni app oluşturma kolu): aynı imzayla.

Yazar topolojisi:
- Yeni yayıncılar yalnızca `AppSyncer::syncIdentity` ve `App::discover`
  yollarından oluşur.
- `PublisherController::storeApps` doğrudan yazmaz; mevcut publisher'ı 404
  doğrulaması yapıp `App::discover`'ı tetikler (controller'da yorum:
  "Prevents unverified IDs from hitting the scraper and polluting the
  publishers table").

---

## Okuyan Yerler

Controller (`PublisherController`):
- `index` — `whereHas('apps')` ile kullanıcının track ettiği app'lere bağlı
  yayıncıları `apps_count` ile listeler.
- `show` — `platform('ios|android')->where('external_id', $externalId)`;
  user'ın track ettiği app'leri `trackedApps` relation'ına manuel set eder.
- `storeApps` — yayıncı varlığını doğrular (`firstOr(abort 404)`), connector'a
  `external_id` gönderir, dönen app'leri `App::discover` ile seed eder.
- `search` — DB okumaz; sadece store connector'larına gider, sonuçları
  `groupByPublisher` ile yayıncı bazlı toplar.
- `import` — DB'den publisher okumaz; sadece `external_ids` üzerinden
  `AppRegistrar::register` çağırır.

Resource embed'leri (`$app->publisher` üzerinden):
- `AppResource` (satır 37–41) — `id, name, external_id, platform`.
- `AppDetailResource` (satır 50–55) — yukarıdakine ek olarak `url`.
- `AppSearchResultResource` (satır 65, 71–76) — `publisher_name` (legacy düz alan)
  ve `publisher` nested objesi birlikte sunuluyor.

Web tarafı kullanım:
- `web/src/pages/publishers/Index.tsx` — `useListPublishers()` ve
  `useSearchPublishers()`. Link path'i: `/publishers/{platformSlug}/{external_id}`
  (search sonuçları `external_id` olarak iOS'ta numeric ID, Android'de
  developer name döner).
- `web/src/pages/publishers/Show.tsx` — `useShowPublisher` + `usePublisherStoreApps`;
  store apps listesi üzerinden track/untrack toggle.

---

## API Yüzeyi

Route: `server/routes/api.php:80–87`

| Method | Path | Controller | Açıklama |
|---|---|---|---|
| GET | `/publishers/search` | `search` | Store'larda publisher ara (DB'siz) |
| GET | `/publishers` | `index` | Kullanıcının tracklediği app'lerin yayıncıları |
| GET | `/publishers/{platform}/{externalId}` | `show` | Yayıncı detayı + tracked app'leri |
| GET | `/publishers/{platform}/{externalId}/store-apps` | `storeApps` | Store'dan yayıncının tüm app'lerini çek + `App::discover` seed |
| POST | `/publishers/{platform}/{externalId}/import` | `import` | Verilen `external_ids` listesini track et |

Route constraint:
```php
->where(['platform' => 'ios|android', 'externalId' => '[a-zA-Z0-9._%+ -]+'])
```
`externalId` regex'i URL-encoded Android developer adlarını (boşluk, `%`, `+`,
`.`) kabul edecek şekilde gevşek tutulmuş.

Resource'lar:
- `PublisherResource` — temel alanlar + opsiyonel `apps_count`.
- `PublisherDetailResource` — `{publisher: {...}, apps: AppResource[]}` (apps,
  controller'da `trackedApps` ile dolduruluyor).
- `PublisherSearchResultResource` — DB'siz; connector çıktısı (`external_id`,
  `name`, `app_count`, `sample_apps[]`).
- `StoreAppResource` — `storeApps` endpoint için, `is_tracked` request
  attribute'undan okunuyor.

---

## Bağımlı Tablolar

Yalnızca tek FK girişi var:

- **`apps.publisher_id`** — `nullable, constrained('publishers')->nullOnDelete()`.
  - Bir publisher silinirse o publisher'a bağlı tüm `apps` satırları
    `publisher_id = NULL` olur; app'ler kaybolmaz.
  - Yeni discover edilen ama henüz identity sync olmamış app'ler de doğal
    olarak `publisher_id = NULL` durumda olabilir (model docblock: "null when
    publisher metadata not yet scraped").

Başka tablo `publishers`'a referans vermiyor.

---

## Gözlemler & Kokular

### 1. Android `external_id = urlencode(name)` yanılgısı

`findOrCreateByName` Android'de external_id verilmediği zaman
`urlencode($name)`'i identifier olarak yazıyor. Bu üç farklı riski getiriyor:

- **Tutarsız anahtar**: Aynı developer için `developer_id` payload'unun bazen
  geldiği (chart, identity sync) bazen gelmediği durumlarda iki ayrı satır
  oluşabilir: biri `external_id = "Acme%20Inc."`, diğeri
  `external_id = "Acme, Inc."` (gerçek Play `developerId` formatı). Unique
  constraint bunu engellemiyor çünkü iki değer farklı.
- **URL routing constraint'iyle eşleşme**: `routes/api.php`'da `externalId`
  regex'i `[a-zA-Z0-9._%+ -]+` — `urlencode` çıktısının (`%20`, `%2C`...) URL
  yolu içine konabilmesi için bilinçli olarak `%` izin verilmiş. Ancak `&`, `(`,
  `)` gibi developer adlarında geçebilen karakterler regex'te yok; encode
  edilmiş haliyle de problem olmaz ama edge case ortaya çıkabilir.
- **iOS ile asimetri**: iOS tarafında `external_id` null kabul ediliyor;
  Android'de hiçbir zaman null bırakılmıyor. Aynı entity'yi farklı bir
  konseptle modellemek bakım yükü yaratıyor.

### 2. iOS'ta `external_id = NULL` ile duplikasyon riski

iOS payload'unda artistId yoksa (`developer_id` boş gelirse) `Publisher`
`(platform=ios, name=...)` ile aranır ve external_id null oluşturulur. MySQL
unique index null'larda çoğul satıra izin verdiği için aynı isimle iki
satır arasında bir yarış (race) durumunda iki kayıt oluşabilir — kontrol
yalnızca `firstOrCreate`'in attributes bloğunda. `firstOrCreate` aynı transaction
içinde değil; aynı anda gelen iki sync için duplicate possible.

### 3. Yayıncı adı değişikliğinde güncelleme yok

`firstOrCreate(..., ['name' => $name])` ikinci argümandaki değerler yalnızca
**yeni kayıt** oluşturulduğunda yazılır. Yayıncı store'da adını değiştirirse
(`AppSyncer::syncIdentity` veya `App::discover`'dan gelen yeni `name`)
DB'deki kayıt güncellenmez. `url` için de aynı durum (sadece `AppSyncer` yazıyor,
o da yalnızca create anında).

### 4. `AppSyncer` vs `Publisher::findOrCreateByName` ayrışması

`AppSyncer::syncIdentity` modeldeki helper'ı kullanmıyor; doğrudan
`Publisher::firstOrCreate` çağırıyor. Sonuç olarak iki kod yolu var:
- AppSyncer yolu: `url` yazar, ama yalnızca `publisher_external_id` ve
  `publisher_name` ikisinin de dolu olduğu durumda (`external_id` null'la
  asla bu kola girilmez).
- App::discover yolu: `url` yazmaz; iOS'ta external_id null olabilir;
  Android'de urlencode fallback'i tetikler.

Bu davranış farkı discovery'den gelen yayıncıları daha "fakir" bir kayıtla
oluşturuyor (URL'siz). Sonradan AppSyncer aynı publisher'a denk geldiğinde
`firstOrCreate` zaten kayıt olduğu için url backfill etmez.

### 5. `apps_count` listesinin user-scope'lu olması

`PublisherController::index` yalnızca giriş yapan kullanıcının tracklediği
app'lere bağlı publisher'ları döner, `apps_count` da sadece o kullanıcının
app'lerini sayar. Bu UI için doğru olabilir ama "publisher'ın kataloğunda
kaç app var" sorusuna cevap vermez — kullanıcı için global istatistik yok.

### 6. `PublisherSearchResultResource.external_id` çift anlamlılığı

`groupByPublisher` içinde:
```php
$key = $devExtId ?? $devName;
// ...
'external_id' => $devExtId ?? $devName,
```
yani iOS arama sonuçlarında developer_id eksikse `external_id` alanı developer
adıyla doluyor. Web frontend bunu URL'de external_id olarak kullandığı için
sonraki `show` endpoint'i 404 verir (DB'de o satır yok). Routing constraint'i
boşluğu kabul ettiği için 404 sessizce kullanıcıya yansır.

### 7. Yetimleştirme (orphan) ihtimali var

Publisher silindiğinde `apps.publisher_id` null'a çekilir; ancak hiçbir app'i
kalmayan publisher kayıtları (örn. tüm app'ler silindi/track'ten çıkarıldı)
otomatik temizlenmiyor. Tabloda zamanla "boş" yayıncılar birikecek.

### 8. URL'siz iOS yayıncıları

Migration `url` için yorum "Publisher website / store page URL scraped from the
listing" diyor ama `App::discover` yolu URL set etmiyor. Discovery üzerinden
oluşturulan iOS yayıncılarının `url`'i identity sync olana dek null kalır.

---

## Refactor / İyileştirme Fırsatları

1. **Tek yazıcı yol**: `AppSyncer::syncIdentity` da `Publisher::findOrCreateByName`
   kullanacak şekilde refactor. URL gibi opsiyonel alanlar için helper'a
   `extras` parametresi veya `firstOrCreate` yerine `updateOrCreate` kullanımı
   ile create-anında yazılan tüm alanlar tek noktada yönetilebilir.

2. **Name/URL refresh politikası**: `updateOrCreate` ile her iki yazıcıda da
   `name` ve (varsa) `url` her senkronizasyonda güncellensin. Store'da
   developer adı değiştiğinde DB takip eder.

3. **Android external_id stratejisini gözden geçir**: Üç seçenek var:
   - **A**: External_id Android için tamamen kaldırılsın, eşleşme her zaman
     `(platform, name)` üzerinden yapılsın (UI rotasında `slug(name)`
     kullanılabilir).
   - **B**: `urlencode` yerine deterministic bir slug (örn. `Str::slug` veya
     `sha1(strtolower(trim(name)))`) — locale'e duyarsız, kararlı.
   - **C**: Mevcut yapı korunup `AppSyncer` Android için de fallback'i aynı
     `findOrCreateByName`'e devreder (en az invaziv).

4. **`(platform, name)` üzerine fonksiyonel veya partial unique**: iOS'ta
   `external_id IS NULL` durumunda duplikasyon race'ini engellemek için
   `(platform, name)` üzerine ikinci bir unique gerekir; ya da
   `findOrCreateByName`'i `DB::transaction` + `lockForUpdate` ile
   sertleştirmek.

5. **`name` üzerine basit index**: Web "My Publishers" listesi `orderBy('name')`
   yapıyor; tablo büyürse `(name)` veya `(platform, name)` üzerine index
   faydalı olur.

6. **Orphan temizlik job'u**: Periyodik bir job ile `apps()->count() === 0`
   olan publisher'ları soft-delete ya da hard-delete. Alternatif: app silindiğinde
   eventte kontrol.

7. **Resource embedding'i tek noktaya al**: `AppResource`, `AppDetailResource`,
   `AppSearchResultResource` üçü de aynı publisher şeklini elle yazıyor.
   `PublisherResource::make($app->publisher)?->resolve()` veya küçük bir
   `PublisherEmbedResource` tek noktada tanımlanabilir.

8. **`PublisherSearchResultResource.external_id` net davransın**: developer_id
   yoksa ya sonuç hiç dönmesin ya da bir `is_searchable: false` bayrağıyla
   frontend tıklatmayı bloklasın. Şu anki davranış sessizce ölü link üretiyor.

9. **`apps_count` semantiği**: Listede iki sayım sunmak — `tracked_apps_count`
   (mevcut davranış) ve `catalog_apps_count` (publisher'a bağlı tüm app'ler) —
   kullanıcıya daha net bilgi verir.

10. **`storeApps` yan etkisi şeffaflaştırılsın**: `App::discover` toplu seed
    yan etkisi controller yorumlarında belirtilmiş ama servise (örn.
    `PublisherCatalogSeeder`) taşınması test edilebilirliği artırır ve
    controller'ı sade tutar.
