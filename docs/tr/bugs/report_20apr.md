# Sync Pipeline Bug Raporu — 20 Nisan 2026

Branch: `feat/sync-pipeline-rewrite`
Bağlam: Multi-country sync refactor'ü sonrasında üç app üzerinde yapılan
smoke test (ChatGPT `6448311069`, Virtual Phone Number
`io.sms.virtualnumber`, Mavi `927394856`) birkaç bug'ı açığa çıkardı. Bu
dosya canlı semptomu, scraper'lara karşı nasıl yeniden üretildiğini ve
kök sebebi kayıt altına alır — hiçbir fix burada uygulanmıyor, bu bir
kontrol listesi.

## Özet

| # | Bug | Önem | Katman |
|---|-----|------|--------|
| 1 | Scraper "App not found (404)" durumunda 500 dönüyor | Yüksek | scraper-ios |
| 2 | `classifyError` 500 içine sarılmış 404'ü `http_500` olarak sınıflandırıyor | Yüksek | Laravel AppSyncer |
| 3 | `syncIdentity` fallback'i `origin_country` kullanıyor ama discovery'de değer `us` kalıyor | Yüksek | Laravel AppSyncer + App::discover |
| 4 | Identity başarısız olduktan sonra `syncAll` listing/metrics phase'lerini çalıştırıyor | Yüksek | Laravel AppSyncer |
| 5 | `app_metrics.price` / `currency` her zaman null | Orta | scraper-ios + AppSyncer |
| 6 | `app_metrics.rating_breakdown` başarılı sync'lerde bile zaman zaman null geliyor | Orta | scraper-ios + pipeline |
| 7 | `progress_total` sayacı phase'ler arası sıfırlanıyor | Düşük | SyncStatus tracking |
| 8 | `apps.is_available` global ama availability country-specific | Orta | veri modeli |

---

## Bug 1 — Scraper "App not found" için 500 dönüyor

**Semptom (repro):**

```bash
curl -s -w "\nHTTP=%{http_code}\n" \
  "http://localhost:7462/apps/6448311069/metrics?country=ru"
# {"error":"App not found (404)"}
# HTTP=500

curl -s -w "\nHTTP=%{http_code}\n" \
  "http://localhost:7462/apps/6448311069/listings?country=hk&lang=zh-Hant"
# {}
# HTTP=500
```

Scraper body "App not found (404)" diyor ama HTTP status `500`.

**Kök sebep:**

`scraper-ios/src/main.ts` içindeki her route handler'ın gözü kapalı bir
catch bloğu var:

```ts
} catch (e: any) {
  return reply.status(500).send({ error: e.message });
}
```

`app-store-scraper`'ın `store.app()` fonksiyonu storefront'ta app yoksa
`Error("App not found (404)")` fırlatıyor. Route HTTP status'u
belirlemeden önce error'u incelemiyor.

**Etki:**

Laravel "app bu country'de yok" (kalıcı) ile "scraper / upstream Apple
hatası" (geçici) arasında ayrım yapamıyor. Her 404-olarak-500 yanıt bug
#2'ye, oradan da bug #3'e akıyor.

**Gözlemlenen scraper davranışı:**

ChatGPT (`6448311069`) şu country'lerde 500 dönüyor: cn, hk, mo, ru, by,
ve, zh-Hans, zh-Hant. Mavi (`927394856`) `tr` dışında her country'de 500
dönüyor.

---

## Bug 2 — `classifyError` sarılmış 404'ü `http_500` olarak işaretliyor

**Semptom:**

ChatGPT'nin `sync_statuses.failed_items`'ı cn / ru / hk / mo / by / ve
için `reason: "http_500"` gösteriyor — her error mesajı
`500 - App not found (404)` şeklinde. `http_500`
`max_attempts.http_500 = 10` ile eşleşiyor, yani reconciliation bu
item'ları 10 kez yeniden deniyor; app o storefront'larda gerçekten yokken.

**Kök sebep:**

`AppSyncer::classifyError` string match'leri şu sırayla kontrol ediyor:

```php
if (str_contains($lower, '429') || ...) return HTTP_429;
if (str_contains($lower, '500') || ...) return HTTP_500;   // önce match oluyor
if (str_contains($lower, 'timeout') ...) return TIMEOUT;
if (str_contains($lower, 'not found') || str_contains($lower, '404')) return EMPTY_RESPONSE;
```

Error metni hem "500" hem "404" içeriyor — hangisi önce test edilirse o
kazanıyor. "500" "not found"'dan önce listelendiği için her sarılmış 404
`http_500` olarak işaretleniyor.

**Bağımlılık:** Bug #1 ile doğrudan bağlı. Scraper doğru status dönse
bile bu sınıflandırıcı savunmacı olmalı çünkü Laravel error'ı
`"Scraper API request failed: 500 - ..."` şeklinde sarıyor.

---

## Bug 3 — Identity fallback `origin_country` dışına çıkmıyor

**Semptom (repro):**

Mavi'nin doğrudan scraper probe'u:

```bash
for c in us tr gb de jp; do
  curl -s -w " HTTP=%{http_code}\n" "http://localhost:7462/apps/927394856/identity?country=$c"
done
# us: {"error":"App not found (404)"} HTTP=500
# tr: {"app_id":"927394856","name":"Mavi",...} HTTP=200
# gb/de/jp: 404
```

Mavi sadece Türk storefront'ta var, yine de Laravel onu
`apps.is_available=0` olarak işaretliyor.

**Kök sebep:**

`AppSyncer::syncIdentity` iki country adayı deniyor:

1. `'us'` (hardcoded)
2. `$app->origin_country` — sadece `'us'` değilse

Mavi'nin satırında `origin_country='us'` (country verilmediğinde
`App::discover` tarafından atanan default). İki deneme de US
storefront'a gidiyor, ikisi de 404 dönüyor. Apple TR'de veri tutmasına
rağmen Mavi `tr`'ye karşı hiç test edilmiyor.

Bu satır chart sync ile oluşmamış (`trending_chart_entries.app_id = 102`
eşleşmesi yok); `discovered_from = 8` → `DiscoverSource::DirectVisit`.
Bir kullanıcı app detay sayfasını URL üzerinden açmış. `App::discover`
`$country` argümanıyla çağrıldı ama argümanın default'u `'us'` ve
`AppController` çağrı noktası gerçek storefront'u geçmiyor.

**Etki:**

- Sadece bir-iki storefront'ta (özellikle US dışında) yayında olan her
  app "unavailable" olarak işaretleniyor.
- Identity başarısızlığı bug #4'e zincirleniyor, yüzlerce gereksiz
  scraper çağrısı üretiyor.

---

## Bug 4 — Identity başarısızlığından sonra pipeline devam ediyor

**Semptom:**

Mavi'nin 20 Nisan 15:23–15:24 arası sync'i `scraper-ios`'a **449 istek**
attı; identity başarısız olmasına rağmen. Log'larda üç tekrarlayan
`identity?country=us` denemesi, ardından 37 `(country, language)` çifti
için listing phase, sonra 111 country için metric phase görünüyor —
hepsi üç kez retry edildi, hepsi 500 döndü çünkü app yok.

App 102 için `sync_statuses` da `progress_total=0`, `failed_items` 148
entry barındırıyor.

**Kök sebep:**

`AppSyncer::syncAll`:

```php
$identityData = $this->syncIdentity($app, $syncStatus);
$version = $this->saveVersion($app, $identityData);

$syncStatus->update(['current_step' => STEP_LISTINGS]);
$this->syncListingsPhase($app, $version, $syncStatus);

$syncStatus->update(['current_step' => STEP_METRICS]);
$this->syncMetricsPhase($app, $version, $syncStatus);
```

`syncIdentity` `[]` dönünce (identity fail), `saveVersion` `null`
üretiyor ama sonraki phase'ler yine çalışıyor. Listing ve metrics
kendi retry döngülerine sahip, dolayısıyla ölü bir app
`(3 attempt × 39 locale) + (3 attempt × 111 country) = 450` çağrı
üretiyor.

**Etki:**

Boşa giden scraper bütçesi, boşa giden reconciliation retry'ları
(`failed_items` dolar), yanıltıcı UI (`progress` gerçeğe uymuyor).

---

## Bug 5 — `price` ve `currency` `app_metrics`'te hiç dolmuyor

**Semptom:**

```sql
SELECT COUNT(*), SUM(currency IS NULL) FROM app_metrics WHERE app_id=2;
-- total=105, null_currency=105
```

ChatGPT'nin her metric satırında `price=0`, `currency=NULL`; TR (TRY)
veya GB (GBP) gibi ücretli-currency storefront'larda bile.

**Kök sebep:**

İki bağımsız sebep:

1. Scraper'ın `/apps/:id/metrics` endpoint'i response'ta price/currency
   içermiyor:
   ```json
   {"rating":..., "rating_count":..., "rating_breakdown":..., "file_size_bytes":...}
   ```
   Ne `price` var ne `currency`.

2. `/apps/:id/listings` endpoint'i **storefront başına `price` ve
   `currency` döndürüyor** — ama `AppSyncer::saveMetric` sadece metrics
   response'undan okuyor. Planlanan listing→metric piggyback
   uygulanmadı.

**Etki:**

Refactor'ün başlıca vaadi olan country-specific price/currency bilgisi
eksik. Şema kolonları boş duruyor.

---

## Bug 6 — `rating_breakdown` başarılı sync'lerde bazen null geliyor

**Semptom:**

ChatGPT'nin (`app_id=2`) 105 metric satırı var. 104'ünde
`rating_breakdown` mevcut, birinde (Zimbabwe) yok. Scraper'ı canlı tekrar
çalıştırınca:

```bash
curl "http://localhost:7462/apps/6448311069/metrics?country=zw"
# rating 4.75, rating_count 14106, rating_breakdown {"1":334,"2":133,...}
```

Veri şimdi var. Null orijinal sync sırasında yakalandı.

**Kök sebep:**

`scraper-ios/src/scraper.ts` içindeki `fetchMetrics` iki input'a dayanıyor:

1. `app-store-scraper`'ın lookup çağrısından `info.histogram`.
2. `apps.apple.com/<country>/app/id<id>` üzerindeki
   `serialized-server-data` blob'unu parse eden fallback web scrape
   (`scrapeAppStorePage`).

Web scrape exception'ları sessizce yutuyor. Düşük-trafikli storefront'lar
veya geçici upstream hiccup'larda iki kaynak da histogram döndürmüyor,
scraper `rating_breakdown: null` dönüp partial success'i sinyal
vermiyor.

AppSyncer sadece metric request'i tamamen fail olursa retry ediyor;
"success with missing breakdown" completed sayılıyor. Reconciliation
satırı tekrar ziyaret etmiyor, dolayısıyla tüm sync yeniden koşmadıkça
`null` sonsuza kadar kalıyor.

**Etki:**

Country-level rating breakdown'lar yazı-tura oluyor. "Başarılı yanıt
ama eksik payload" için retry yolu yok.

---

## Bug 7 — `progress_total` phase değişirken flip oluyor

**Semptom:**

- Android app (Virtual Phone Number, `id=101`): `progress_done=1`,
  `progress_total=1`. Gerçek iş: 60 locale listing + 1 GLOBAL metric.
- Mavi (`id=102`): `progress_done=111`, `progress_total=0` — imkânsız
  oran.

**Kök sebep:**

`syncListingsPhase` ve `syncMetricsPhase` her biri giriş noktasında
`$syncStatus->update(['progress_done' => 0, 'progress_total' => N])`
çağırıyor. Metrics listing total'ının üzerine yazınca UI listing
progress bağlamını kaybediyor. Fail path'te (Mavi) son update kazanıyor
ve anlamsız sayılar bırakıyor.

**Etki:**

UI progress bar yanıltıcı. Data corruption değil ama UX regression.

---

## Bug 8 — `apps.is_available` yanlış granularity

**Semptom:**

Mavi: `apps.is_available=0` ama app TR'de canlı (ve muhtemelen komşu
birkaç storefront'ta erişilebilir). ChatGPT: `apps.is_available=1` ama
cn / ru / hk / mo / by / ve'de erişilemez.

**Kök sebep:**

`apps.is_available` tek boolean. `syncIdentity` US (+ origin fallback)
lookup 404 döndüğünde `false` yapıyor. App availability doğal olarak
`(app_id, country_code)`; metric tablosu bunu zaten
`app_metrics.is_available` ile kodluyor — doğru seviye.

**Etki:**

- Dashboard badge ve filtreleri `apps.is_available` okuyor; global
  kabaca bir cevap alıyor.
- Pipeline'lar (sync-tracked, sync-discovery) bunu filtre olarak
  kullanıyor ve US-dışı storefront'larda tamamen canlı app'leri
  atlayabiliyor.
- Country başına `app_metrics.is_available`'e güvendiğimizde
  `apps.is_available` gereksiz bir truth source ve çelişme riski
  taşıyor.

---

## Destekleyici gözlemler

- `discovered_from=8` (DirectVisit) şu an Mavi'nin DB'ye girdiği tek
  yol. DirectVisit kapatmak bug #3'ü bu satır için gizler ama fix
  değildir — Chart discovery de US chart'ta US-dışı bir app
  listelendiğinde veya Apple'ın bölgesel chart'ları yer değiştirdiğinde
  aynı shape'i üretebilir.
- Reconciliation cron 15 dakikada bir çalışıyor. `http_500` olarak
  işaretlenen her `failed_items` şu an bug #2 yüzünden günlerce
  sırada bekliyor.
- Scraper'ın web-scrape fallback'i (`scrapeAppStorePage`) best-effort;
  sessiz fail sadece başka bir veri kaynağı yetkili olduğunda kabul
  edilebilir — breakdown için böyle bir durum yok.

## Öncelik önerisi

Fix sırası — her katman bir sonrakini açar:

1. **Bug 1** (scraper HTTP status) — 2, 3, 4'ün upstream'i.
2. **Bug 2** (classifyError priority) — #1 olmasa da
   reconciliation'ı anlamsız retry'lardan korur.
3. **Bug 4** (identity başarısızlığında pipeline abort) — #3 devam
   ederken boşa giden request fan-out'unun en kötüsünü durdurur.
4. **Bug 3** (multi-country identity fallback + discovery gerçek
   country'yi geçsin) — TR-only, JP-only, DE-only app'leri açar.
5. **Bug 5** (price/currency piggyback) — şemanın vaat ettiği fiyat
   intelligence'ı getirir.
6. **Bug 8** (`apps.is_available` kaldır, `app_metrics.is_available`'a
   yaslan).
7. **Bug 7** (progress sayacı hijyeni).
8. **Bug 6** (rating_breakdown için partial-payload retry).
