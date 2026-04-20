# Hızlı Başlangıç

Bu rehber, kurulumdan sonra AppStoreCat'i nasıl kullanacağınızı adım adım anlatır.

## 1. Hesap Oluşturun

http://localhost:7461 adresini açın ve yeni bir hesap oluşturun. Bu işlem, kimlik doğrulama için bir Sanctum API token'ı oluşturur.

## 2. Uygulamaları Keşfedin

### Trend Listeleri ile

**Keşif > Trendler** sayfasına gidin ve hem iOS hem de Android için en popüler ücretsiz, en popüler ücretli ve en çok kazandıran uygulamaları görün. Listelerden gelen uygulamalar otomatik olarak keşfedilir ve veritabanına eklenir.

### Arama ile

**Keşif > Uygulamalar** sayfasına gidin ve herhangi bir uygulamayı ada göre arayın. Sonuçlar doğrudan App Store ve Google Play'den gelir. Detaylarını görmek için herhangi bir uygulamaya tıklayın — bu işlem uygulamayı otomatik olarak keşfeder.

> Not: Varsayılan olarak `DISCOVER_{IOS,ANDROID}_ON_DIRECT_VISIT=false`'tur. Yani harici ID ile doğrudan ziyaret edilen, veritabanında henüz olmayan uygulamalar 404 döner. Kaydı yalnızca arama, trend listesi, yayıncı sayfası gibi etkin keşif kaynakları yaratır.

### Yayıncılar ile

**Keşif > Yayıncılar** sayfasına gidin ve bir yayıncı arayın. Uygulamalarını görün ve hepsini tek seferde içe aktarın.

## 3. Bir Uygulamayı Takip Edin

Bir uygulamanın detay sayfasını görüntülerken **Takip Et** butonuna tıklayın. Takip edilen uygulamalar daha sık senkronize edilir (varsayılan olarak her 24 saatte bir) ve uygulama listenizde görünür.

## 4. Uygulama Verilerini Keşfedin

Bir uygulama senkronize edildikten sonra şunları inceleyebilirsiniz:

- **Mağaza Listesi** — Her yerel ayar (`locale`) için başlık, açıklama, ekran görüntüleri, ikon
- **Sürümler** — Yayın tarihleri ve yeniliklerle birlikte sürüm geçmişi
- **Anahtar Kelimeler** — N-gram destekli anahtar kelime yoğunluk analizi
- **Rakipler** — Karşılaştırma için rakip uygulamalar ekleyin
- **Değişiklikler** — Mağaza listesi değişikliklerini zaman içinde takip edin

## 5. Arka Plan Senkronizasyonu

AppStoreCat, kuyruk işçileri kullanarak arka planda verileri otomatik olarak senkronize eder. Senkronizasyon pipeline'ı fazlara ayrılmıştır (`SyncStatus` tablosu üzerinden izlenir):

1. **identity** — temel uygulama kimliği
2. **listings** — her yerel ayar için mağaza listeleri
3. **metrics** — ülke bazlı metrikler (`app_metrics`)
4. **finalize** — uygulamanın genel durumu (`apps.is_available`)
5. **reconciling** — başarısız öğeler `ReconcileFailedItemsJob` ile yeniden denenir

Varsayılan aralıklar:

- **Takip edilen uygulamalar** her 24 saatte bir senkronize edilir (`SYNC_{IOS,ANDROID}_TRACKED_REFRESH_HOURS`)
- **Keşfedilen uygulamalar** her 24 saatte bir senkronize edilir (`SYNC_{IOS,ANDROID}_DISCOVERY_REFRESH_HOURS`)
- **Listeler** günlük olarak senkronize edilir (`CHART_{IOS,ANDROID}_DAILY_SYNC_ENABLED`)

Tüm scraper işleri platform ayrıktır: `sync-discovery-{ios,android}`, `sync-tracked-{ios,android}`, `sync-on-demand-{ios,android}`, `charts-{ios,android}`. iOS ve Android bu sayede birbirini bloklamaz.

Senkronizasyon aktivitesini görmek için `make logs-server` komutunu kontrol edin.

## API Erişimi

Tüm özellikler aynı zamanda `http://localhost:7460/api/v1/` adresindeki REST API üzerinden de kullanılabilir. Tam endpoint referansı için [API dokümantasyonuna](../api/endpoints.md) bakın.

`L5_SWAGGER_GENERATE_ALWAYS=true` olduğunda Swagger UI http://localhost:7460/api/documentation adresinde kullanılabilir.

## Sonraki Adımlar

- [Yapılandırma](./configuration.md) — Senkronizasyon aralıklarını, throttle oranlarını ve keşif kaynaklarını özelleştirin
- [Mimari Genel Bakış](../architecture/overview.md) — Sistemin nasıl çalıştığını anlayın
- [Özellik Dokümantasyonu](../features/) — Her özellik için detaylı rehberler
