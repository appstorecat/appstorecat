# Kurulum

## Gereksinimler

- **Docker** (v20+) ve **Docker Compose** (v2+)
- **Git**
- 4 GB+ RAM önerilir (MySQL + Redis + 4 servis)

## Depoyu Klonlayın

```bash
git clone https://github.com/appstorecat/appstorecat.git
cd appstorecat
```

## Kurulum

Tek komutla kurulumu çalıştırın:

```bash
make setup
```

Bu komut şunları yapar:

1. Tüm Docker container'larını **derler** (`server`, `web`, `scraper-ios`, `scraper-android`, `mysql`, `redis`)
2. Bağımlılıkları **yükler** (server için Composer, web ve App Store scraper için npm)
3. Laravel `APP_KEY` anahtarını **oluşturur**
4. Veritabanı migration'larını **çalıştırır**

## Servisleri Başlatın

```bash
make dev
```

Tüm servisler arka planda başlayacaktır:

| Servis | URL | Açıklama |
|--------|-----|----------|
| Backend API | http://localhost:7460 | Laravel API gateway |
| Frontend | http://localhost:7461 | React SPA |
| App Store Scraper | http://localhost:7462 | iOS veri kaynağı |
| Google Play Scraper | http://localhost:7463 | Android veri kaynağı |
| MySQL | localhost:7464 | Veritabanı |

## Kurulumu Doğrulayın

Tüm servislerin çalıştığını kontrol edin:

```bash
make ps
```

6 adet sağlıklı container görmelisiniz: `appstorecat-server`, `appstorecat-web`, `appstorecat-scraper-ios`, `appstorecat-scraper-android`, `appstorecat-mysql`, `appstorecat-redis`.

Frontend'e erişmek için http://localhost:7461 adresini ziyaret edin.

## Mağaza Kategorilerini Yükleyin

Kurulumdan sonra mağaza kategorilerini yükleyin (App Store ve Google Play kategorileri):

```bash
make seed
```

## Servisleri Durdurma

```bash
make down
```

## Tek Tek Servis Başlatma

Yalnızca belirli servislere ihtiyacınız varsa:

```bash
make dev-server    # Backend + MySQL + Redis
make dev-web   # Yalnızca Frontend
make dev-ios   # Yalnızca App Store scraper
make dev-android      # Yalnızca Google Play scraper
```

## Sorun Giderme

### Port Çakışmaları

Varsayılan portlar 7460-7464 aralığındadır. Herhangi bir port kullanılıyorsa, proje kök dizinindeki `.env` dosyasını düzenleyerek değiştirebilirsiniz.

### Veritabanı Bağlantı Sorunları

Backend MySQL'e bağlanamıyorsa, sağlık kontrolünün geçmesi için birkaç saniye bekleyin:

```bash
docker compose logs appstorecat-mysql
```

### Temiz Kurulum

Tüm container'ları, volume'ları kaldırıp sıfırdan başlamak için:

```bash
make clean    # Container'ları ve volume'ları kaldır
make setup    # Her şeyi yeniden derle
```

Yerel Docker imajları dahil tam sıfırlama için:

```bash
make nuke     # İmajlar dahil her şeyi kaldır
make setup
```
