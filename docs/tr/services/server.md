# Backend Servisi

Laravel API server, AppStoreCat'in merkezi servisidir. API gecidi olarak gorev yapar, veritabanina sahiptir, arka plan gorevlerini yonetir ve scraper mikroservisleriyle tum iletisimi orkestra eder.

## Teknoloji Yigini

| Bilesen | Teknoloji |
|---------|-----------|
| Framework | Laravel 13, PHP 8.4 |
| Veritabani | MySQL 8.4 |
| Kimlik Dogrulama | Laravel Sanctum (token tabanli) |
| API Dokumantasyonu | L5-Swagger (OpenAPI) |
| Kuyruk | Redis (gelistirme) / Database (production) |
| Onbellek | Redis (gelistirme) / File (production) |
| Kod Stili | Laravel Pint |
| Testler | PHPUnit |

## Dizin Yapisi

```
server/
├── app/
│   ├── Connectors/          # Magaza API entegrasyonlari
│   │   ├── ConnectorInterface.php
│   │   ├── ConnectorResult.php
│   │   ├── ITunesLookupConnector.php
│   │   └── GooglePlayConnector.php
│   ├── Enums/               # Platform, DiscoverSource, vb.
│   ├── Http/
│   │   └── Controllers/Api/V1/
│   │       ├── Account/     # Kimlik Dogrulama, Profil, Guvenlik
│   │       └── App/         # Uygulama, Arama, Rakip, Anahtar Kelime, Yorum
│   ├── Jobs/
│   │   ├── Chart/           # Grafik senkronizasyon gorevleri
│   │   └── Sync/            # Uygulama senkronizasyon gorevleri
│   ├── Models/              # Eloquent modelleri (toplam 14)
│   └── Services/            # Is mantigi
│       ├── AppRegistrar.php
│       ├── AppSyncer.php
│       └── KeywordAnalyzer.php
├── config/
│   └── appstorecat.php        # Merkezi yapilandirma
├── database/
│   └── migrations/          # Tum tablo tanimlari
├── resources/
│   └── data/stopwords/      # 50 dilde durak kelime sozlukleri
├── routes/
│   └── api.php              # Tum API rotalari
└── tests/                   # PHPUnit testleri
```

## Temel Sorumluluklar

### API Gecidi
Tum web istekleri server uzerinden gecer. Backend, kullanicilari dogrular (Sanctum), istekleri dogrular (Form Request'ler) ve formatlanmis yanitlar dondurur (API Resource'lari).

### Veritabani Sahibi
Backend, MySQL veritabaninin tek sahibidir. Baska hicbir servis veritabanina dogrudan erismez.

### Gorev Orkestrasyonu
Laravel zamanlayicisi senkronizasyon ve grafik gorevlerini gonderir. Kuyruk iscileri bunlari Redis uzerinden platforma ozel hiz sinirlamasiyla isler.

### Connector Katmani
Connector'lar, scraper mikroservisleriyle HTTP iletisimini soyutlar ve platformlar arasi yanit formatlarini normallestir.

## Calistirma

```bash
make dev-server    # Backend + MySQL + Redis'i baslat
make logs-server   # Backend loglarini goruntule
make pint           # Kod stili duzelticiyi calistir
make test-server   # PHPUnit testlerini calistir
```

## API Dokumantasyonu

`L5_SWAGGER_GENERATE_ALWAYS=true` oldugunda Swagger UI `/api/documentation` adresinde kullanilabilir.

Tam referans icin [API Endpoint'leri](../api/endpoints.md) sayfasina bakin.
