# Plan: `app_metrics.price` / `currency` alanlarını doldur

**Durum:** Backlog
**İlgili tablo:** `app_metrics.price`, `app_metrics.currency`

## Sorun

`app_metrics` tablosundaki `price` ve `currency` kolonları şu anda %100
`NULL`. Kolonlar var, migration çoktan atılmış, ama hiçbir sync akışı bu
iki alanı yazmıyor.

## Kök sebep

### iOS tarafı

scraper-ios'un iki farklı handler'ı **aynı** iTunes `/lookup` endpoint'ine
gidiyor:

- `fetchListing` (`scraper-ios/src/scraper.ts:183`) → `store.app(opts)`
  çağırıp response'tan `price` + `currency` **dahil** tüm alanları çıkarıyor.
- `fetchMetrics` (`scraper-ios/src/scraper.ts:253`) → **aynı** `store.app(opts)`
  çağrısını yapıyor, ama response'tan sadece rating/histogram/file_size
  alıyor; `price` ve `currency` atılıyor.

Yani veri iTunes'dan zaten geliyor — sadece metrics handler'ı bu iki alanı
response'a koymuyor.

### Android tarafı

gplay-scraper'ın metrics endpoint'i gerçekten price döndürmüyor (Google Play
detail API'sinin yapısı farklı). Fakat listings endpoint'i döndürüyor ve
Laravel zaten `app_store_listings.price` / `currency` kolonlarına yazıyor.

## Hedef

`app_metrics.price` ve `app_metrics.currency` alanlarını her sync'te
doldur, hiçbir ek scraper çağrısı eklemeden.

## Yaklaşım

### iOS — scraper tek satır fix

`scraper-ios/src/scraper.ts` içindeki `fetchMetrics` fonksiyonuna iki satır
ekle:

```ts
return {
  rating: info.score ?? null,
  rating_count: info.reviews ?? null,
  rating_breakdown: ratingBreakdown,
  file_size_bytes: ...,
  price: info.price ?? 0,         // yeni
  currency: info.currency ?? null, // yeni
};
```

`scraper-ios/src/schemas.ts` içindeki `AppMetrics` şemasına da `price` +
`currency` alanlarını ekle.

Laravel tarafında `saveMetric` çağrısı zaten `$data['price']` /
`$data['currency']` bekleyecek şekilde yazılabilir durumda — sadece saveMetric
gövdesinde bu iki alan `$data`'dan okunup yazılıyor mu kontrol et. Eğer
yoksa oraya da iki satır ekle.

### Android — listings'ten piggyback

`AppSyncer::fetchAndSaveMetric` Android için çağrıldığında (tek bir `zz` satırı)
en son `app_store_listings` satırındaki `price` / `currency` değerlerini
okuyup `saveMetric`'e ver. Android'in listings fazı metrics fazından önce
çalıştığı için veri hazır olacak.

```php
if ($app->isAndroid()) {
    $listing = StoreListing::where('app_id', $app->id)
        ->orderByDesc('fetched_at')
        ->first(['price', 'currency']);
    $data['price'] = $listing?->price;
    $data['currency'] = $listing?->currency;
}
```

## Kapsam dışı

- USD normalizasyonu / çapraz kur çevirisi.
- Fiyat geçmişi analizi (`app_metrics` zaten günlük, tarih boyutu var).
- `fetchListing` ve `fetchMetrics`'in aynı upstream'e gitmesi kaynaklı
  potansiyel çift çağrı ayıklaması — bu ayrı bir refactor konusu, bu planın
  kapsamında değil.

## İnce noktalar

1. **Ücretsiz app'ler**: `price = 0`, `currency = 'USD'` gibi değerler
   geçerli. NULL sadece "app o ülkede yok" durumunda kalmalı.
2. **Ülke yok (404)**: scraper zaten boş dönüyor, `saveMetric` bunu
   `isAvailable: false` ile yazıyor. O satırlarda `price = NULL` kalması
   doğru.
3. **Android `zz`**: tek global satır, en son listing'in fiyatı yeterli
   — locale bazında ayrıştırmaya gerek yok.

## Doğrulama

1. ChatGPT iOS'u track et (paralı storefront varyasyonu: US→USD, TR→TRY,
   GB→GBP).
2. Fresh sync çalıştır.
3. Kontrol:
   - `us, tr, gb` satırlarında `price >= 0`, `currency` storefront ile
     eşleşiyor.
   - `ru, cn` gibi kullanılamayan ülkelerde `price = NULL`,
     `is_available = false`.
4. Android için bir app track et, `zz` satırında price + currency'nin
   listing ile aynı olduğunu doğrula.

## Rollout

- scraper-ios değişikliği için `scraper-ios` imajı yeniden build edilmeli.
- Laravel değişikliği sonrası `make queue-restart` — çalışan worker'lar
  yeni mantığı alsın.
- Şema değişikliği yok, migration yok.
- Tarihsel back-fill opsiyonel: istersen tek seferlik bir artisan komutu
  `app_metrics` satırlarını dolaşıp sibling `app_store_listings` satırından
  fiyat kopyalar. Tarihsel doğruluk önemli değilse at.

## Bitti sayılır

- Fresh sync'te `is_available = true` olan her `app_metrics` satırında
  `price` ve `currency` dolu.
- Unavailable satırlar `NULL` kalıyor.
- Ek scraper çağrısı yok (iOS için request sayısı sync başına sabit
  kalmalı).
