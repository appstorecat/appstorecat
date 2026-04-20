# Degisiklik Algilama

Takip edilen ve rakip uygulamalar icin magaza listesi degisikliklerini zaman icinde izleyin.

![Degisiklik Algilama](../../screenshots/change-detection.jpeg)

## Genel Bakis

AppStoreCat, her senkronizasyon dongusunde uygulama magaza listelerindeki degisiklikleri algilar. Bir alan degistiginde (baslik, aciklama, ekran goruntuleri vb.), eski ve yeni degerler kaydedilerek uygulamalarin magaza varliklarini nasil gelistirdiklerine dair bir zaman cizelgesi olusturulur.

## Takip Edilen Alanlar

| Alan | Aciklama |
|------|----------|
| `title` | Uygulama basligi degisti |
| `subtitle` | Uygulama alt basligi degisti (iOS) |
| `description` | Uygulama aciklamasi degisti |
| `whats_new` | Surum notlari degisti |
| `screenshots` | Ekran goruntusu seti degisti |
| `promotional_text` | Tanitim metni degisti (yalnizca iOS; Android'de her zaman null) |
| `locale_added` | Desteklenen yeni bir yerel ayar eklendi |
| `locale_removed` | Desteklenen bir yerel ayar kaldirildi |

## Nasil Calisir

1. Her senkronizasyonda, liste icerigi bir `checksum` olarak hashlenir
2. Checksum onceki senkronizasyonla eslesirse degisiklik olmamistir -- atlanir
3. Checksum farkliysa her alan ayri ayri karsilastirilir
4. Degisen her alan icin asagidaki bilgilerle bir `StoreListingChange` kaydi olusturulur:
   - Alan adi (`field_changed`)
   - Eski deger
   - Yeni deger
   - Algilama zaman damgasi
   - Yerel ayar (`locale` sutunu)

## Yerel Ayar Degisiklik Algilama

Bireysel alan degisikliklerinin otesinde, AppStoreCat yerel ayar duzeyindeki degisiklikleri de takip eder:

- **`locale_added`:** Bir uygulama yeni bir dili desteklemeye basladiginda
- **`locale_removed`:** Bir uygulama bir dil icin destegi biraktiginda

Bunlar, senkronizasyonlar arasinda `supported_locales` dizisinin karsilastirilmasiyla algilanir.

## API

### Takip Edilen Uygulama Degisiklikleri

```
GET /api/v1/changes/apps?field=title
```

Tum takip edilen uygulamalar icin degisiklikleri dondurur. Alan turune gore filtrelenebilir.

### Rakip Degisiklikleri

```
GET /api/v1/changes/competitors?field=description
```

Tum rakip uygulamalar icin degisiklikleri dondurur.

## Arayuz

Degisiklik zaman cizelgesini gormek icin **Changes > Apps** veya **Changes > Competitors** sayfasina gidin. Her degisiklik sunlari gosterir:
- Uygulama adi ve ikonu
- Degisen alan
- Fark vurgulamali eski ve yeni degerler
- Yerel ayar ve zaman damgasi

## Teknik Detaylar

- **Model:** `StoreListingChange`
- **Tablo:** `app_store_listing_changes` (`locale` sutunu BCP-47 kodunu tutar)
- **Indeksler:** `(app_id, detected_at)`, `(version_id)`, `(app_id, locale)`, `(field_changed)`
- **Senkronizasyon adimi:** `AppSyncer::syncListing()` (alan degisiklikleri), `AppSyncer::detectLocaleChanges()` (yerel ayar degisiklikleri)
- **Controller:** `ChangeMonitorController`
- **Algilama yontemi:** Checksum tabanli (birlesik liste iceriginin SHA-256'si)
