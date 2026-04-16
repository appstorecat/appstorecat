# Docker ile Dağıtım

AppStoreCat hem geliştirme hem de production ortamında tamamen Docker ile çalışır.

## Geliştirme Ortamı

Geliştirme ortamı (`docker-compose.yml`) 6 container çalıştırır:

| Container | Image | Port |
|-----------|-------|------|
| `appstorecat-server` | `server/.docker/Dockerfile` ile oluşturulur | 7460 |
| `appstorecat-web` | `web/.docker/Dockerfile` ile oluşturulur | 7461 |
| `appstorecat-scraper-ios` | `scraper-ios/.docker/Dockerfile` ile oluşturulur | 7462 |
| `appstorecat-scraper-android` | `scraper-android/.docker/Dockerfile` ile oluşturulur | 7463 |
| `appstorecat-mysql` | `mysql:8.4` | 7464 |
| `appstorecat-redis` | `redis:7-alpine` | 6379 |

### Geliştirme Ortamını Başlatma

```bash
make setup   # Sadece ilk seferde
make dev     # Tüm servisleri başlat
```

### Volume'lar

- `./server` canlı yeniden yükleme (live reload) için server container'ına bağlanır
- `./web` web container'ına bağlanır (`/app/node_modules` hariç tutulur)
- `./scraper-ios` ve `./scraper-android` canlı yeniden yükleme için bağlanır
- MySQL ve Redis verileri adlandırılmış Docker volume'larında kalıcı olarak saklanır

### Sağlık Kontrolleri

MySQL ve Redis için sağlık kontrolleri yapılandırılmıştır. Backend container'ı, her ikisinin de sağlıklı olmasını bekledikten sonra başlar (`depends_on` ile `service_healthy`).

## Production Ortamı

Production ortamı (`docker-compose.production.yml`) Docker Hub'dan önceden oluşturulmuş image'ları kullanır.

### Geliştirme Ortamından Temel Farklar

| Özellik | Geliştirme | Production |
|---------|------------|------------|
| Image'lar | Yerel olarak oluşturulur | Docker Hub'dan önceden oluşturulmuş (`appstorecat/appstorecat-*`) |
| Redis | Evet (kuyruk, önbellek, hız sınırlama) | Hayır (veritabanı kuyruğu, dosya önbelleği) |
| Portlar | Yayınlanmış (`ports`) | Yalnızca dahili (`expose`) |
| Ağ | Yerel bridge | Harici `dokploy-network` + dahili bridge |
| Yeniden Başlatma | Yok | `unless-stopped` |
| Loglama | Varsayılan | `stderr` sürücüsü |
| Kuyruk sürücüsü | `redis` | `database` |

### Production Ortam Değişkenleri

Standart `.env` değişkenlerine ek olarak, production ortamı şunları gerektirir:

```env
APP_ENV=production
APP_DEBUG=false
APP_VERSION=0.0.3
QUEUE_CONNECTION=database
LOG_CHANNEL=stderr
```

### Production Image'larını Oluşturma

```bash
# Tüm servisler için çoklu platform image'larını oluştur ve gönder
make build-prod

# Tam sürüm: versiyon güncelle, oluştur, gönder, etiketle
make release v=0.0.4
```

Image'lar `linux/amd64` ve `linux/arm64` platformları için oluşturulur.

### Ağlar

Production ortamı iki ağ kullanır:
- **dokploy-network** (harici) — Dokploy reverse proxy tarafından yönetilir
- **appstorecat** (bridge) — Dahili servis iletişimi

## Docker Komutları

```bash
make ps          # Servis durumunu göster
make logs        # Tüm logları takip et
make logs-server   # Sadece server loglarını takip et
make restart     # Tüm servisleri yeniden başlat
make clean       # Durdur + volume'ları kaldır
make nuke        # Image'lar dahil tam temizlik
```
