# Yayinci Kesfi

Yayincilari arayin, uygulama kataloglarini goruntuleyin ve tum uygulamalarini toplu olarak iceri aktarin.

![Yayinci Kesfi](../../../screenshots/publisher-discovery.jpeg)

## Genel Bakis

AppStoreCat, yayincilari (gelistiriciler) ve uygulama kataloglarini takip eder. Yayincilari arayabilir, tum uygulamalarini goruntuleyebilir ve tum kataloglari tek seferde iceri aktarabilirsiniz.

## Nasil Calisir

Yayincilar, uygulamalar senkronize edildiginde otomatik olarak olusturulur -- yayinci verileri uygulamanin kimlik yanitindan gelir. Yayincilar ayrica arama yoluyla da kesfedilebilir.

## Ozellikler

### Yayinci Arama

Her iki magazada yayincilari ada gore arayin. Sonuclar dogrudan scraper mikroservislerinden gelir.

### Yayinci Uygulama Katalogu

Belirli bir yayinci tarafindan yayinlanan tum uygulamalari goruntuleyin. Bu, magazadan tam gelistirici uygulama listesini ceker.

### Toplu Iceri Aktarma

Tek bir eylemle bir yayincinin tum uygulamalarini veritabaniniza iceri aktarin. Iceri aktarilan her uygulama `on_import` kaynak etiketiyle kesfedilir ve arka plan senkronizasyonu icin siraya alinir.

## API

### Yayinci Ara

```
GET /api/v1/publishers/search?term=google&platform=android&country_code=US
```

### Yayincilari Listele

```
GET /api/v1/publishers
```

Takip ettiginiz uygulamalardaki yayincilari dondurur.

### Yayinci Detaylari

```
GET /api/v1/publishers/{platform}/{externalId}
```

Veritabaninda bulunmayan yayincilar icin `404` doner.

### Yayincinin Magaza Uygulamalari

```
GET /api/v1/publishers/{platform}/{externalId}/store-apps
```

Magazadaki tum uygulamalari ceker (canli scraper cagrisi). Yayinci DB'de yoksa `404` doner.

### Tum Yayinci Uygulamalarini Iceri Aktar

```
POST /api/v1/publishers/{platform}/{externalId}/import
```

`external_ids[*]` alanindaki her kalem ayri ayri dogrulanir; gecersiz bir kimlik tum istegi `422` ile reddeder.

## Arayuz

- **Discovery > Publishers** -- Magazalar genelinde yayinci arama
- **Publishers** (kenar cubugu) -- Takip ettiginiz uygulamalardaki yayincilari goruntuleme
- **Yayinci detay sayfasi** -- Yayinci bilgisi ve iceri aktarma butonuyla uygulama katalogu

## Teknik Detaylar

- **Model:** `Publisher`
- **Tablo:** `publishers`
- **Benzersiz kisitlama:** `(platform, external_id)`
- **Controller:** `PublisherController`
- **Connector metodu:** `fetchDeveloperApps()`, `fetchSearch()`
- **Not:** Android yayinci `external_id` degeri URL kodlanmis karakterler icerebilir (bosluklar, arti isaretleri)
