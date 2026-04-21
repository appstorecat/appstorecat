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
   - Production: `APPSTORE_API_URL=http://appstorecat-scraper-ios:7462`
3. Scraper sağlığını test edin: `curl http://localhost:7462/health`

### Kuyruk işleri işlenmiyor

İşçilerin çalışıp çalışmadığını kontrol edin:

```bash
docker compose exec appstorecat-server php artisan queue:work --once
```

İşçileri yeniden başlatın:

```bash
docker compose exec appstorecat-server php artisan queue:restart
```

### İşler failed_jobs tablosuna düşüyor

Başarısız işleri listeleyin:

```bash
docker compose exec appstorecat-server php artisan queue:failed
```

Yaygın nedenler:
- Scraper servisi kapalı → scraper'ı yeniden başlatın
- Hız sınırı aşıldı → işler otomatik olarak yeniden denenir
- Uygulama mağazadan kaldırıldı → scraper HTTP 404 döner, `apps.is_available` (en az bir mağazada erişilebilir) ve ülke bazlı `app_metrics.is_available` bayraklarını kontrol edin
- Kalıcı başarısız öğeler `ReconcileFailedItemsJob` tarafından toplanır ve `sync_statuses` tablosunda `reconciling` fazında görünür

Tüm başarısız işleri yeniden deneyin:

```bash
docker compose exec appstorecat-server php artisan queue:retry all
```

### Migration hataları

Migration'lar başarısız olursa:

```bash
# Mevcut migration durumunu kontrol edin
docker compose exec appstorecat-server php artisan migrate:status

# Bekleyen migration'ları çalıştırın
docker compose exec appstorecat-server php artisan migrate
```

## Frontend Sorunları

### Boş sayfa / API hataları

Server URL yapılandırmasını kontrol edin. Web, API çağrılarını server'a yönlendirir:

```bash
# Frontend loglarını kontrol edin
make logs-web
```

Frontend container ortamında `BACKEND_URL` değerinin doğru ayarlandığından emin olun.

### Canlı yeniden yükleme (hot reload) çalışmıyor

Frontend volume bağlantısı `./web:/app` içermelidir. Vite geliştirme sunucusunun çalıştığını kontrol edin:

```bash
make logs-web
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

- iOS senkronizasyonu: dakikada 5 iş
- Android senkronizasyonu: dakikada 5 iş

IP adresiniz hız sınırlamasına takılmıyorsa bu değerler artırılabilir.

### Veritabanı büyümesi

`app_metrics` ve `trending_chart_entries` tabloları en hızlı büyüyen tablolardır. Şunları değerlendirin:

- Takip senkronizasyon sıklığını ayarlama (`SYNC_{IOS,ANDROID}_TRACKED_REFRESH_HOURS`) veya zamanlayıcının tur başına dağıttığı uygulama sayısını düşürme (`SYNC_{IOS,ANDROID}_TRACKED_BATCH_SIZE`)
- İhtiyacınız olmayan platformda günlük grafik senkronizasyonunu kapatma (`CHART_{IOS,ANDROID}_DAILY_SYNC_ENABLED=false`)
- Aktif ülke listesini `countries.is_active_{ios,android}` üzerinden daraltma

## Sync Pipeline

### Başarısız sync öğeleri biriktikçe ne olur?

Sync pipeline'ı fazlara ayrılmıştır (**identity → listings → metrics → finalize → reconciling**) ve ilerleme `sync_statuses` tablosunda tutulur. Başarısız öğeler otomatik olarak `ReconcileFailedItemsJob` tarafından toplanıp yeniden denenir. Manuel inceleme için:

```bash
make artisan tinker
>>> \App\Models\SyncStatus::where('phase', 'reconciling')->latest()->take(20)->get();
```

### Kuyruklar bloklanıyor

iOS ve Android kuyrukları platform bazında ayrıdır (`sync-tracked-{ios,android}`, `sync-on-demand-{ios,android}`, `charts-{ios,android}`). Biri yavaşsa diğerini bloklamaz. Hangi kuyruğun birikmiş iş barındırdığını görmek için:

```bash
make artisan queue:monitor sync-tracked-ios,sync-tracked-android,sync-on-demand-ios,sync-on-demand-android
```
