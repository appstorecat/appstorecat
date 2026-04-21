# Production Dağıtımı

Bu rehber, AppStoreCat'in production ortamına dağıtımını kapsar.

## Gereksinimler

- Docker ve Docker Compose v2 yüklü bir sunucu
- Bir reverse proxy (Nginx, Caddy, Traefik veya Dokploy)
- Bir alan adı (isteğe bağlı ancak önerilir)

## Adım 1: Ortamı Hazırlayın

Sunucunuzda bir `.env` dosyası oluşturun:

```env
# Uygulama
APP_NAME=AppStoreCat
APP_ENV=production
APP_KEY=base64:...    # Şununla oluşturun: php artisan key:generate --show
APP_DEBUG=false
APP_VERSION=0.0.3

# URL'ler (kendi alan adınızla değiştirin)
APP_URL=https://api.yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

# Scraper URL'leri (dahili Docker ağı)
APPSTORE_API_URL=http://appstorecat-scraper-ios:7462
GPLAY_API_URL=http://appstorecat-scraper-android:7463

# Veritabanı
DB_DATABASE=appstorecat
DB_USERNAME=appstorecat
DB_PASSWORD=your-secure-password
MYSQL_ROOT_PASSWORD=your-root-password

# Kuyruk ve Önbellek (production'da Redis yok)
QUEUE_CONNECTION=database
CACHE_STORE=file

# Loglama
LOG_CHANNEL=stderr

# Portlar
BACKEND_PORT=7460
FRONTEND_PORT=7461
APPSTORE_API_PORT=7462
GPLAY_API_PORT=7463
FORWARD_DB_PORT=3306
```

## Adım 2: Dağıtım

```bash
# Production compose dosyasını çalıştırın
docker compose -f docker-compose.production.yml up -d
```

## Adım 3: Veritabanını Başlatın

```bash
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan migrate
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan db:seed
```

## Adım 4: Reverse Proxy'yi Yapılandırın

Trafiği açığa çıkarılan portlara yönlendirin:

| Alan Adı/Yol | Servis Portu |
|--------------|-------------|
| `api.yourdomain.com` | `appstorecat-server:7460` |
| `app.yourdomain.com` | `appstorecat-web:7461` |

Scraper servisleri yalnızca dahili kullanım içindir ve herkese açık erişilebilir olmamalıdır.

## Güncelleme

```bash
# Yeni image'ları çekin
docker compose -f docker-compose.production.yml pull

# Yeni image'larla yeniden başlatın
docker compose -f docker-compose.production.yml up -d

# Migration'ları çalıştırın
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan migrate
```

## Kuyruk İşçileri

Production ortamında, server container'ı aşağıdakileri yöneten bir Supervisor süreci çalıştırır:

- **Kuyruk işçileri** — Arka plan senkronizasyon ve grafik işlerini işler. Tüm scraper kuyrukları platform ayrıktır: `sync-tracked-{ios,android}`, `sync-on-demand-{ios,android}`, `charts-{ios,android}`. iOS ve Android bağımsız rate limit ve worker profillerine sahiptir.
- **Zamanlayıcı** — Tekrarlayan işleri dağıtır (cron), `ReconcileFailedItemsJob` başarısız sync öğelerini periyodik olarak yeniden kuyruğa alır.

Bunlar production Docker image'ında otomatik olarak yapılandırılmıştır.

## İzleme

### Loglar

```bash
# Tüm servis logları
docker compose -f docker-compose.production.yml logs -f

# Sadece server
docker compose -f docker-compose.production.yml logs -f appstorecat-server
```

### Başarısız İşler

Başarısız arka plan işlerini kontrol edin:

```bash
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan queue:failed
```

Başarısız işleri yeniden deneyin:

```bash
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan queue:retry all
```

## Güvenlik Hususları

- Production ortamında `APP_DEBUG=false` olarak ayarlayın
- Güçlü veritabanı şifreleri kullanın
- Scraper servislerini dahili tutun (herkese açık erişilebilir olmasın)
- Reverse proxy'niz aracılığıyla HTTPS kullanın
- Production ortamında Swagger'ı devre dışı bırakmak için `L5_SWAGGER_GENERATE_ALWAYS=false` olarak ayarlayın
