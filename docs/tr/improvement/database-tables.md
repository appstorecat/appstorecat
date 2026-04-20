# Veritabanı Tabloları — İyileştirme Önerileri

Tarih: 20 Nisan 2026
Branch bağlamı: `feat/sync-pipeline-rewrite`

## Genel Bakış

Bu belge, Laravel sunucusunun sahip olduğu her tabloyu inceler ve şema
düzeyindeki iyileştirmeleri listeler — eksik index'ler, daha sıkı kolon
tipleri, eksik foreign key'ler, partitioning adayları ve sync pipeline
bug raporunun doğal sonucu olan birkaç yapısal değişiklik.

Bu belgedeki hiçbir şey henüz uygulanmamıştır. Her öneri, bağımsız olarak
gözden geçirilmesi, planlanması ve uygulanması gereken aday bir
migration'dır. Her madde bir **Öncelik** (Yüksek / Orta / Düşük), bir
**Etki** (breaking / non-breaking) ve bir **Efor** (S / M / L) etiketi
taşır; belgenin sonunda birleşik bir matris sunulmuştur.

Öneriler Laravel Blueprint sözdizimini kullanır. Blueprint'in ifade
edemediği durumlarda (ör. `FULLTEXT` index'ler, partitioning) ham SQL
kullanılmıştır.

---

## 1. Kritik Sorunlar

Bu bölüm doğrudan [`bugs/report_20apr.md`](../bugs/report_20apr.md)
içindeki hatalara karşılık gelir ve sync pipeline'ının doğru çalışmasını
engelledikleri için ayrı bir başlığı hak eder.

### 1.1 `app_metrics.price` "bilinmiyor" durumunu ifade edemiyor

**Mevcut:**

```php
$table->decimal('price', 10, 2)->default(0);
$table->char('currency', 3)->nullable();
```

**Sorun:** Bug 5, scraper'ın metrics payload'unda şu an `price` /
`currency` alanlarını doldurmadığını gösteriyor. Kolonun `0` varsayılanı,
"ücretsiz" ile "bilinmiyor" durumlarını birbirine karıştırıyor — scraper
düzeltildikten sonra bile geçmişteki bir satırın gerçekten ücretsiz mi
olduğunu yoksa yalnızca yakalanmadığını mı anlayamıyoruz.

**Önerilen:**

```php
Schema::table('app_metrics', function (Blueprint $table) {
    $table->decimal('price', 10, 2)->nullable()->default(null)->change();
});
```

Sonrasında connector, scraper alanı vermediğinde `null` yazacak şekilde
güncellenmeli ve `0.00` "kesinlikle ücretsiz" anlamına çekilmelidir. Şu an
`price === 0` kontrolüne güvenen uygulama kodu denetlenmelidir.

**Öncelik:** Yüksek
**Etki:** Breaking (geçmişteki `0` satırlar backfill yapılmadan muğlak kalır)
**Efor:** M (geçmiş satırlar için backfill planı gerekir)

### 1.2 Kısmi metric satırları için veri kalitesi sinyali yok

**Mevcut:** `app_metrics`, satırın tam olup olmadığını gösteren bir
kolona sahip değil. `rating_breakdown`, satır hâlâ başarılı görünürken
sessizce `null` olabiliyor (Bug 6).

**Sorun:** Tüketiciler "ülkede breakdown yok" ile "breakdown pipeline
ortasında düştü" arasındaki farkı göremiyor. Bu aynı zamanda
reconciliation'ı zorlaştırıyor çünkü eksik satırları ucuza bulamıyoruz.

**Önerilen:**

```php
Schema::table('app_metrics', function (Blueprint $table) {
    $table->unsignedTinyInteger('completeness')
        ->default(0)
        ->comment('Bitmask: 1=rating, 2=breakdown, 4=price, 8=availability');
    $table->index(['app_id', 'completeness']);
});
```

Bitmask, kolonu ucuz tutar ve pipeline'ın gerçekten yakalanan alanları
işaretlemesine izin verir. Reconciliation böylece
`completeness < expected_mask` koşulunu hedefleyebilir.

**Öncelik:** Yüksek
**Etki:** Non-breaking (eklemeli kolon)
**Efor:** M

### 1.3 `sync_statuses` tek bir ilerleme sayacı taşıyor

**Mevcut:**

```php
$table->unsignedInteger('progress_done')->default(0);
$table->unsignedInteger('progress_total')->default(0);
```

**Sorun:** Bug 7 — sync pipeline birden çok faza sahip (identity,
listings, metrics, finalize, reconciling) ama ilerleme faz değiştiğinde
üzerine yazılan tek bir `(done, total)` çifti ile takip ediliyor. UI
faz bazında doğru ilerlemeyi gösteremiyor ve operatörler hangi fazın
takıldığını göremiyor.

**Önerilen:** Faz başına sayaçlar.

```php
Schema::table('sync_statuses', function (Blueprint $table) {
    $table->unsignedInteger('listings_done')->default(0);
    $table->unsignedInteger('listings_total')->default(0);
    $table->unsignedInteger('metrics_done')->default(0);
    $table->unsignedInteger('metrics_total')->default(0);
    // progress_done/progress_total'ı toplu görünüm olarak tutmak veya düşürmek.
});
```

Alternatif: faza göre anahtarlanmış tek bir JSON `phase_progress` kolonu.
Ayrık kolonlar önerilir çünkü index'lenebilir ve SQL içinde
toplanabilirler.

**Öncelik:** Orta
**Etki:** Non-breaking (eklemeli kolonlar); eski sayaçlar düşürülürse
opsiyonel breaking.
**Efor:** M

### 1.4 `apps.is_available` global ama uygunluk ülkeye özel

**Mevcut:** `apps.is_available`, uygulama satırındaki tek bir boolean;
buna karşılık `app_metrics.is_available` her `(app_id, country_code, date)`
için kaydediliyor.

**Sorun:** Bug 8 — iki kolon çelişiyor. Bir uygulama ABD'de mevcut ama
Rusya'da kaldırılmış olabilir; global bayrak bunu ifade edemez. Metric
satırı otoritedir ama çoğu UI yolu şu an app düzeyindeki bayrağı okuyor.

**Önerilen (Faz 1, önerilen):** `apps.is_available`'ı önbellek olarak
yeniden tanımlamak — "en az bir aktif ülkede mevcut". Güncellemeyi
identity adımı yerine reconciliation adımından yap.

```php
Schema::table('apps', function (Blueprint $table) {
    $table->boolean('is_available')
        ->default(true)
        ->comment('Önbelleklenmiş toplam: >= 1 aktif ülkede mevcutsa true. '
            . 'Otorite veri app_metrics.is_available içinde yaşar.')
        ->change();
});
```

**Önerilen (Faz 2, uzun vade):** tüm okumalar
`app_metrics.is_available`'ı toplayan bir view veya resource üzerinden
gittiğinde kolonu tamamen düşür.

```php
Schema::table('apps', function (Blueprint $table) {
    $table->dropColumn('is_available');
});
```

Bir migration yolu gereklidir: `apps.is_available`'ın tüm kullanımları
denetlenmeli, bir `AvailabilityResolver` servisi tanıtılmalı ve sonra
kolon düşürülmelidir.

**Öncelik:** Yüksek
**Etki:** Breaking (nihayetinde), Faz 1 süresince non-breaking
**Efor:** L

---

## 2. Şema iyileştirmeleri

Tablo bazında öneriler. Her madde aynı biçimi izler.

### 2.1 `apps`

#### `last_synced_at` üzerine index

**Mevcut:** Index yok. Tracked-sync zamanlayıcısı tabloyu her 20 dakikada
bir `last_synced_at` ile sıralayarak tarar.

**Sorun:** Uygulama sayısı arttıkça tam tablo taramaları lineer büyür.

**Önerilen:**

```php
Schema::table('apps', function (Blueprint $table) {
    $table->index('last_synced_at');
});
```

**Öncelik:** Yüksek
**Etki:** Non-breaking
**Efor:** S

#### `discovered_from` üzerine index

**Mevcut:** Keşif kaynağı enum'unda index yok.

**Sorun:** Keşif kaynağına göre uygulama sayılarını kıran dashboard'lar,
tüm tabloyu tarayan `GROUP BY discovered_from` sorguları üretir.

**Önerilen:**

```php
Schema::table('apps', function (Blueprint $table) {
    $table->index('discovered_from');
});
```

**Öncelik:** Düşük
**Etki:** Non-breaking
**Efor:** S

#### `is_available` üzerine index

**Mevcut:** Index yok. Tracked sync `is_available = true` ile filtreler.

**Önerilen:**

```php
Schema::table('apps', function (Blueprint $table) {
    $table->index(['platform', 'is_available', 'last_synced_at']);
});
```

Bileşik bir index "X platformunda sıradaki senkronize edilecek
uygulamalar" sorgusunu doğrudan karşılar.

**Öncelik:** Orta
**Etki:** Non-breaking
**Efor:** S

#### `origin_country` üzerine foreign key

**Mevcut:** `origin_country`, FK olmadan `char(2) default 'us'`.

**Sorun:** Geçersiz veya bilinmeyen ülke kodlarını engelleyen bir şey
yok. Referans kaynağı `countries` tablosudur.

**Önerilen:**

```php
Schema::table('apps', function (Blueprint $table) {
    $table->foreign('origin_country')
        ->references('code')->on('countries')
        ->onUpdate('cascade')->onDelete('restrict');
});
```

FK eklenebilmesi için, geçmişteki `US`/`TR` gibi büyük harfli değerleri
küçük harfe çeviren bir backfill geçişi gerekir.

**Öncelik:** Orta
**Etki:** Breaking (geçersiz satırlar reddedilir)
**Efor:** M

#### Ülke kodlarında büyük-küçük harf tutarlılığı

**Mevcut:** Kodlar küçük harfle saklanıyor (`us`, `tr`) ama birkaç çağrı
noktası hâlâ büyük harf gönderiyor. `trending_charts.country` zaten
`references('code')`'ı zorunlu kılıyor ve bu, uyumsuzlukları reddeder.

**Önerilen:** Bir CHECK constraint ekle (MySQL 8.4 destekliyor) ya da
model mutator'ı üzerinden normalize et:

```php
// app/Models/App.php
public function setOriginCountryAttribute(?string $value): void
{
    $this->attributes['origin_country'] = $value ? strtolower($value) : null;
}
```

**Öncelik:** Düşük
**Etki:** Non-breaking
**Efor:** S

#### `bundle_id` / `package_name` ayırımı

**Mevcut:** `external_id`, tek bir store tanımlayıcısı — iOS'ta sayısal
bir string, Android'de ters DNS paket adı. Aynı kolon her ikisi için de
kullanılıyor.

**Sorun:** Bazı sorgular (ör. deep link'ler, harici API'ler) iOS
uygulamaları için de bundle ID'ye ihtiyaç duyar ve `external_id`'deki
sayısal iTunes ID tek başına yeterli değildir. Şu an bunun için scraper'a
geri dönüyoruz.

**Önerilen:** Identity sync sırasında doldurulan opsiyonel bir
`bundle_id` kolonu ekle.

```php
Schema::table('apps', function (Blueprint $table) {
    $table->string('bundle_id', 191)->nullable()->after('external_id');
    $table->index(['platform', 'bundle_id']);
});
```

Android'de `external_id` zaten bundle ID'dir; `bundle_id`'yi nullable
bırakıp gerektiğinde aynala, ya da doldurmayı atla.

**Öncelik:** Düşük
**Etki:** Non-breaking
**Efor:** S

### 2.2 `app_metrics`

#### `country_code`'u `CHAR(2)`'ye sıkılaştır

**Mevcut:** `country_code VARCHAR(10)` — tarihsel bir kalıntı.

**Sorun:** `(app_id, country_code, date)` unique constraint'i üzerindeki
anahtar alanını israf ediyor ve tutarsız değerlere davetiye çıkarıyor.
Her gerçek değer 2 harfli bir ISO kodudur.

**Önerilen:**

```php
Schema::table('app_metrics', function (Blueprint $table) {
    $table->char('country_code', 2)->change();
    $table->foreign('country_code')
        ->references('code')->on('countries')
        ->onUpdate('cascade')->onDelete('restrict');
});
```

**Öncelik:** Yüksek
**Etki:** Breaking (2 harfli olmayan geçmiş satırlar için backfill gerekir)
**Efor:** M

#### Partitioning stratejisi (uzun vade)

**Mevcut:** `app_metrics` kabaca `apps × countries × days` hızıyla büyür
ve sistemdeki en büyük tablo hâline gelir.

**Önerilen:** Tablo ~50M satırı geçtiğinde `date` üzerinden yıllık
ritimde range-partition uygula. Örnek (ham SQL, Blueprint partition'ları
ifade edemez):

```sql
ALTER TABLE app_metrics
PARTITION BY RANGE (YEAR(date)) (
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION pmax  VALUES LESS THAN MAXVALUE
);
```

Partitioning, herhangi bir retention işinden önce gelmelidir (bkz. §4).

**Öncelik:** Düşük (takip maddesi)
**Etki:** Acil hâle gelmeden önce planlanırsa non-breaking
**Efor:** L

### 2.3 `app_store_listings`

#### En güncel listing aramaları için bileşik index

**Mevcut:** Yalnızca `version_id` üzerine index.

**Sorun:** "X uygulamasının Y dilindeki en güncel listing'i" sorgusu şu
an `(app_id, language)` ile filtrelenip `fetched_at` ile sıralanıyor; bu
da filesort'a düşüyor.

**Önerilen:**

```php
Schema::table('app_store_listings', function (Blueprint $table) {
    $table->index(['app_id', 'language', 'fetched_at']);
});
```

**Öncelik:** Yüksek
**Etki:** Non-breaking
**Efor:** S

#### `checksum` üzerine index

**Mevcut:** Index yok. Değişiklik tespiti sync pipeline'ı sırasında
checksum'u satır satır okuyor.

**Önerilen:**

```php
Schema::table('app_store_listings', function (Blueprint $table) {
    $table->index('checksum');
});
```

**Öncelik:** Orta
**Etki:** Non-breaking
**Efor:** S

#### `title` / `description` için FULLTEXT index (arama desteği)

**Mevcut:** Metin araması için index yok.

**Sorun:** Planlanan uygulama arama endpoint'leri aksi hâlde `LIKE
'%foo%'` taramaları veya harici bir arama motoru gerektirir.

**Önerilen (Blueprint'in `fullText` metoduyla):**

```php
Schema::table('app_store_listings', function (Blueprint $table) {
    $table->fullText(['title', 'description']);
});
```

**Öncelik:** Orta
**Etki:** Non-breaking
**Efor:** M (MySQL yapılandırmasının kontrol edilmesi gerekir: Latin
dışı diller için `ngram` parser)

### 2.4 `app_store_listing_changes`

#### `field_changed` üzerine index

**Mevcut:** `(app_id, detected_at)` ve `version_id` üzerine index var.

**Sorun:** UI değişim günlüğünü alan tipine göre filtreliyor (title,
description, screenshots); bu sorgular uygulama bazında aralıkları
tarıyor.

**Önerilen:**

```php
Schema::table('app_store_listing_changes', function (Blueprint $table) {
    $table->index(['app_id', 'field_changed', 'detected_at']);
});
```

**Öncelik:** Orta
**Etki:** Non-breaking
**Efor:** S

#### Partitioning adayı

**Mevcut:** Hızlı büyüyen, yalnızca-ekleme bir tablo.

**Önerilen:** Tablo birkaç on milyonu geçtiğinde `YEAR(detected_at)`
üzerinden range-partition; §2.2 ile aynı yaklaşım.

**Öncelik:** Düşük (takip maddesi)
**Etki:** Non-breaking
**Efor:** L

### 2.5 `app_versions`

#### `release_date` üzerine index

**Mevcut:** Yalnızca unique `(app_id, version)` index'i.

**Sorun:** "Bu hafta yeni sürüm çıkaran uygulamalar" sorguları tabloyu
tarıyor.

**Önerilen:**

```php
Schema::table('app_versions', function (Blueprint $table) {
    $table->index('release_date');
    $table->index(['app_id', 'release_date']);
});
```

**Öncelik:** Orta
**Etki:** Non-breaking
**Efor:** S

### 2.6 `publishers`

#### Eksik metadata

**Mevcut:** `id, name, external_id, platform, url`.

**Sorun:** Yayıncı profilleri website, destek e-postası ve menşe ülke
alanlarından yoksun — scraper'lar bu alanların hepsini iOS için zaten
döndürüyor.

**Önerilen:**

```php
Schema::table('publishers', function (Blueprint $table) {
    $table->string('website')->nullable()->after('url');
    $table->string('support_email')->nullable()->after('website');
    $table->char('country', 2)->nullable()->after('support_email');

    $table->foreign('country')
        ->references('code')->on('countries')
        ->nullOnDelete();
});
```

**Öncelik:** Düşük
**Etki:** Non-breaking
**Efor:** S

#### Platform kolon adlandırması

**Mevcut:** `platform` tinyint, `apps.platform` ile aynı kural.

**Not:** `apps` ile tutarlı — değişiklik gerekmiyor; ancak §4'teki
platform-enum tartışmasına bakınız.

### 2.7 `store_categories`

#### Bileşik index

**Mevcut:** Beklenen unique key `(platform, external_id)` üzerinde; ağaç
gezintileri için `(platform, parent_id)` üzerinde index yok.

**Önerilen:**

```php
Schema::table('store_categories', function (Blueprint $table) {
    $table->index(['platform', 'parent_id']);
});
```

**Öncelik:** Düşük
**Etki:** Non-breaking
**Efor:** S

### 2.8 `trending_chart_entries`

#### Kapsayıcı index

**Mevcut:** `(trending_chart_id, rank)` ve `(app_id, trending_chart_id)`
üzerine index var.

**Sorun:** Okuma ağırlıklı — leaderboard endpoint'leri `price`,
`currency` alanlarını da satır gövdesinden çekiyor.

**Önerilen:** MySQL 8.4 + InnoDB için PK kümeleme anahtarı zaten olduğu
için filtre kolonlarıyla başlayan bir index'in yeterli olduğunu bil.
Çapraz-chart aramaları için düz bir `(app_id)` index'i yardımcı olur:

```php
Schema::table('trending_chart_entries', function (Blueprint $table) {
    $table->index('app_id');
});
```

**Öncelik:** Düşük
**Etki:** Non-breaking
**Efor:** S

### 2.9 `sync_statuses`

Faz başına ilerleme önerisi için §1.3'e bakınız.

#### `job_id` üzerine index

**Mevcut:** `job_id`, index'siz bir `char(36)` kolonu.

**Sorun:** Worker geri çağırımları çalıştırmayı kapatmak için `job_id`
ile arama yapıyor.

**Önerilen:**

```php
Schema::table('sync_statuses', function (Blueprint $table) {
    $table->index('job_id');
});
```

**Öncelik:** Orta
**Etki:** Non-breaking
**Efor:** S

### 2.10 `user_apps`

#### `updated_at` ve aktivite kolonları ekle

**Mevcut:** Yalnızca `created_at` — tablo yalnızca bir pivot iken
bilinçli bir seçimdi. Artık yalnızca bir pivot değil: UI şimdi
pin'lenmiş ve son açılış durumunu izliyor.

**Önerilen:**

```php
Schema::table('user_apps', function (Blueprint $table) {
    $table->timestamp('updated_at')->nullable()->after('created_at');
    $table->timestamp('pinned_at')->nullable()->after('updated_at');
    $table->timestamp('last_opened_at')->nullable()->after('pinned_at');

    $table->index(['user_id', 'pinned_at']);
    $table->index(['user_id', 'last_opened_at']);
});
```

**Öncelik:** Orta
**Etki:** Non-breaking
**Efor:** S

---

## 3. Çapraz kesen konular

### 3.1 `platform` tinyint enum olarak

Scraper'a bakan her tablo `platform` tinyint kullanıyor (`1` iOS, `2`
Android) ve JSON'a slug olarak serileşiyor. Bu bugün için sorun değil ama:

- Yeni bir platform (ör. Huawei AppGallery) eklenirse üç yerde migration
  ve sabit güncellemesi gerekir.
- `tinyint` değerleri SQL keşfinde opak kalır.

**Seçenekler:**

- Olduğu gibi bırak, eşlemeyi tek bir yerde belgele
  (`app/Support/Platform.php`).
- Foreign key ile bir `platforms` referans tablosuna yükselt.

Henüz bir öneri yok — mevcut düzen düşük sürtünmeli; üçüncü bir platform
yol haritasına girdiğinde yeniden değerlendirilmeli.

### 3.2 Soft delete

Hiçbir tablo şu an `SoftDeletes` kullanmıyor. Kullanıcıya ait satırlar
(`user_apps`, `app_competitors`) için soft delete, UI'daki kazara yıkıcı
eylemleri geri almaya yardımcı olur. Scraper'a ait satırlar (`apps`,
`app_versions`, `app_metrics`) için sert silme doğrudur — kayıtlar her
zaman yeniden kazınabilir.

**Öncelik:** Düşük
**Etki:** Non-breaking
**Efor:** S (tablo başına)

### 3.3 Locale / dil alanı uzunluğu

`app_store_listings.language` `varchar(10)` iken kod içindeki bazı
sabitler `char(5)` kullanıyor. Gerçekte ürettiğimiz BCP-47 etiketleri
(`en-US`, `zh-Hant`) 10'a sığar ama satırlar hiçbir zaman 7 karakteri
geçmez.

**Öneri:** Her yerde `varchar(10)` standardına gel ve beklentiyi
`architecture/data-model.md` içinde belgele. Şema değişikliği yok,
yalnızca bir denetim.

**Öncelik:** Düşük
**Etki:** Non-breaking
**Efor:** S

### 3.4 Retention ve arşivleme

Hızla büyüyen üç tablo:

1. `app_metrics` — ülke başına günlük granülarite.
2. `app_store_listing_changes` — yalnızca-ekleme değişim günlüğü.
3. `trending_chart_entries` — chart başına günlük anlık görüntü.

**Öneri:** Tablo başına bir retention politikası tanımla (ör.
`app_metrics`'i 90 gün günlük granülaritede tut, sonra 1 yıl boyunca
haftalığa, sonra aylığa yuvarla). Şunları yapan gecelik bir artisan
komutu olarak uygula:

1. Eski satırları kardeş tablolarda topla (`app_metrics_weekly`,
   `app_metrics_monthly`).
2. Bir transaction içinde kaynak satırları sil.
3. Gözlemlenebilirlik için sayıları içeren bir log satırı yaz.

Partitioning (§2.2), 2. adımı ucuzlatır; o olmadan büyük `DELETE`
ifadeleri uzun süren InnoDB undo log baskısına neden olur.

**Öncelik:** Düşük (takip maddesi)
**Etki:** Non-breaking
**Efor:** L

---

## 4. Öncelik Matrisi

| # | Öneri | Öncelik | Etki | Efor |
|---|-------|---------|------|------|
| 1.1 | `app_metrics.price` nullable (bilinmiyor / ücretsiz) | Yüksek | Breaking | M |
| 1.2 | `app_metrics.completeness` bitmask | Yüksek | Non-breaking | M |
| 1.3 | `sync_statuses` üzerinde faz başına ilerleme | Orta | Non-breaking | M |
| 1.4 | `apps.is_available`'ı yeniden tanımla/düşür | Yüksek | Breaking (Faz 2) | L |
| 2.1a | `apps.last_synced_at` üzerine index | Yüksek | Non-breaking | S |
| 2.1b | `apps.discovered_from` üzerine index | Düşük | Non-breaking | S |
| 2.1c | Bileşik `apps(platform, is_available, last_synced_at)` | Orta | Non-breaking | S |
| 2.1d | FK `apps.origin_country` → `countries.code` | Orta | Breaking | M |
| 2.1e | Ülke kodu küçük harf normalizasyonu | Düşük | Non-breaking | S |
| 2.1f | `apps.bundle_id` ekle | Düşük | Non-breaking | S |
| 2.2a | `app_metrics.country_code` → `CHAR(2)` + FK | Yüksek | Breaking | M |
| 2.2b | `app_metrics` partitioning | Düşük | Non-breaking | L |
| 2.3a | Bileşik `app_store_listings(app_id, language, fetched_at)` | Yüksek | Non-breaking | S |
| 2.3b | `app_store_listings.checksum` üzerine index | Orta | Non-breaking | S |
| 2.3c | `title`, `description` üzerine FULLTEXT | Orta | Non-breaking | M |
| 2.4a | Index `app_store_listing_changes(app_id, field_changed, detected_at)` | Orta | Non-breaking | S |
| 2.4b | `app_store_listing_changes` partitioning | Düşük | Non-breaking | L |
| 2.5 | `app_versions.release_date` üzerine index | Orta | Non-breaking | S |
| 2.6 | `publishers` website/email/country | Düşük | Non-breaking | S |
| 2.7 | Index `store_categories(platform, parent_id)` | Düşük | Non-breaking | S |
| 2.8 | `trending_chart_entries.app_id` üzerine index | Düşük | Non-breaking | S |
| 2.9 | `sync_statuses.job_id` üzerine index | Orta | Non-breaking | S |
| 2.10 | `user_apps` `updated_at`, `pinned_at`, `last_opened_at` | Orta | Non-breaking | S |
| 3.1 | `platform` enum tartışması | — | — | — |
| 3.2 | Kullanıcıya ait satırlarda soft delete | Düşük | Non-breaking | S |
| 3.3 | Locale alan uzunluğu denetimi | Düşük | Non-breaking | S |
| 3.4 | Retention ve arşivleme politikası | Düşük | Non-breaking | L |

**Önerilen ilk parti (düşük risk, yüksek değer):** 2.1a, 2.3a, 2.5, 2.9,
2.10 — tek bir migration'da birlikte gönderilmesi gereken beş saf index
ekleme.

**Önerilen ikinci parti (tasarım çalışması gerekir):** 1.1, 1.2, 1.3,
2.2a — veri kalitesini ve operasyonel görünürlüğü iyileştiren ama
backfill planlaması gerektiren değişiklikler.

**Önerilen üçüncü parti (yapısal):** 1.4, 2.1d, 3.4 — uygulanmadan önce
uygulama düzeyinde denetim gerektiren daha büyük değişiklikler.
