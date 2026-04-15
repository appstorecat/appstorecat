# Puanlar ve Yorumlar

Uygulama puanlarini izleyin ve her iki magazadan kullanici yorumlarini senkronize edin.

![Puanlar ve Yorumlar](../../screenshots/ratings-reviews.jpeg)

## Genel Bakis

AppStoreCat iki tur yorum verisini takip eder: toplu metrikler (puan, puan sayisi, dagilim) ve bireysel kullanici yorumlari. Her ikisi de duzeli uygulama senkronizasyon dongusunde senkronize edilir.

## Puan Metrikleri

Uygulama puanlarinin gunluk anlik goruntuleri `app_metrics` tablosunda saklanir:

| Metrik | Aciklama |
|--------|----------|
| **Puan** | Ortalama puan (ondalik, ornegin 4.56) |
| **Puan Sayisi** | Toplam puan sayisi |
| **Puan Dagilimi** | Yildiz bazinda dagilim `{1: 100, 2: 50, 3: 200, 4: 500, 5: 1200}` |
| **Puan Degisimi** | Onceki gune gore puan sayisindaki degisim |

## Kullanici Yorumlari

Bireysel yorumlar her iki magazadan senkronize edilir:

| Alan | Aciklama |
|------|----------|
| **Yazar** | Yorumcu adi |
| **Baslik** | Yorum basligi (yalnizca iOS) |
| **Icerik** | Yorum metni |
| **Puan** | 1-5 yildiz |
| **Yorum Tarihi** | Yorumun gonderildigi tarih |
| **Uygulama Surumu** | Yorum sirasindaki uygulama surumu |
| **Ulke** | Ulke kodu (yalnizca iOS; Android yorumlari globaldir) |

## API

### Yorum Listesi

```
GET /api/v1/apps/{platform}/{externalId}/reviews?country_code=US&rating=5&sort=latest&per_page=25
```

Filtreler: `country_code`, `rating` (1-5), `sort` (latest, oldest, highest, lowest).

### Yorum Ozeti

```
GET /api/v1/apps/{platform}/{externalId}/reviews/summary
```

Toplu istatistikleri ve puan dagilimini dondurur.

## Arayuz

Uygulama detay sayfasindaki **Reviews** sekmesi sunlari gosterir:
- Yildiz dagilim grafigi ile puan ozeti
- Filtrelenebilir yorum listesi
- Siralama secenekleri (en yeni, en eski, en yuksek, en dusuk)
- Ulke filtresi (iOS)

## Teknik Detaylar

- **Modeller:** `Review`, `AppMetric`
- **Tablolar:** `app_reviews`, `app_metrics`
- **Benzersiz kisitlamalar:** Yorumlar: `(app_id, external_id)`, Metrikler: `(app_id, date)`
- **Senkronizasyon adimi:** `AppSyncer::syncMetrics()`, `AppSyncer::syncReviews()`
- **Sayfalama:** Scraper'dan sayfa basina en fazla 200 yorum
- **Yapilandirma:** `SYNC_{PLATFORM}_REVIEWS_ENABLED`
