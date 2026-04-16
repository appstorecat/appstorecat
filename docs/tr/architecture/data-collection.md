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

Kesfedilmis ancak takip edilmeyen uygulamalar. Varsayilan olarak her **72 saatte** bir senkronize edilir.

- Takip edilen uygulamalarla ayni tam senkronizasyon, ancak daha dusuk oncelik
- Kuyruk: `sync-discovery-ios` / `sync-discovery-android`
- Kontrol: `SYNC_{PLATFORM}_DISCOVERY_REFRESH_HOURS`

## Neler Senkronize Edilir

Her senkronizasyon dongusu (`AppSyncer` tarafindan yonetilir) bu adimlari sirasiyla gerceklestirir:

```
1. Identity    → Uygulama metadata'si (ad, yayinci, kategori, diller)
2. Version     → Mevcut surum numarasi ve yayin tarihi
3. Listing     → Magaza listesi (baslik, aciklama, dil basina ekran goruntuleri)
4. Changes     → Onceki listeyle farklari tespit et (checksum tabanli)
5. Metrics     → Puan, puan sayisi, dagilim, dosya boyutu
6. Reviews     → Kullanici incelemeleri (sayfalanmis, sayfa basina 200'e kadar)
7. Keywords    → Liste metninden anahtar kelime yogunlugu analizi
```

## Throttle Oranlari

Her platformun magaza hiz sinirlarini asmayi onlemek icin bagimsiz Redis tabanli throttle oranlari vardir:

| Platform | Senkronizasyon Job'lari | Chart Job'lari |
|----------|------------------------|----------------|
| iOS (App Store) | 3/dakika | 24/dakika |
| Android (Google Play) | 2/dakika | 37/dakika |

Throttle'i asan job'lar bir slot acilana kadar bekler (en fazla 300 saniye). Bu, hiz sinirlarini tetiklemeden istikrarli ve surdurulebilir veri toplama saglar.

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
