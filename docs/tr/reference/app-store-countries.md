# App Store Ulkeleri

AppStoreCat, hem iOS hem de Android magazalarinda birden fazla ulkeden veri cekmegi destekler. Ulke destegi, platform bazinda aktivasyon ile `countries` tablosu uzerinden yonetilir.

## Ulke Yapilandirmasi

Her ulkenin iOS ve Android icin bagimsiz aktivasyonu vardir:

```
countries tablosu:
├── code (ISO 3166-1 alpha-2, orn., "us", "tr", "de")
├── name
├── emoji (bayrak)
├── is_active_ios (boolean)
├── is_active_android (boolean)
├── priority (goruntulenme sirasi)
├── ios_languages (desteklenen yerel ayar kodlarinin JSON dizisi)
├── ios_cross_localizable (capraz yerellestirilmis yerel ayar kodlarinin JSON dizisi)
└── android_languages (desteklenen yerel ayar kodlarinin JSON dizisi)
```

## Ulkelerin Kullanimi

### API

```
GET /api/v1/countries
```

Aktif ulkelerin listesini dondurur. Yanit, mevcut baglama (iOS veya Android) gore filtrelenir.

### Yapilandirmada

Islemler icin varsayilan ulke `config/appstorecat.php` dosyasinda ayarlanir:

```php
'default_country' => 'us'
```

### Senkronizasyon Islemlerinde

Bir uygulamayi senkronize ederken backend:

1. Once `us` yerel ayarini dener
2. ABD yerel ayari basarisiz olursa uygulamanin `origin_country` degerine geri doner
3. Yerel ayara ozgu islemler icin ulkenin `ios_languages` veya `android_languages` degerlerini kullanir

## Ulkeleri Etkinlestirme

Ulkeler, `is_active_ios` ve `is_active_android` bayraklari araciligiyla platform bazinda etkinlestirilir. Yalnizca aktif ulkeler API yanitinda gorunur ve su islemler icin kullanilir:

- Magaza aramasi
- Grafik senkronizasyonu
- Liste cekme
- Yorum cekme

## Dil Eslemeleri

Her ulkenin platform basina desteklenen dillerin JSON dizisi vardir:

- **ios_languages**: Bu ulke icin App Store'da desteklenen yerel ayar kodlari (orn., `["en-US", "es-MX"]`)
- **android_languages**: Bu ulke icin Google Play'de desteklenen yerel ayar kodlari (orn., `["en-US", "es-419"]`)
- **ios_cross_localizable**: Ulkeler arasi kullanilabilen iOS yerel ayarlari (capraz yerellestime)

## Notlar

- iOS ve Android bazi durumlarda farkli yerel ayar kodu formatlari kullanir
- Android yorumlari globaldir (ulkeye ozgu degildir), bu nedenle Android yorumlarinda `country_code` nullable olabilir
- Grafik verileri her iki platform icin de her zaman ulkeye ozgudur
- App Store, Google Play'e kiyasla cok daha fazla ulke/yerel ayar kombinasyonuna sahiptir
