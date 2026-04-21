# Uygulama Kesfi

Arama, trend listeler, yayinci sayfalari ve daha fazlasi araciligiyla yeni uygulamalar kesfet.

![Uygulama Kesfi](../../../screenshots/app-discovery.jpeg)

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
| **Dogrudan Ziyaret** | Bir uygulamanin magaza kimligiyle ziyaret edilmesi (varsayilan olarak devre disi) |

Her kaynak, `config/appstorecat.php` dosyasindaki `discover` anahtari altinda platform bazinda etkinlestirilebilir/devre disi birakilabilir. `on_direct_visit` varsayilan olarak kapalidir — veritabaninda bulunmayan bir uygulamanin URL'sine dogrudan gidilirse API `404` doner.

## Nasil Calisir

1. Kullanici bir eylem gerceklestirir (arama, listeleri goruntuler, yayinci ziyaret eder)
2. Backend, magaza verilerini cekmek icin uygun scraper'i cagrir
3. `App::discover()`, `discovered_from` etiketiyle yeni bir uygulama kaydi olusturur
4. Uygulama, arka plan senkronizasyonu icin zamanlayicinin katmanli geri dusus akisina (takip edilen → rakip → birikmis) birakilir; zamanlayici `sync-tracked-ios` veya `sync-tracked-android` kuyruguna bir `SyncAppJob` gonderir
5. Senkronizasyon `sync_statuses` tablosunda faz faz (identity → listings → metrics → finalize → reconciling) takip edilir; identity asamasi basarisiz olursa tum pipeline durur ve uygulama "kullanilamaz" olarak isaretlenir

## Arama

```
GET /api/v1/apps/search?term=instagram&platform=ios&country_code=US
```

Scraper uzerinden magazada gercek zamanli arama yapar ve eslesen uygulamalari dondurur. Sonuclar her iki platform icin normalize edilir. `country_code` iki harfli ISO kodudur ve `countries.code` uzerinden dogrulanir.

## Arayuz

Uygulama aramak icin **Discovery > Apps** sayfasina gidin. Arayuz sunlari saglar:
- Gecikme sureli arama girisi
- Platform secici (iOS / Android)
- Ulke secici
- Uygulama ikonu, adi, yayincisi ve puani iceren sonuclar

## Teknik Detaylar

- **Controller:** `AppSearchController`
- **Connector'lar:** Her iki connector uzerinde `fetchSearch()`
- **Senkronizasyon kuyruklari:** `sync-tracked-ios`, `sync-tracked-android` (platform ayrimli; zamanlayici takip edilen, rakip ve birikmis uygulamalari ayni kuyruga besler)
- **Yapilandirma:** `appstorecat.discover.{platform}.on_search` (ve diger `on_*` anahtarlari; `on_direct_visit` varsayilan `false`)
- **404 sozlesmesi:** Scraper'lar, bir uygulama bir magazada bulunamadiginda 404 dondurur; bu durumlar `sync_statuses.failed_items` uzerinden `ReconcileFailedItemsJob` tarafindan sonradan ele alinir.
