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

## Hata Semantigi

Scraper, magaza hatalarini uygun HTTP durum kodlariyla geri iletir:

- **404 Not Found** — Uygulama hedef magaza/ulke icin mevcut degil. `AppNotFoundError` bir FastAPI exception handler'i uzerinden tutarli bir 404 yanitina cevrilir. Sunucu tarafi bunu "bu storefront'ta kalici olarak mevcut degil" olarak yorumlar.
- **5xx** — Beklenmeyen hatalar; sunucu tarafinda yeniden denenir.

Sessiz `print`/uyari hatalari yapilandirilmis JSON log'lari olarak yayinlanir.

## App Store Scraper ile Temel Farklar

| Ozellik | App Store | Google Play |
|---------|-----------|-------------|
| Grafik derinligi | 200'e kadar | 100'e kadar |
| Altyazi | Destekleniyor | Mevcut degil |
| Yukleme sayisi | Mevcut degil | Mevcut (aralik) |
| Yerel ayar parametresi | `lang` | `locale` |
| Varsayilan kategori | Yok | `APPLICATION` |
| Metrik `country_code` | Gercek ISO kodu | `zz` sentineli (global) |

> **Not:** Android metrikleri magaza genelinde toplandigi icin sunucu tarafi bunlari ISO 3166 user-assigned `zz` kodu ile "Global" olarak saklar. Bu kod `countries` tablosuna tohumlanir ama public `/countries` listesinde filtrelenir.

## Calistirma

```bash
make dev-android      # Servisi baslat
make logs-android     # Loglari goruntule
```

## API Dokumantasyonu

Servis calisirken OpenAPI dokumantasyonu `/docs` adresinde kullanilabilir (FastAPI tarafindan otomatik olusturulur).

## Tasarim Ilkeleri

- **Durumsuz:** Veritabani yok, onbellek yok, kalici durum yok
- **Pydantic modelleri:** Tum yanitlar Pydantic semalari araciligiyla dogrulanir
- **Hata iletimi:** Magaza hatalari uygun HTTP durum kodlariyla iletilir (eksik uygulama icin 404)
- **Port:** `PORT` ortam degiskeni ile yapilandirilabilir (varsayilan: 7463)
