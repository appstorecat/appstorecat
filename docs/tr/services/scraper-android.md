# Google Play Scraper Servisi

Google Play Store'dan uygulama verilerini ceken durumsuz bir Python mikroservisidir.

## Teknoloji Yigini

| Bilesen | Teknoloji |
|---------|-----------|
| Framework | FastAPI |
| Dil | Python |
| Scraper | gplay-scraper |
| Sunucu | uvicorn |
| Dogrulama | Pydantic |
| Testler | pytest |

## Endpoint'ler

| Metot | Rota | Aciklama |
|-------|------|----------|
| GET | `/health` | Saglik kontrolu |
| GET | `/charts` | Grafik siralamalari |
| GET | `/apps/search` | Terime gore uygulama ara |
| GET | `/apps/{app_id}/identity` | Uygulama kimligi ve meta verileri |
| GET | `/apps/{app_id}/listings` | Bir yerel ayar icin magaza listesi |
| GET | `/apps/{app_id}/listings/locales` | Birden fazla yerel ayar icin listeler |
| GET | `/apps/{app_id}/metrics` | Puan ve metrikler |
| GET | `/apps/{app_id}/reviews` | Kullanici yorumlari |
| GET | `/developers/{developer_id}/apps` | Gelisitricinin uygulama katalogu |
| GET | `/developers/search` | Gelistirici ara |

## Temel Parametreler

### Grafikler
- `collection`: Grafik turu (varsayilan: `top_free`)
- `category`: Google Play kategorisi (varsayilan: `APPLICATION`)
- `country`: ISO ulke kodu (varsayilan: `us`)
- `count`: Sonuc sayisi (varsayilan: 100, maksimum: 200)

### Arama
- `term` (zorunlu): Arama sorgusu (minimum 1 karakter)
- `limit`: Maksimum sonuc sayisi (varsayilan: 10, maksimum: 50)
- `country`: ISO ulke kodu (varsayilan: `us`)

### Uygulama Verileri
- `country`: ISO ulke kodu (varsayilan: `us`)
- `locale`: Yerel ayar kodu (varsayilan: `en`)

## App Store Scraper ile Temel Farklar

| Ozellik | App Store | Google Play |
|---------|-----------|-------------|
| Grafik derinligi | 200'e kadar | 100'e kadar |
| Yorum kapsami | Ulke bazinda | Global |
| Altyazi | Destekleniyor | Mevcut degil |
| Yukleme sayisi | Mevcut degil | Mevcut (aralik) |
| Yerel ayar parametresi | `lang` | `locale` |
| Varsayilan kategori | Yok | `APPLICATION` |

## Calistirma

```bash
make dev-android      # Servisi baslat
make logs-android     # Loglari goruntule
make test-android     # pytest calistir
```

## API Dokumantasyonu

Servis calisirken OpenAPI dokumantasyonu `/docs` adresinde kullanilabilir (FastAPI tarafindan otomatik olusturulur).

## Tasarim Ilkeleri

- **Durumsuz:** Veritabani yok, onbellek yok, kalici durum yok
- **Pydantic modelleri:** Tum yanitlar Pydantic semalari araciligiyla dogrulanir
- **Hata iletimi:** Magaza hatalari uygun durum kodlariyla iletilir
- **Port:** `PORT` ortam degiskeni ile yapilandirilabilir (varsayilan: 7463)
