# Kuyruk Sistemi

AppStoreCat, iOS ve Android pipeline'larinin birbirini asla engellemeyeceginden emin olmak icin platform ayrimli kuyruklar kullanir.

## Kuyruk Mimarisi

```
                    ┌─ sync-tracked-ios ──────▶ SyncAppJob (iOS takip edilen)
                    ├─ sync-tracked-android ──▶ SyncAppJob (Android takip edilen)
                    ├─ sync-discovery-ios ────▶ SyncAppJob (iOS kesfedilen)
Zamanlayici ───────▶├─ sync-discovery-android ▶ SyncAppJob (Android kesfedilen)
                    ├─ sync-on-demand-ios ────▶ SyncAppJob (UI tetikli eskimis yenileme, iOS)
                    ├─ sync-on-demand-android ▶ SyncAppJob (UI tetikli eskimis yenileme, Android)
                    ├─ charts-ios ────────────▶ SyncChartSnapshotJob (iOS)
                    ├─ charts-android ────────▶ SyncChartSnapshotJob (Android)
                    ├─ discover ──────────────▶ Kesif job'lari
                    └─ default ───────────────▶ Genel job'lar + ReconcileFailedItemsJob
```

## Kuyruklar

| Kuyruk | Amac | Job |
|--------|------|-----|
| `sync-tracked-ios` | Takip edilen iOS uygulamalarini senkronize et | `SyncAppJob` |
| `sync-tracked-android` | Takip edilen Android uygulamalarini senkronize et | `SyncAppJob` |
| `sync-discovery-ios` | Kesfedilen iOS uygulamalarini senkronize et | `SyncAppJob` |
| `sync-discovery-android` | Kesfedilen Android uygulamalarini senkronize et | `SyncAppJob` |
| `sync-on-demand-ios` | Eskimis iOS uygulamalari icin UI tetikli yenileme | `SyncAppJob` |
| `sync-on-demand-android` | Eskimis Android uygulamalari icin UI tetikli yenileme | `SyncAppJob` |
| `charts-ios` | iOS chart goruntuleri | `SyncChartSnapshotJob` |
| `charts-android` | Android chart goruntuleri | `SyncChartSnapshotJob` |
| `discover` | Uygulama kesfi | Cesitli |
| `default` | Genel amacli job'lar | `ReconcileFailedItemsJob` dahil cesitli |

## Job'lar

### SyncAppJob

Tek bir uygulamanin tum pipeline fazlarini (identity → listings → metrics → finalize) calistirir ve `sync_statuses` uzerinden ilerlemeyi takip eder.

- **Kuyruk:** Platforma ozel senkronizasyon kuyrugu (`sync-tracked-*`, `sync-discovery-*` veya `sync-on-demand-*`)
- **Benzersiz:** Uygulama ID'si basina, 1 saatlik pencere (tekrar senkronizasyonu onler)
- **Yeniden deneme:** `[30, 60, 120]` saniye geri cekilme ile 3 deneme
- **Throttle:** Redis tabanli, platform bazinda (iOS: 5/dk, Android: 5/dk)
- **Blok zaman asimi:** 300 saniye (throttle slotu icin bekler)
- **404 isleme:** Scraper'dan gelen 404 `empty_response` olarak siniflandirilir — ilgili ulke icin kalici olarak "mevcut degil" isaretlenir, sonsuza kadar yeniden denenmez

### SyncChartSnapshotJob

Bir chart goruntusu getirir (ornegin top_free iOS US) ve siralamalari kaydeder.

- **Kuyruk:** `charts-ios` veya `charts-android`
- **Yeniden deneme:** `[60, 300]` saniye geri cekilme ile 2 deneme
- **Throttle:** Redis tabanli (iOS: 24/dk, Android: 37/dk)
- **Yan etki:** Chart sonuclarindan yeni uygulamalar kesfeder

### FetchChartSnapshotJob

`SyncChartSnapshotJob` ile aynidir ancak Redis throttle engeli yoktur. Kullanici arayuzunden talep uzerine chart getirmeleri icin kullanilir.

- **Yeniden deneme:** `[30, 60, 120]` saniye geri cekilme ile 3 deneme

### ReconcileFailedItemsJob

Onceki bir geziyle `sync_statuses.failed_items` icine yazilan ogeleri yeniden dener. Neden etiketi basina yapilandirilmis maksimum deneme sayisina saygi duyar (`empty_response` gibi kalici reason'lar atlanir).

- **Kuyruk:** `default`
- **Zamanlama:** `sync_statuses.next_retry_at` tarafindan yonlendirilir
- **Kapsam:** Olu olmayan ogeleri ayni Redis throttle kurallari altinda pipeline'a geri besler

## Throttling

Scraper'a bagli tum job'lar magaza hiz sinirlarini asmayi onlemek icin Redis throttle kullanir:

```php
// Ornek: iOS senkronizasyon throttle
Redis::throttle('sync-job:ios')
    ->allow(5)          // 5 job
    ->every(60)         // dakikada
    ->block(300)        // slot icin en fazla 300 saniye bekle
    ->then(fn() => ...)
```

### Throttle Anahtarlari

| Anahtar | Izin | Basina | Platform |
|---------|------|--------|----------|
| `sync-job:ios` | 5 | 60s | iOS |
| `sync-job:android` | 5 | 60s | Android |
| `chart-job:ios` | 24 | 60s | iOS |
| `chart-job:android` | 37 | 60s | Android |

Oranlar ortam degiskenleri araciligiyla yapilandirilabilir (bkz. [Yapilandirma](../getting-started/configuration.md)).

## Kuyruk Suruculeri

| Ortam | Surucu | Notlar |
|-------|--------|--------|
| Gelistirme | `redis` | Hizli, bellek ici. Redis ayrica onbellek ve throttling'i de yonetir |
| Uretim | `database` | Kalici. Uretimde Redis kullanilmaz |

## Worker Yapilandirmasi

Worker'lar atanan kuyruklarindaki job'lari isler. Uretimde Laravel Supervisor worker'lari yonetir. Gelistirmede yerlesik zamanlayici job gonderimini yonetir.

Kod degisikliklerinden sonra worker'lari yeniden baslatin:

```bash
make queue-restart
```

## Yeni Job Ekleme

Yeni scraper ile iliskili job'lar olusturulurken:

1. Her zaman platform ayrimli kuyruklar kullanin (`{queue}-ios` ve `{queue}-android`)
2. Uygun connector'in hiz yapilandirmasiyla Redis throttle uygulatin
3. Ustel geri cekilme ile yeniden deneme uygulayin
4. Tekrar islemeyi onlemek icin `ShouldBeUnique` kullanmayi degerlendirin
5. Gecici hatalari `sync_statuses.failed_items` icine yazin ki `ReconcileFailedItemsJob` onlari uzlastirabilsin
