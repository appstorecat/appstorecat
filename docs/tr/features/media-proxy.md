# Medya Proxy

Tutarli ve hizli yukleme icin uygulama ikonlarini ve ekran goruntularini yerel bir proxy uzerinden sunun.

![Medya Proxy](../../screenshots/media-proxy.jpeg)

## Genel Bakis

AppStoreCat, uygulama ikonlarini ve ekran goruntularini yerel bir proxy uzerinden sunabilir veya dogrudan magazalardan gelen ham CDN URL'lerini kullanabilir. Varsayilan olarak, basitlik icin ham CDN URL'leri kullanilir.

## Gezgin Sayfalari

Gezgin ozelligi, veritabaninizdaki tum uygulamalar genelinde ekran goruntularini ve ikonlari gozden gecirmenizi saglar:

### Ekran Goruntusu Gezgini

```
GET /api/v1/explorer/screenshots?platform=ios&category_id=6014&search=game&per_page=12
```

Platform, kategori ve arama filtreleriyle tum senkronize uygulamalar genelinde ekran goruntularini gezin.

### Ikon Gezgini

```
GET /api/v1/explorer/icons?platform=android&search=social&per_page=12
```

Tum senkronize uygulamalar genelinde uygulama ikonlarini gezin.

## Arayuz

Gorsel varliklari gozden gecirmek icin **Explorer > Screenshots** veya **Explorer > Icons** sayfasina gidin:

- **Screenshots** -- Platform ve kategori filtreleriyle uygulama ekran goruntuleri izgara gorunumu
- **Icons** -- Arama ile uygulama ikonlari izgara gorunumu

Her iki sayfa da sayfalama ve aramayi destekler.

## Teknik Detaylar

- **Controller:** `ExplorerController`
- **Metodlar:** `screenshots()`, `icons()`
- **Veri kaynagi:** `app_store_listings` tablosu (`screenshots` JSON alani, `icon_url` alani)
- **URL formati:** Ham magaza CDN URL'leri (App Store `mzstatic.com`, Google Play `play-lh.googleusercontent.com` kullanir)
