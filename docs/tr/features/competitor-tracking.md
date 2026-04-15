# Rakip Takibi

Uygulamalar arasinda rakip iliskileri tanimlayin ve magaza varliklarini yan yana izleyin.

![Rakip Takibi](../../screenshots/competitor-tracking.jpeg)

## Genel Bakis

AppStoreCat, takip ettiginiz uygulamalarin rakiplerini tanimlamanizi saglar. Rakip uygulamalar daha sonra kendi uygulamalarinizla birlikte senkronize edilir ve izlenir; boylece listeler, anahtar kelimeler, puanlar ve degisiklikler yan yana karsilastirabilir.

## Nasil Calisir

1. Bir uygulamayi takibe alin
2. Uygulama detay sayfasindan rakip uygulamalar ekleyin
3. Rakipler, takip ettiginiz uygulamalarla ayni programda senkronize edilir
4. Tum rakipler arasinda anahtar kelimeleri, listeleri ve degisiklikleri karsilastirin

## Rakip Iliskileri

Her rakip baglantisi kullaniciya ozeldir: rakip tanimlariniz diger kullanicilardan bagimsizdir. Iliski bir tur ile saklanir (varsayilan: `direct`).

## API

### Rakipleri Listele

```
GET /api/v1/apps/{platform}/{externalId}/competitors
```

Bir uygulama icin tanimlanmis tum rakipleri dondurur.

### Rakip Ekle

```
POST /api/v1/apps/{platform}/{externalId}/competitors
Body: { "competitor_app_id": 123 }
```

### Rakip Kaldir

```
DELETE /api/v1/apps/{platform}/{externalId}/competitors/{competitorId}
```

### Tum Rakip Uygulamalar

```
GET /api/v1/competitors
```

Tum takip ettiginiz uygulamalar genelinde tum rakip uygulamalari dondurur.

## Arayuz

Uygulama detay sayfasindaki **Competitors** sekmesi sunlari gosterir:
- Puanlari ve kategorileriyle birlikte rakip uygulama listesi
- Rakip ekleme butonu (eklemek icin uygulama arama)
- Rakip detay sayfalarina hizli navigasyon

Tum takip ettiginiz uygulamalar genelindeki tum rakip uygulamalari gormek icin kenar cubugundaki **Competitors** sayfasina gidin.

## Teknik Detaylar

- **Model:** `AppCompetitor`
- **Tablo:** `app_competitors`
- **Benzersiz kisitlama:** `(user_id, app_id, competitor_app_id)`
- **Controller:** `CompetitorController`
- **Iliski turu:** `direct` (`relationship` sutununda saklanir)
