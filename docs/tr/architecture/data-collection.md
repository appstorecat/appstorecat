# Veri Toplama

AppStoreCat, uygulama verilerini normal magaza etkilesimleri araciligiyla organik olarak toplar. Toplu tarama veya seri gezinme yoktur — tum veriler dogal kullanici eylemleri ve arka plan senkronizasyon donguleri uzerinden akar.

## Uygulamalar Sisteme Nasil Girer

Uygulamalar kullanici etkilesimleri yoluyla kesfedilir ve bir `discovered_from` kaynak etiketiyle saklanir:

| Kaynak | Tetikleyici | Aciklama |
|--------|-------------|----------|
| `on_search` | Kullanici uygulama arar | Magaza arama sonuclari uygulama kayitlari olusturur |
| `on_trending` | Gunluk chart senkronizasyonu | Trend listelerindeki en iyi uygulamalar kesfedilir |
| `on_publisher_apps` | Bir yayincinin uygulamalarini goruntuleme | Gelistirici uygulama listeleri kayit olusturur |
| `on_register` | Kullanici API uzerinden uygulama kaydeder | Dogrudan uygulama kaydi |
| `on_import` | Yayinci toplu aktarimi | Bir yayincinin tum uygulamalari iceri aktarilir |
| `on_direct_visit` | Bir uygulamayi ID ile goruntuleme | Dogrudan URL erisimi uygulamayi kesfeder |

Her kaynak `config/appstorecat.php` dosyasinda platform bazinda etkinlestirilebilir/devre disi birakilabilir.

## Senkronizasyon Katmanlari

Kesfedildikten sonra uygulamalar iki farkli takvimde senkronize edilir:

### Takip Edilen Uygulamalar (Oncelikli Katman)

Kullanicilar tarafindan acikca takip edilen uygulamalar. Varsayilan olarak her **24 saatte** bir senkronize edilir.

- Tam veri senkronizasyonu: kimlik, liste, metrikler, incelemeler, surumler
- Kuyruk: `sync-tracked-ios` / `sync-tracked-android`
- Kontrol: `SYNC_{PLATFORM}_TRACKED_REFRESH_HOURS`

### Kesif Uygulamalari (Arka Plan Katmani)

Kesfedilmis ancak takip edilmeyen uygulamalar. Varsayilan olarak her **24 saatte** bir senkronize edilir.

- Takip edilen uygulamalarla ayni tam senkronizasyon, ancak daha dusuk oncelik
- Kuyruk: `sync-discovery-ios` / `sync-discovery-android`
- Kontrol: `SYNC_{PLATFORM}_DISCOVERY_REFRESH_HOURS`

### Arayuzden Talep Uzerine Yenileme

Bir kullanici `last_synced_at` degeri yenileme esiginden eski olan bir uygulama detay veya liste sayfasini actiginda, API ilgili platform icin `sync-on-demand-ios` / `sync-on-demand-android` kuyruguna bir `SyncAppJob` gonderir. Bu ozel havuz, kullanici tetikli yenilemelerin cron tabanli kesif birikiminin arkasinda beklememesini saglar.

## Neler Senkronize Edilir

Her senkronizasyon dongusu (`AppSyncer` tarafindan yonetilir) bu adimlari sirasiyla gerceklestirir:

```
1. Identity    → Uygulama metadata'si (ad, yayinci, kategori, diller)
2. Version     → Mevcut surum numarasi ve yayin tarihi
3. Listing     → Magaza listesi (baslik, aciklama, dil basina ekran goruntuleri)
4. Changes     → Onceki listeyle farklari tespit et (checksum tabanli)
5. Metrics     → Puan, puan sayisi, dagilim, dosya boyutu
6. Reviews     → Kullanici incelemeleri (sayfalanmis, sayfa basina 200'e kadar)
```

Anahtar kelime yogunlugu artik bir senkronizasyon adimi **degildir**; API cagrildiginda saklanan liste uzerinde aninda hesaplanir. Ayri bir kalici saklama veya yeniden indeksleme gecisi gerekmez.

## Throttle Oranlari

Her platformun magaza hiz sinirlarini asmayi onlemek icin bagimsiz Redis tabanli throttle oranlari vardir:

| Platform | Senkronizasyon Job'lari | Chart Job'lari |
|----------|------------------------|----------------|
| iOS (App Store) | 5/dakika | 24/dakika |
| Android (Google Play) | 5/dakika | 37/dakika |

Throttle'i asan job'lar bir slot acilana kadar bekler (en fazla 300 saniye). Bu, hiz sinirlarini tetiklemeden istikrarli ve surdurulebilir veri toplama saglar.

Laravel zamanlayicisi her platformda `sync-discovery` ve `sync-tracked` komutlarini her **20 dakikada** bir gonderir — dakikada 5 uygulama ile bu, dongu basina ~100 uygulama siralanmasini saglar ve senkronizasyon havuzunu takvim ile eslesik tutar.

## Chart Toplama

Trend chart'lari her aktif ulke icin gunluk olarak senkronize edilir:

- **Koleksiyonlar:** `top_free`, `top_paid`, `top_grossing`
- **Derinlik:** Chart basina 200'e kadar uygulama
- **Kuyruklar:** `charts-ios` / `charts-android`
- Chart'lardan kesfedilen uygulamalar `discovered_from: trending` olarak etiketlenir

## Degisiklik Tespiti

Bir liste senkronize edildiginde icerigi bir `checksum` olarak hashlenir. Checksum onceki senkronizasyondan farkliysa:

1. Her alan (title, subtitle, description, whats_new, screenshots) tek tek karsilastirilir
2. Degisen alanlar eski/yeni degerleriyle `StoreListingChange` kayitlari olusturur
3. Dil eklemeleri ve kaldirmalari da takip edilir

Bu ozellik, web'deki **Degisiklikler** sekmesini besler; takip edilen ve rakip uygulamalar arasindaki magaza listesi degisikliklerinin zaman cizelgesini gosterir.
