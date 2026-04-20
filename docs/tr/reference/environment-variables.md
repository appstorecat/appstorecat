# Ortam Degiskenleri

AppStoreCat tarafindan kullanilan tum ortam degiskenlerinin tam referansi.

## Uygulama

| Degisken | Varsayilan | Zorunlu | Aciklama |
|----------|------------|---------|----------|
| `APP_NAME` | `AppStoreCat` | Hayir | Uygulama adi |
| `APP_ENV` | `local` | Evet | `local` veya `production` |
| `APP_KEY` | — | Evet | Sifreleme anahtari (`php artisan key:generate` ile olusturun) |
| `APP_DEBUG` | `true` | Hayir | Hata ayiklama modunu etkinlestir (production'da `false`) |
| `APP_URL` | `http://localhost:7460` | Evet | Backend API temel URL'si |
| `FRONTEND_URL` | `http://localhost:7461` | Evet | Frontend URL'si (CORS icin kullanilir) |
| `APP_LOCALE` | `en` | Hayir | Varsayilan uygulama dili |

## Veritabani

| Degisken | Varsayilan | Zorunlu | Aciklama |
|----------|------------|---------|----------|
| `DB_CONNECTION` | `mysql` | Hayir | Veritabani surucusu |
| `DB_HOST` | `appstorecat-mysql` | Evet | MySQL sunucu adi |
| `DB_PORT` | `3306` | Hayir | MySQL portu |
| `DB_DATABASE` | `appstorecat` | Evet | Veritabani adi |
| `DB_USERNAME` | `sail` | Evet | Veritabani kullanici adi |
| `DB_PASSWORD` | `password` | Evet | Veritabani sifresi |

## Scraper'lar

| Degisken | Varsayilan | Zorunlu | Aciklama |
|----------|------------|---------|----------|
| `APPSTORE_API_URL` | `http://host.docker.internal:7462` | Evet | App Store scraper temel URL'si |
| `GPLAY_API_URL` | `http://host.docker.internal:7463` | Evet | Google Play scraper temel URL'si |
| `APPSTORE_TIMEOUT` | `30` | Hayir | App Store istek zaman asimi (saniye) |
| `GPLAY_TIMEOUT` | `30` | Hayir | Google Play istek zaman asimi (saniye) |

## Hiz Sinirlamalari

| Degisken | Varsayilan | Aciklama |
|----------|------------|----------|
| `APPSTORE_THROTTLE_SYNC_JOBS` | `5` | Dakika basina maksimum iOS senkronizasyon gorev sayisi |
| `GPLAY_THROTTLE_SYNC_JOBS` | `5` | Dakika basina maksimum Android senkronizasyon gorev sayisi |
| `APPSTORE_THROTTLE_CHART_JOBS` | `24` | Dakika basina maksimum iOS grafik cekme gorev sayisi |
| `GPLAY_THROTTLE_CHART_JOBS` | `37` | Dakika basina maksimum Android grafik cekme gorev sayisi |

## Senkronizasyon Yapilandirmasi

| Degisken | Varsayilan | Aciklama |
|----------|------------|----------|
| `SYNC_IOS_TRACKED_ENABLED` | `true` | Takip edilen iOS uygulama senkronizasyonunu etkinlestir |
| `SYNC_IOS_TRACKED_REFRESH_HOURS` | `24` | Takip edilen iOS senkronizasyonlari arasindaki saat |
| `SYNC_IOS_DISCOVERY_ENABLED` | `true` | Kesfedilen iOS uygulama senkronizasyonunu etkinlestir |
| `SYNC_IOS_DISCOVERY_REFRESH_HOURS` | `24` | Kesfedilen iOS senkronizasyonlari arasindaki saat |
| `SYNC_ANDROID_TRACKED_ENABLED` | `true` | Takip edilen Android uygulama senkronizasyonunu etkinlestir |
| `SYNC_ANDROID_TRACKED_REFRESH_HOURS` | `24` | Takip edilen Android senkronizasyonlari arasindaki saat |
| `SYNC_ANDROID_DISCOVERY_ENABLED` | `true` | Kesfedilen Android uygulama senkronizasyonunu etkinlestir |
| `SYNC_ANDROID_DISCOVERY_REFRESH_HOURS` | `24` | Kesfedilen Android senkronizasyonlari arasindaki saat |

## Kesif Kaynaklari

Her kaynak, platform bazinda bagimsiz olarak acilip kapatilabilir. Kaynak kapali iken bu yoldan gelen bilinmeyen uygulamalar veritabanina eklenmez.

| Degisken | Varsayilan | Aciklama |
|----------|------------|----------|
| `DISCOVER_IOS_ON_UNKNOWN` | `true` | Bilinmeyen kaynaklarda iOS kesfini etkinlestir |
| `DISCOVER_IOS_ON_SEARCH` | `true` | Magaza aramasinda iOS kesfini etkinlestir |
| `DISCOVER_IOS_ON_TRENDING` | `true` | Trend listelerinde iOS kesfini etkinlestir |
| `DISCOVER_IOS_ON_PUBLISHER_APPS` | `true` | Yayinci uygulama listelerinde iOS kesfini etkinlestir |
| `DISCOVER_IOS_ON_REGISTER` | `true` | Kullanici kayit akisinda iOS kesfini etkinlestir |
| `DISCOVER_IOS_ON_IMPORT` | `true` | Yayinci import'unda iOS kesfini etkinlestir |
| `DISCOVER_IOS_ON_SIMILAR` | `true` | Benzer uygulamalar uzerinden iOS kesfini etkinlestir |
| `DISCOVER_IOS_ON_CATEGORY` | `true` | Kategori listelerinde iOS kesfini etkinlestir |
| `DISCOVER_IOS_ON_DIRECT_VISIT` | `false` | Harici ID ile dogrudan ziyarette iOS kesfini etkinlestir (kapaliyken bilinmeyen uygulama URL'leri 404 dondurur) |
| `DISCOVER_ANDROID_ON_UNKNOWN` | `true` | Bilinmeyen kaynaklarda Android kesfini etkinlestir |
| `DISCOVER_ANDROID_ON_SEARCH` | `true` | Magaza aramasinda Android kesfini etkinlestir |
| `DISCOVER_ANDROID_ON_TRENDING` | `true` | Trend listelerinde Android kesfini etkinlestir |
| `DISCOVER_ANDROID_ON_PUBLISHER_APPS` | `true` | Yayinci uygulama listelerinde Android kesfini etkinlestir |
| `DISCOVER_ANDROID_ON_REGISTER` | `true` | Kullanici kayit akisinda Android kesfini etkinlestir |
| `DISCOVER_ANDROID_ON_IMPORT` | `true` | Yayinci import'unda Android kesfini etkinlestir |
| `DISCOVER_ANDROID_ON_SIMILAR` | `true` | Benzer uygulamalar uzerinden Android kesfini etkinlestir |
| `DISCOVER_ANDROID_ON_CATEGORY` | `true` | Kategori listelerinde Android kesfini etkinlestir |
| `DISCOVER_ANDROID_ON_DIRECT_VISIT` | `false` | Harici ID ile dogrudan ziyarette Android kesfini etkinlestir (kapaliyken bilinmeyen uygulama URL'leri 404 dondurur) |

## Grafikler

| Degisken | Varsayilan | Aciklama |
|----------|------------|----------|
| `CHART_IOS_DAILY_SYNC_ENABLED` | `true` | Gunluk iOS grafik senkronizasyonunu etkinlestir |
| `CHART_ANDROID_DAILY_SYNC_ENABLED` | `true` | Gunluk Android grafik senkronizasyonunu etkinlestir |

## Kuyruk ve Onbellek

| Degisken | Varsayilan | Aciklama |
|----------|------------|----------|
| `QUEUE_CONNECTION` | `redis` | Kuyruk surucusu (gelistirme: `redis`, production: `database`) |
| `CACHE_STORE` | `redis` | Onbellek surucusu (gelistirme: `redis`, production: `file`) |
| `REDIS_HOST` | `appstorecat-redis` | Redis sunucu adi |
| `REDIS_PORT` | `6379` | Redis portu |
| `REDIS_PASSWORD` | `null` | Redis sifresi |

## Oturum

| Degisken | Varsayilan | Aciklama |
|----------|------------|----------|
| `SESSION_DRIVER` | `database` | Oturum depolama surucusu |
| `SESSION_LIFETIME` | `120` | Oturum suresi (dakika) |
| `BCRYPT_ROUNDS` | `12` | Sifre hashleme maliyeti |

## Loglama

| Degisken | Varsayilan | Aciklama |
|----------|------------|----------|
| `LOG_CHANNEL` | `stack` | Log kanali (gelistirme: `stack`, production: `stderr`) |
| `LOG_LEVEL` | `debug` | Minimum log seviyesi |

## Swagger

| Degisken | Varsayilan | Aciklama |
|----------|------------|----------|
| `L5_SWAGGER_GENERATE_ALWAYS` | `true` | API dokumantasyonunu otomatik olustur (production'da `false`) |

## Docker Compose Portlari

Bunlar kok `.env` dosyasinda ayarlanir (`server/.env` degil):

| Degisken | Varsayilan | Servis |
|----------|------------|--------|
| `BACKEND_PORT` | `7460` | Backend API |
| `FRONTEND_PORT` | `7461` | Frontend |
| `APPSTORE_API_PORT` | `7462` | App Store scraper |
| `GPLAY_API_PORT` | `7463` | Google Play scraper |
| `FORWARD_DB_PORT` | `7464` | MySQL (harici) |
| `FORWARD_REDIS_PORT` | `6379` | Redis (harici) |
