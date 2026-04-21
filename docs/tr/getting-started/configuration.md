# Yapılandırma

AppStoreCat, ortam değişkenleri ve merkezi bir yapılandırma dosyası aracılığıyla yapılandırılır. Tüm yapılandırma server servisindedir.

## Ortam Değişkenleri

Backend `.env` dosyası (`server/.env`) temel ayarları kontrol eder. Örnek dosyadan kopyalayın:

```bash
cp server/.env.example server/.env
```

### Uygulama

| Değişken | Varsayılan | Açıklama |
|----------|------------|----------|
| `APP_ENV` | `local` | Ortam: `local`, `production` |
| `APP_DEBUG` | `true` | Hata ayıklama modunu etkinleştir |
| `APP_URL` | `http://localhost:7460` | Backend API URL'si |
| `FRONTEND_URL` | `http://localhost:7461` | Frontend URL'si (CORS için) |

### Veritabanı

| Değişken | Varsayılan | Açıklama |
|----------|------------|----------|
| `DB_HOST` | `appstorecat-mysql` | MySQL host (Docker servis adı) |
| `DB_PORT` | `3306` | MySQL portu |
| `DB_DATABASE` | `appstorecat` | Veritabanı adı |
| `DB_USERNAME` | `sail` | Veritabanı kullanıcısı |
| `DB_PASSWORD` | `password` | Veritabanı şifresi |

### Scraper URL'leri

| Değişken | Varsayılan | Açıklama |
|----------|------------|----------|
| `APPSTORE_API_URL` | `http://host.docker.internal:7462` | App Store scraper URL'si |
| `GPLAY_API_URL` | `http://host.docker.internal:7463` | Google Play scraper URL'si |

### Kuyruk ve Önbellek

| Değişken | Varsayılan | Açıklama |
|----------|------------|----------|
| `QUEUE_CONNECTION` | `redis` | Kuyruk sürücüsü: `redis` (geliştirme), `database` (prodüksiyon) |
| `CACHE_STORE` | `redis` | Önbellek sürücüsü |
| `REDIS_HOST` | `appstorecat-redis` | Redis host |

## AppStoreCat Yapılandırması

Ana yapılandırma dosyası `server/config/appstorecat.php`'dir. Ayarlar 4 bölüme ayrılmıştır:

### Connector Ayarları

Backend'in scraper mikroservisleriyle nasıl iletişim kurduğunu kontrol eder:

| Değişken | Varsayılan | Açıklama |
|----------|------------|----------|
| `APPSTORE_TIMEOUT` | `30` | App Store scraper istek zaman aşımı (saniye) |
| `GPLAY_TIMEOUT` | `30` | Google Play scraper istek zaman aşımı (saniye) |
| `APPSTORE_THROTTLE_SYNC_JOBS` | `5` | Dakikada maksimum iOS sync job sayısı |
| `GPLAY_THROTTLE_SYNC_JOBS` | `5` | Dakikada maksimum Android sync job sayısı |
| `APPSTORE_THROTTLE_CHART_JOBS` | `24` | Dakikada maksimum iOS chart job sayısı |
| `GPLAY_THROTTLE_CHART_JOBS` | `37` | Dakikada maksimum Android chart job sayısı |

### Sync Ayarları

Platform bazında otomatik uygulama senkronizasyonunu kontrol eder:

| Değişken | Varsayılan | Açıklama |
|----------|------------|----------|
| `SYNC_IOS_TRACKED_ENABLED` | `true` | Takip edilen iOS uygulamaları için sync'i etkinleştir |
| `SYNC_IOS_TRACKED_REFRESH_HOURS` | `24` | Takip edilen iOS uygulamaları sync aralığı (saat) |
| `SYNC_IOS_TRACKED_BATCH_SIZE` | `5` | 20 dakikalık tur başına dağıtılan maksimum iOS uygulaması |
| `SYNC_ANDROID_TRACKED_ENABLED` | `true` | Takip edilen Android uygulamaları için sync'i etkinleştir |
| `SYNC_ANDROID_TRACKED_REFRESH_HOURS` | `24` | Takip edilen Android uygulamaları sync aralığı (saat) |
| `SYNC_ANDROID_TRACKED_BATCH_SIZE` | `5` | 20 dakikalık tur başına dağıtılan maksimum Android uygulaması |

Zamanlayıcı her platformda `appstorecat:apps:sync-tracked` komutunu 20 dakikada bir çalıştırır. Eskimiş takip edilen uygulamalar batch'i doldurmazsa komut sırasıyla rakip uygulamalara (`app_competitors`) ve birikmiş havuzdaki en eski uygulamalara düşer; tüm katmanlar aynı 24 saatlik eskime penceresini ve `sync-tracked-{platform}` kuyruğunu paylaşır.

Sync pipeline'ı fazlara ayrılmıştır ve `sync_statuses` tablosu üzerinden izlenir: **identity → listings → metrics → finalize → reconciling**. Başarısız öğeler `ReconcileFailedItemsJob` tarafından toplanıp yeniden denenir.

### Chart Ayarları

Günlük trend chart senkronizasyonunu kontrol eder:

| Değişken | Varsayılan | Açıklama |
|----------|------------|----------|
| `CHART_IOS_DAILY_SYNC_ENABLED` | `true` | Günlük iOS chart sync'ini etkinleştir |
| `CHART_ANDROID_DAILY_SYNC_ENABLED` | `true` | Günlük Android chart sync'ini etkinleştir |

### Keşif Ayarları

Veritabanında yeni uygulama keşfedebilecek (oluşturabilecek) eylemleri kontrol eder. Her kaynak, `DISCOVER_{IOS,ANDROID}_ON_{KAYNAK}` ortam değişkenleri ile platform bazında açılıp kapatılabilir:

| Kaynak | Varsayılan | Açıklama |
|--------|------------|----------|
| `on_search` | `true` | Mağaza araması ile bulunan uygulamalar |
| `on_trending` | `true` | Trend listelerinde bulunan uygulamalar |
| `on_publisher_apps` | `true` | Yayıncının uygulama listesi ile bulunan uygulamalar |
| `on_register` | `true` | Kullanıcılar tarafından doğrudan kaydedilen uygulamalar |
| `on_import` | `true` | Yayıncı import'u ile içe aktarılan uygulamalar |
| `on_similar` | `true` | Benzer uygulamalar üzerinden bulunan uygulamalar |
| `on_category` | `true` | Kategori listelerinde bulunan uygulamalar |
| `on_direct_visit` | `false` | Harici ID ile doğrudan ziyaret edilen uygulamalar — kapalıyken bilinmeyen uygulama URL'leri 404 döner |
| `on_unknown` | `true` | Bilinmeyen kaynaklardan gelen uygulamalar |

## Docker Compose Portları

Kök dizindeki `docker-compose.yml` dosyası bu port değişkenlerini kullanır (kök `.env` dosyasında ayarlanır):

| Değişken | Varsayılan | Servis |
|----------|------------|--------|
| `BACKEND_PORT` | `7460` | Laravel API |
| `FRONTEND_PORT` | `7461` | React web |
| `APPSTORE_API_PORT` | `7462` | App Store scraper |
| `GPLAY_API_PORT` | `7463` | Google Play scraper |
| `FORWARD_DB_PORT` | `7464` | MySQL |
| `FORWARD_REDIS_PORT` | `6379` | Redis |
