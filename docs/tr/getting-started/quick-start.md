# Hızlı Başlangıç

Bu rehber, kurulumdan sonra AppStoreCat'i nasıl kullanacağınızı adım adım anlatır.

## 1. Hesap Oluşturun

http://localhost:7461 adresini açın ve yeni bir hesap oluşturun. Bu işlem, kimlik doğrulama için bir Sanctum API token'ı oluşturur.

## 2. Uygulamaları Keşfedin

### Trend Listeleri ile

**Keşif > Trendler** sayfasına gidin ve hem iOS hem de Android için en popüler ücretsiz, en popüler ücretli ve en çok kazandıran uygulamaları görün. Listelerden gelen uygulamalar otomatik olarak keşfedilir ve veritabanına eklenir.

### Arama ile

**Keşif > Uygulamalar** sayfasına gidin ve herhangi bir uygulamayı ada göre arayın. Sonuçlar doğrudan App Store ve Google Play'den gelir. Detaylarını görmek için herhangi bir uygulamaya tıklayın — bu işlem uygulamayı otomatik olarak keşfeder.

### Yayıncılar ile

**Keşif > Yayıncılar** sayfasına gidin ve bir yayıncı arayın. Uygulamalarını görün ve hepsini tek seferde içe aktarın.

## 3. Bir Uygulamayı Takip Edin

Bir uygulamanın detay sayfasını görüntülerken **Takip Et** butonuna tıklayın. Takip edilen uygulamalar daha sık senkronize edilir (varsayılan olarak her 24 saatte bir) ve uygulama listenizde görünür.

## 4. Uygulama Verilerini Keşfedin

Bir uygulama senkronize edildikten sonra şunları inceleyebilirsiniz:

- **Mağaza Listesi** — Her dil için başlık, açıklama, ekran görüntüleri, ikon
- **Sürümler** — Yayın tarihleri ve yeniliklerle birlikte sürüm geçmişi
- **Yorumlar** — Puan filtreli kullanıcı yorumları
- **Anahtar Kelimeler** — N-gram destekli anahtar kelime yoğunluk analizi
- **Rakipler** — Karşılaştırma için rakip uygulamalar ekleyin
- **Değişiklikler** — Mağaza listesi değişikliklerini zaman içinde takip edin

## 5. Arka Plan Senkronizasyonu

AppStoreCat, kuyruk işçileri kullanarak arka planda verileri otomatik olarak senkronize eder:

- **Takip edilen uygulamalar** her 24 saatte bir senkronize edilir
- **Keşfedilen uygulamalar** her 72 saatte bir senkronize edilir
- **Listeler** günlük olarak senkronize edilir
- **Yorumlar** her uygulama senkronizasyonuyla birlikte senkronize edilir

Senkronizasyon aktivitesini görmek için `make logs-server` komutunu kontrol edin.

## API Erişimi

Tüm özellikler aynı zamanda `http://localhost:7460/api/v1/` adresindeki REST API üzerinden de kullanılabilir. Tam endpoint referansı için [API dokümantasyonuna](../api/endpoints.md) bakın.

`L5_SWAGGER_GENERATE_ALWAYS=true` olduğunda Swagger UI http://localhost:7460/api/documentation adresinde kullanılabilir.

## Sonraki Adımlar

- [Yapılandırma](./configuration.md) — Senkronizasyon aralıklarını, throttle oranlarını ve keşif kaynaklarını özelleştirin
- [Mimari Genel Bakış](../architecture/overview.md) — Sistemin nasıl çalıştığını anlayın
- [Özellik Dokümantasyonu](../features/) — Her özellik için detaylı rehberler
