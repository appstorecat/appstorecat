# Trend Listeler

App Store ve Google Play genelinde en populer uygulamalari gunluk liste anlik goruntuleriyle takip edin.

![Trend Listeler](../../screenshots/trending-charts.jpeg)

## Genel Bakis

AppStoreCat, her iki platform icin gunluk trend liste verilerini toplar ve uygulamalarin zaman icinde listelerde nasil hareket ettigini takip etmenizi saglayan gecmis siralamalari saklar.

## Nasil Calisir

1. Laravel zamanlayicisi, her aktif ulke icin `SyncChartSnapshotJob` gonderir
2. Isler `charts-ios` ve `charts-android` kuyruklerinde bagimsiz olarak calisir
3. Her is, scraper'dan bir liste ceker (ornegin ABD iOS En Cok Indirilen Ucretsiz)
4. Siralamalar `ChartSnapshot` + `ChartEntry` kayitlari olarak saklanir
5. Listelerde bulunan uygulamalar otomatik olarak sistemde kesfedilir

## Liste Turleri

| Koleksiyon | Aciklama |
|------------|----------|
| `top_free` | En cok indirilen ucretsiz uygulamalar |
| `top_paid` | En cok indirilen ucretli uygulamalar |
| `top_grossing` | En yuksek gelirli uygulamalar |

Listeler sunlara gore filtrelenebilir:
- **Platform:** iOS veya Android
- **Ulke:** Herhangi bir aktif ulke
- **Kategori:** Magaza kategorileri (Oyunlar, Is, vb.) veya genel

## Liste Derinligi

- **iOS:** Liste basina en fazla 200 uygulama
- **Android:** Liste basina en fazla 100 uygulama

## API

```
GET /api/v1/charts?platform=ios&collection=top_free&country=us&category_id=6014
```

Pozisyon degisikligi gostergeleri icin onceki siralama verileriyle birlikte en son liste anlik goruntusunu dondurur.

## Arayuz

Listeleri gozden gecirmek icin **Discovery > Trending** sayfasina gidin. Arayuz sunlari gosterir:
- Platform ve koleksiyon degistiriciler
- Ulke ve kategori filtreleri
- Degisiklik gostergeleriyle siralama pozisyonu (yukari/asagi/yeni)
- Detay sayfalarina hizli erisimli uygulama bilgileri

## Teknik Detaylar

- **Tablolar:** `trending_charts`, `trending_chart_entries`
- **Is:** `SyncChartSnapshotJob` (kisitlanmis: iOS 24/dk, Android 37/dk)
- **Kuyruklar:** `charts-ios`, `charts-android`
- **Anlik goruntu sikligi:** Gunluk (gun basina platform/koleksiyon/ulke/kategori basina bir anlil goruntu)
- **Yapilandirma:** `CHARTS_IOS_DAILY_SYNC_ENABLED`, `CHARTS_ANDROID_DAILY_SYNC_ENABLED`
