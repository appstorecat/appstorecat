# Magaza Listeleri

Uygulama magaza listelerini birden fazla dilde goruntuleyin ve takip edin.

![Magaza Listesi](../../screenshots/store-listing.jpeg)

## Genel Bakis

AppStoreCat, yerellestirilmis surumleri dahil olmak uzere her uygulama icin tam magaza listesini senkronize eder ve saklar. Listeler yerel ayar bazinda takip edilir, boylece uygulamalarin farkli pazarlarda kendilerini nasil sundugunu izleyebilirsiniz.

## Liste Verileri

Her magaza listesi kaydi sunlari icerir:

| Alan | Aciklama |
|------|----------|
| **Baslik** | Bu yerel ayardaki uygulama adi |
| **Alt Baslik** | Kisa slogan (yalnizca iOS) |
| **Tanitim Metni** | `promotional_text` — yalnizca iOS; Android'de her zaman `null` |
| **Aciklama** | Tam uygulama aciklamasi |
| **Yenilikler** | Surum notlari |
| **Ekran Goruntuleri** | Ekran goruntusu URL'leri dizisi |
| **Ikon** | Uygulama ikonu URL'si (`icon_url`) |
| **Video** | On izleme videosu URL'si |
| **Fiyat** | Yerel para biriminde uygulama fiyati |
| **Para Birimi** | Para birimi kodu |

## Coklu Yerel Ayar Destegi

Listeler `(app_id, locale)` bazinda benzersizdir. Bir uygulama birden fazla yerel ayari desteklediginde, her yerel ayar kendi liste kaydina sahip olur. Uygulamanin `supported_locales` alani mevcut tum yerel ayar kodlarini listeler.

## API

```
GET /api/v1/apps/{platform}/{externalId}/listing?country_code=US&locale=en-US
```

Belirli bir ulke ve yerel ayar icin magaza listesini dondurur. `country_code`, `AppAvailableCountry` kurali ile dogrulanir — uygulama o ulkede yayinda degilse yanit `422` olur. Uygulama hicbir storefront'ta ulasilabilir degilse `AppDetailResource` yanitindaki `unavailable_countries` dizisi eksik ulkeleri listeler.

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
- **Tablo:** `app_store_listings` (`locale` sutunu BCP-47 kodunu tutar; `promotional_text` iOS icin kullanilir)
- **Benzersiz kisitlama:** `(app_id, locale)`
- **Senkronizasyon adimi:** `AppSyncer::syncListing()` (pipeline'in `listings` fazi)
- **Degisiklik algilama:** Her senkronizasyonda checksum tabanli karsilastirma
- **Dogrulama:** `country_code` icin `AppAvailableCountry` kurali; uygulama ulkede yoksa 422
