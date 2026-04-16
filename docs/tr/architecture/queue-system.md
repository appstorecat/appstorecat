# Kuyruk Sistemi

AppStoreCat, iOS ve Android pipeline'larinin birbirini asla engellemeyeceginden emin olmak icin platform ayrimli kuyruklar kullanir.

## Kuyruk Mimarisi

```
                    ┌─ sync-tracked-ios ──────▶ SyncAppJob (iOS takip edilen)
                    ├─ sync-tracked-android ──▶ SyncAppJob (Android takip edilen)
                    ├─ sync-discovery-ios ────▶ SyncAppJob (iOS kesfedilen)
Zamanlayici ───────▶├─ sync-discovery-android ▶ SyncAppJob (Android kesfedilen)
                    ├─ charts-ios ────────────▶ SyncChartSnapshotJob (iOS)
                    ├─ charts-android ────────▶ SyncChartSnapshotJob (Android)
                    ├─ discover ──────────────▶ Kesif job'lari
                    └─ default ───────────────▶ Genel job'lar
```

## Kuyruklar

| Kuyruk | Amac | Job |
|--------|------|-----|
| `sync-tracked-ios` | Takip edilen iOS uygulamalarini senkronize et | `SyncAppJob` |
| `sync-tracked-android` | Takip edilen Android uygulamalarini senkronize et | `SyncAppJob` |
| `sync-discovery-ios` | Kesfedilen iOS uygulamalarini senkronize et | `SyncAppJob` |
| `sync-discovery-android` | Kesfedilen Android uygulamalarini senkronize et | `SyncAppJob` |
| `charts-ios` | iOS chart goruntuleri | `SyncChartSnapshotJob` |
| `charts-android` | Android chart goruntuleri | `SyncChartSnapshotJob` |
| `discover` | Uygulama kesfi | Cesitli |
| `default` | Genel amacli job'lar | Cesitli |

## Job'lar

### SyncAppJob

Tek bir uygulamanin tam verisini senkronize eder (kimlik, liste, metrikler, incelemeler, anahtar kelimeler).

- **Kuyruk:** Platforma ozel senkronizasyon kuyrugu
- **Benzersiz:** Uygulama ID'si basina, 1 saatlik pencere (tekrar senkronizasyonu onler)
- **Yeniden deneme:** `[30, 60, 120]` saniye geri cekilme ile 3 deneme
- **Throttle:** Redis tabanli, platform bazinda (iOS: 3/dk, Android: 2/dk)
- **Blok zaman asimi:** 300 saniye (throttle slotu icin bekler)

### SyncChartSnapshotJob

Bir chart goruntusu getirir (ornegin top_free iOS US) ve siralamalari kaydeder.

- **Kuyruk:** `charts-ios` veya `charts-android`
- **Yeniden deneme:** `[60, 300]` saniye geri cekilme ile 2 deneme
- **Throttle:** Redis tabanli (iOS: 24/dk, Android: 37/dk)
- **Yan etki:** Chart sonuclarindan yeni uygulamalar kesfeder

### FetchChartSnapshotJob

`SyncChartSnapshotJob` ile aynidir ancak Redis throttle engeli yoktur. Kullanici arayuzunden talep uzerine chart getirmeleri icin kullanilir.

- **Yeniden deneme:** `[30, 60, 120]` saniye geri cekilme ile 3 deneme

## Throttling

Scraper'a bagli tum job'lar magaza hiz sinirlarini asmayi onlemek icin Redis throttle kullanir:

```php
// Ornek: iOS senkronizasyon throttle
Redis::throttle('sync-job:ios')
    ->allow(3)          // 3 job
    ->every(60)         // dakikada
    ->block(300)        // slot icin en fazla 300 saniye bekle
    ->then(fn() => ...)
```

### Throttle Anahtarlari

| Anahtar | Izin | Basina | Platform |
|---------|------|--------|----------|
| `sync-job:ios` | 3 | 60s | iOS |
| `sync-job:android` | 2 | 60s | Android |
| `chart-job:ios` | 24 | 60s | iOS |
| `chart-job:android` | 37 | 60s | Android |

Oranlar ortam degiskenleri araciligiyla yapilandirilabiir (bkz. [Yapilandirma](../getting-started/configuration.md)).

## Kuyruk Suruculeri

| Ortam | Surucu | Notlar |
|-------|--------|--------|
| Gelistirme | `redis` | Hizli, bellek ici. Redis ayrica onbellek ve throttling'i de yonetir |
| Uretim | `database` | Kalici. Uretimde Redis kullanilmaz |

## Worker Yapilandirmasi

Worker'lar atanan kuyruklarindaki job'lari isler. Uretimde Laravel Supervisor worker'lari yonetir. Gelistirmede yerlesik zamanlayici job gonderimini yonetir.

Kod degisikliklerinden sonra worker'lari yeniden baslatin:

```bash
docker compose exec appstorecat-server php artisan queue:restart
```

## Yeni Job Ekleme

Yeni scraper ile iliskili job'lar olusturulurken:

1. Her zaman platform ayrimli kuyruklar kullanin (`{queue}-ios` ve `{queue}-android`)
2. Uygun connector'in hiz yapilandirmasiyla Redis throttle uygulatin
3. Ustel geri cekilme ile yeniden deneme uygulayin
4. Tekrar islemeyi onlemek icin `ShouldBeUnique` kullanmayi degerlendirin
