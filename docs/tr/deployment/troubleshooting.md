# Sorun Giderme

AppStoreCat çalıştırılırken karşılaşılan yaygın sorunlar ve çözümleri.

## Docker Sorunları

### Container'lar başlamıyor

Portların zaten kullanımda olup olmadığını kontrol edin:

```bash
lsof -i :7460   # Backend portunu kontrol et
lsof -i :7461   # Frontend portunu kontrol et
```

Çözüm: Kök dizindeki `.env` dosyasında portları değiştirin veya çakışan işlemi durdurun.

### MySQL sağlık kontrolü başarısız oluyor

MySQL'in başlatılması birkaç saniye sürer. Logları kontrol edin:

```bash
docker compose logs appstorecat-mysql
```

Sorun devam ederse, volume'un bozulmadığından emin olun:

```bash
make clean   # Volume'ları kaldır
make setup   # Yeniden oluştur
```

### Redis bağlantı hatası

Backend, Redis'in erişilebilir olmasını bekler. Geliştirme ortamında kontrol edin:

```bash
docker compose ps appstorecat-redis
```

Production ortamında Redis kullanılmaz — `QUEUE_CONNECTION=database` ve `CACHE_STORE=file` ayarlarının yapıldığından emin olun.

## Backend Sorunları

### Scraper bağlantı hataları

Backend scraper'lara ulaşamıyorsa:

1. Scraper'ların çalıştığını kontrol edin: `make ps`
2. `.env` dosyasındaki URL'leri doğrulayın:
   - Geliştirme: `APPSTORE_API_URL=http://host.docker.internal:7462`
   - Production: `APPSTORE_API_URL=http://appstorecat-scraper-appstore:7462`
3. Scraper sağlığını test edin: `curl http://localhost:7462/health`

### Kuyruk işleri işlenmiyor

İşçilerin çalışıp çalışmadığını kontrol edin:

```bash
docker compose exec appstorecat-backend php artisan queue:work --once
```

İşçileri yeniden başlatın:

```bash
docker compose exec appstorecat-backend php artisan queue:restart
```

### İşler failed_jobs tablosuna düşüyor

Başarısız işleri listeleyin:

```bash
docker compose exec appstorecat-backend php artisan queue:failed
```

Yaygın nedenler:
- Scraper servisi kapalı → scraper'ı yeniden başlatın
- Hız sınırı aşıldı → işler otomatik olarak yeniden denenir
- Uygulama mağazadan kaldırıldı → beklenen 404, `is_available` bayrağını kontrol edin

Tüm başarısız işleri yeniden deneyin:

```bash
docker compose exec appstorecat-backend php artisan queue:retry all
```

### Migration hataları

Migration'lar başarısız olursa:

```bash
# Mevcut migration durumunu kontrol edin
docker compose exec appstorecat-backend php artisan migrate:status

# Bekleyen migration'ları çalıştırın
docker compose exec appstorecat-backend php artisan migrate
```

## Frontend Sorunları

### Boş sayfa / API hataları

Backend URL yapılandırmasını kontrol edin. Frontend, API çağrılarını backend'e yönlendirir:

```bash
# Frontend loglarını kontrol edin
make logs-frontend
```

Frontend container ortamında `BACKEND_URL` değerinin doğru ayarlandığından emin olun.

### Canlı yeniden yükleme (hot reload) çalışmıyor

Frontend volume bağlantısı `./frontend:/app` içermelidir. Vite geliştirme sunucusunun çalıştığını kontrol edin:

```bash
make logs-frontend
```

## Scraper Sorunları

### App Store scraper'ı boş sonuç döndürüyor

Bazı kategori/ülke kombinasyonları App Store tarafından desteklenmez. Bu beklenen bir durumdur ve uyarı olarak loglanır.

### Google Play scraper zaman aşımı

Google Play veri çekme işlemi App Store'dan daha yavaş olabilir. Zaman aşımı süresini artırın:

```env
GPLAY_TIMEOUT=60
```

## Performans

### Yavaş senkronizasyon işleri

`config/appstorecat.php` dosyasındaki hız sınırlama oranlarını kontrol edin. Varsayılan oranlar ihtiyatlıdır:

- iOS senkronizasyonu: dakikada 3 iş
- Android senkronizasyonu: dakikada 2 iş

IP adresiniz hız sınırlamasına takılmıyorsa bu değerler artırılabilir.

### Veritabanı büyümesi

`app_keyword_densities` ve `app_reviews` tabloları en hızlı büyüyen tablolardır. Şunları değerlendirin:

- İhtiyacınız olmayan platformlar için yorum senkronizasyonunu devre dışı bırakma
- Keşif senkronizasyon sıklığını ayarlama (`SYNC_{PLATFORM}_DISCOVERY_REFRESH_HOURS`)
