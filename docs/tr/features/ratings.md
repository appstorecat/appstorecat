# Puanlar

Uygulama puanlarini ulke bazinda gunluk anlik goruntuler halinde izleyin.

![Puanlar](../../screenshots/ratings-reviews.jpeg)

## Genel Bakis

AppStoreCat, her senkronizasyon dongusunde toplu puan metriklerini (ortalama puan, puan sayisi, yildiz dagilimi) ulke bazinda kaydeder.

## Puan Metrikleri

Uygulama puanlarinin gunluk anlik goruntuleri `app_metrics` tablosunda saklanir ve her `(app_id, country_code, date)` kombinasyonu icin ayri bir kayit olusur:

| Metrik | Aciklama |
|--------|----------|
| **Puan** | Ortalama puan (ondalik, ornegin 4.56) |
| **Puan Sayisi** | Toplam puan sayisi |
| **Puan Dagilimi** | Yildiz bazinda dagilim `{1: 100, 2: 50, 3: 200, 4: 500, 5: 1200}` (`rating_breakdown` JSON) |
| **Puan Degisimi** | Onceki gune gore puan sayisindaki degisim |

`app_metrics.country_code` `CHAR(2)` tipindedir ve `countries.code` FK'sine baglanir. Android metriklerinde, magaza global veri dondurdugu icin `zz` "Global" ISO sentinel'i kullanilir; `/countries` endpoint'i bu sentinel'i yanitlarindan filtreler.

`app_metrics.price` nullable'dir: `null` bilinmeyen, `0` dogrulanmis ucretsiz demektir. Her kaydin `is_available` bayragi, uygulamanin o ulkede o gun ulasilabilir olup olmadigini belirler; `apps.is_available` ise "en az bir magazada ulasilabilir" anlamina gelir.

## Arayuz

Uygulama detay sayfasindaki **Overview** / **Metrics** gorunumu puan ozetini, yildiz dagilim grafigini ve zaman icindeki puan sayisi trendini gosterir.

## Teknik Detaylar

- **Model:** `AppMetric`
- **Tablo:** `app_metrics`
- **Benzersiz kisitlama:** `(app_id, country_code, date)`
- **Senkronizasyon adimi:** `AppSyncer::syncMetrics()` (metrics fazi)
