# Uygulama Kesfi

Arama, trend listeler, yayinci sayfalari ve daha fazlasi araciligiyla yeni uygulamalar kesfet.

![Uygulama Kesfi](../../screenshots/app-discovery.jpeg)

## Genel Bakis

AppStoreCat, kullanici etkilesimleri araciligiyla uygulamalari organik olarak kesfeder. Her arama, liste goruntulemesi veya yayinci sayfasi ziyareti, sisteme yeni uygulamalar tanitabilir.

## Kesif Kaynaklari

| Kaynak | Nasil Tetiklenir |
|--------|-----------------|
| **Arama** | Uygulama aramasi, magaza sonuclarini dondurur ve kayitlar olusturur |
| **Trend** | Gunluk liste senkronizasyonu en populer uygulamalari otomatik olarak kesfeder |
| **Yayinci Uygulamalari** | Bir yayincinin uygulama katalogu goruntulenmesi onun uygulamalarini kesfeder |
| **Kayit** | API uzerinden bir uygulamanin acikca kaydedilmesi |
| **Iceri Aktarma** | Bir yayincinin tum uygulamalarinin toplu olarak iceri aktarilmasi |
| **Dogrudan Ziyaret** | Bir uygulamanin magaza kimligiyle ziyaret edilmesi |

Her kaynak, `config/appstorecat.php` dosyasindaki `discover` anahtari altinda platform bazinda etkinlestirilebilir/devre disi birakilabilir.

## Nasil Calisir

1. Kullanici bir eylem gerceklestirir (arama, listeleri goruntuler, yayinci ziyaret eder)
2. Backend, magaza verilerini cekmek icin uygun scraper'i cagrir
3. `App::discover()`, `discovered_from` etiketiyle yeni bir uygulama kaydi olusturur
4. Uygulama, kesif kuyrugundan arka plan senkronizasyonu icin siraya alinir

## Arama

```
GET /api/v1/apps/search?term=instagram&platform=ios&country=us
```

Scraper uzerinden magazada gercek zamanli arama yapar ve eslesen uygulamalari dondurur. Sonuclar her iki platform icin normalize edilir.

## Arayuz

Uygulama aramak icin **Discovery > Apps** sayfasina gidin. Arayuz sunlari saglar:
- Gecikme sureli arama girisi
- Platform secici (iOS / Android)
- Ulke secici
- Uygulama ikonu, adi, yayincisi ve puani iceren sonuclar

## Teknik Detaylar

- **Controller:** `AppSearchController`
- **Connector'lar:** Her iki connector uzerinde `fetchSearch()`
- **Kesif kuyrugu:** `discover`
- **Yapilandirma:** `appstorecat.discover.{platform}.on_search` (ve diger `on_*` anahtarlari)
