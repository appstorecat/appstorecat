# Veri Toplama

AppStoreCat, uygulama verilerini normal magaza etkilesimleri araciligiyla organik olarak toplar. Toplu tarama veya seri gezinme yoktur — tum veriler dogal kullanici eylemleri ve arka plan senkronizasyon donguleri uzerinden akar.

## Uygulamalar Sisteme Nasil Girer

Uygulamalar kullanici etkilesimleri yoluyla kesfedilir ve bir `discovered_from` kaynak etiketiyle saklanir:

| Kaynak | Tetikleyici | Aciklama |
|--------|-------------|----------|
| `on_search` | Kullanici uygulama arar | Magaza arama sonuclari uygulama kayitlari olusturur |
| `on_trending` | Gunluk chart senkronizasyonu | Trend listelerindeki en iyi uygulamalar kesfedilir |
| `on_publisher_apps` | Bir yayincinin uygulamalarini goruntuleme | Gelistirici uygulama listeleri kayit olusturur |
| `on_register` | Kullanici API uzerinden uygulama kaydeder | Dogrudan uygulama kaydi — yalnizca daha onceden kesfedilmis uygulamalar icin kabul edilir |
| `on_import` | Yayinci toplu aktarimi | Bir yayincinin tum uygulamalari iceri aktarilir (her `external_id` DB'de zaten bulunmalidir) |
| `on_direct_visit` | Bir uygulamayi ID ile goruntuleme | **Varsayilan olarak kapalidir** (`DISCOVER_{IOS,ANDROID}_ON_DIRECT_VISIT=false`). Bilinmeyen uygulamalar icin dogrudan URL ziyaretleri 404 doner — once arama/chart ile kesif gerekir |

Her kaynak `config/appstorecat.php` dosyasinda platform bazinda etkinlestirilebilir/devre disi birakilabilir.

## Senkronizasyon Katmanlari

Kesfedildikten sonra uygulamalar tek bir zamanlayici komutu (`appstorecat:apps:sync-tracked`) tarafindan katmanli geri dusus ile senkronize edilir. Her platform icin 20 dakikada bir `SYNC_{PLATFORM}_TRACKED_BATCH_SIZE` kadar uygulama su siraya gore secilir:

1. Eskiyen takip edilen uygulamalar (kullanicilarin `user_apps` kayitlari)
2. Kendisi takip edilmeyen, eskiyen rakip uygulamalar (`app_competitors.competitor_app_id`)
3. Birikmis havuzdan en eski senkronize edilmis diger uygulamalar

Tum katmanlar ayni **24 saatlik** eskime penceresini ve ayni `sync-tracked-{platform}` kuyrugunu paylasir.

- Tam veri senkronizasyonu: kimlik, listeler, metrikler, surumler
- Kuyruk: `sync-tracked-ios` / `sync-tracked-android`
- Kontrol: `SYNC_{PLATFORM}_TRACKED_REFRESH_HOURS`, `SYNC_{PLATFORM}_TRACKED_BATCH_SIZE`

### Arayuzden Talep Uzerine Yenileme

Bir kullanici `last_synced_at` degeri yenileme esiginden eski olan bir uygulama detay veya liste sayfasini actiginda, API ilgili platform icin `sync-on-demand-ios` / `sync-on-demand-android` kuyruguna bir `SyncAppJob` gonderir. Bu ozel havuz, kullanici tetikli yenilemelerin cron tabanli kesif birikiminin arkasinda beklememesini saglar. Arayuz ilerlemeyi `GET /apps/{platform}/{externalId}/sync-status` ile sorgular ve kullanici `POST /apps/{platform}/{externalId}/sync` ile aciktan yenileme tetikleyebilir.

## Neler Senkronize Edilir

`AppSyncer` pipeline'i su fazlarda calisir ve her adimin durumunu `sync_statuses` tablosuna yazar:

```
1. identity    → Uygulama metadata'si (ad, yayinci, kategori, diller)
                 Basarisiz olursa pipeline durur.
2. listings    → Tum aktif ulke + locale icin magaza listeleri
3. metrics     → Ulke basina puan / rating_count / is_available
                 (Android metrikleri `zz` Global sentinel ulkesi altinda toplanir)
4. finalize    → `apps.last_synced_at`, degisiklik tespiti, checksumlar
5. reconciling → `ReconcileFailedItemsJob` gecici hatalari yeniden dener
```

Anahtar kelime yogunlugu bir senkronizasyon adimi **degildir**; API cagrildiginda saklanan liste uzerinde aninda hesaplanir. Ayri bir kalici saklama veya yeniden indeksleme gerekmez.

## Throttle Oranlari

Her platformun magaza hiz sinirlarini asmayi onlemek icin bagimsiz Redis tabanli throttle oranlari vardir:

| Platform | Senkronizasyon Job'lari | Chart Job'lari |
|----------|------------------------|----------------|
| iOS (App Store) | 5/dakika | 24/dakika |
| Android (Google Play) | 5/dakika | 37/dakika |

Throttle'i asan job'lar bir slot acilana kadar bekler (en fazla 300 saniye). Bu, hiz sinirlarini tetiklemeden istikrarli ve surdurulebilir veri toplama saglar.

Laravel zamanlayicisi her platformda `appstorecat:apps:sync-tracked` komutunu her **20 dakikada** bir gonderir; takip edilen, rakip ve birikmis uygulamalar arasinda katmanli geri dusus yapar. Varsayilan dakikada 5 uygulama hiziyla bu, dongu basina ~100 uygulama siralanmasini saglar ve senkronizasyon havuzunu takvim ile eslesik tutar.

## Chart Toplama

Trend chart'lari her aktif ulke icin gunluk olarak senkronize edilir:

- **Koleksiyonlar:** `top_free`, `top_paid`, `top_grossing`
- **Derinlik:** Chart basina 200'e kadar uygulama
- **Kuyruklar:** `charts-ios` / `charts-android`
- Chart'lardan kesfedilen uygulamalar `discovered_from: trending` olarak etiketlenir

## Degisiklik Tespiti

Bir liste senkronize edildiginde icerigi bir `checksum` olarak hashlenir. Checksum onceki senkronizasyondan farkliysa **ve** onceki liste farkli bir `app_version`'a aitse:

1. Her alan (title, subtitle, promotional_text, description, whats_new, screenshots) tek tek karsilastirilir
2. Degisen alanlar eski/yeni degerleriyle `StoreListingChange` kayitlari olusturur
3. Locale eklemeleri ve kaldirmalari da `locale_added` / `locale_removed` olarak takip edilir

Ayni surum icinde yapilan upsert'ler atlanir, bu da scraper'in tek bir pass'te ayni locale'i birden fazla kez yakaladigi durumlarda olusabilecek sahte kayitlari onler.

Bu ozellik, web'deki **Degisiklikler** sekmesini besler; takip edilen ve rakip uygulamalar arasindaki magaza listesi degisikliklerinin zaman cizelgesini gosterir.

## Basarisiz Oge Uzlastirma

Gezi bir oge icin basarisiz olursa (ornegin gecici 5xx), bu `sync_statuses.failed_items` icinde bir reason etiketiyle saklanir. `ReconcileFailedItemsJob` bu ogeleri neden basina yapilandirilan maksimum deneme sayisina gore yeniden dener. Scraper'dan gelen 404 yanitlari `empty_response` olarak siniflandirilir ve kalici "bu storefront'ta mevcut degil" olarak ele alinir — yeniden denenmez.
