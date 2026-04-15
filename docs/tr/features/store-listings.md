# Magaza Listeleri

Uygulama magaza listelerini birden fazla dilde goruntuleyin ve takip edin.

![Magaza Listesi](../../screenshots/store-listing.jpeg)

## Genel Bakis

AppStoreCat, yerellestirilmis surumleri dahil olmak uzere her uygulama icin tam magaza listesini senkronize eder ve saklar. Listeler dil bazinda takip edilir, boylece uygulamalarin farkli pazarlarda kendilerini nasil sundugunu izleyebilirsiniz.

## Liste Verileri

Her magaza listesi kaydi sunlari icerir:

| Alan | Aciklama |
|------|----------|
| **Baslik** | Bu dildeki uygulama adi |
| **Alt Baslik** | Kisa slogan (yalnizca iOS) |
| **Aciklama** | Tam uygulama aciklamasi |
| **Yenilikler** | Surum notlari |
| **Ekran Goruntuleri** | Ekran goruntusu URL'leri dizisi |
| **Ikon** | Uygulama ikonu URL'si |
| **Video** | On izleme videosu URL'si |
| **Fiyat** | Yerel para biriminde uygulama fiyati |
| **Para Birimi** | Para birimi kodu |

## Coklu Dil Destegi

Listeler `(app_id, language)` bazinda benzersizdir. Bir uygulama birden fazla yerel ayari desteklediginde, her yerel ayar kendi liste kaydina sahip olur. Uygulamanin `supported_locales` alani mevcut tum yerel ayar kodlarini listeler.

## API

```
GET /api/v1/apps/{platform}/{externalId}/listing?country=us&language=en-US
```

Belirli bir ulke ve dil icin magaza listesini dondurur.

## Degisiklik Takibi

Bir liste her senkronize edildiginde icerigi checksum'lanir. Checksum degistiginde her alan ayri ayri karsilastirilir ve degisiklikler `app_store_listing_changes` tablosuna kaydedilir. Detaylar icin [Degisiklik Algilama](./change-detection.md) sayfasina bakin.

## Arayuz

Uygulama detay sayfasindaki **Listing** sekmesi sunlari gosterir:
- Baslik, alt baslik ve aciklama
- Ekran goruntuleri galerisi
- Yerel ayarlar arasinda gecis icin dil secici
- Surum bilgisi ve surum notlari

## Teknik Detaylar

- **Model:** `StoreListing`
- **Tablo:** `app_store_listings`
- **Benzersiz kisitlama:** `(app_id, language)`
- **Senkronizasyon adimi:** `AppSyncer::syncListing()`
- **Degisiklik algilama:** Her senkronizasyonda checksum tabanli karsilastirma
