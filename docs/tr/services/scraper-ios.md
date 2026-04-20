# App Store Scraper Servisi

Apple App Store'dan uygulama verilerini ceken durumsuz bir Node.js mikroservisidir.

## Teknoloji Yigini

| Bilesen | Teknoloji |
|---------|-----------|
| Framework | Fastify 5 |
| Dil | TypeScript |
| Scraper | app-store-scraper |
| API Dokumantasyonu | @fastify/swagger + Swagger UI |

## Endpoint'ler

| Metot | Rota | Aciklama |
|-------|------|----------|
| GET | `/health` | Saglik kontrolu |
| GET | `/charts` | Grafik siralamalari (en iyi ucretsiz/ucretli/en cok kazanan) |
| GET | `/apps/search` | Terime gore uygulama ara |
| GET | `/apps/:appId/identity` | Uygulama kimligi ve meta verileri |
| GET | `/apps/:appId/listings` | Bir ulke icin magaza listesi |
| GET | `/apps/:appId/listings/locales` | Birden fazla ulke icin listeler |
| GET | `/apps/:appId/metrics` | Puan ve metrikler |
| GET | `/developers/:developerId/apps` | Gelisitiricinin uygulama katalogu |
| GET | `/developers/search` | Gelistirici ara |

## Temel Parametreler

### Grafikler
- `collection` (zorunlu): `top_free`, `top_paid`, `top_grossing`
- `category`: App Store tur ID'si (istege bagli)
- `country`: ISO ulke kodu (varsayilan: `us`)
- `num`: Sonuc sayisi (varsayilan: 200, maksimum: 200)

### Arama
- `term` (zorunlu): Arama sorgusu (minimum 1 karakter)
- `limit`: Maksimum sonuc sayisi (varsayilan: 10, maksimum: 50)
- `country`: ISO ulke kodu (varsayilan: `us`)

### Uygulama Verileri
- `country`: ISO ulke kodu (varsayilan: `us`)
- `lang`: Dil kodu (istege bagli)

## Yanit Formati

### Identity

Identity yaniti ucretli/ucretsiz bilgisini `is_free` boolean'i olarak iletir.

### Listing

- `promotional_text` (iOS'a ozgu) alani yanitin bir parcasidir.

### Hata Yanitlari

Tum endpoint'ler JSON dondurur. Hata yanitlari su formati kullanir:

```json
{
  "error": "Error message",
  "statusCode": 404
}
```

## Hata Semantigi

`sendScraperError()` yardimcisi `app-store-scraper`'dan gelen hatalari (Error instance ya da duz nesne) dogru HTTP durum koduna esler:

- **404 Not Found** — Uygulama hedef storefront'ta mevcut degil. Sunucu tarafi bunu "bu ulkede kalici olarak mevcut degil" olarak yorumlar.
- **5xx** — Beklenmeyen hatalar; sunucu tarafinda yeniden denenir.

Hata durumlari yapilandirilmis JSON log olarak yayinlanir.

## Calistirma

```bash
make dev-ios      # Servisi baslat
make logs-ios     # Loglari goruntule
```

## API Dokumantasyonu

Servis calisirken Swagger UI `/docs` adresinde kullanilabilir.

## Tasarim Ilkeleri

- **Durumsuz:** Veritabani yok, onbellek yok, kalici durum yok
- **Normallestirilmis yanitlar:** Ham App Store verileri tutarli JSON yapilarina normallestir
- **Hata iletimi:** Magaza hatalari (404, hiz siniri) uygun HTTP durum kodlariyla iletilir; eksik uygulama = 404 (500 degil)
- **Port:** `PORT` ortam degiskeni ile yapilandirilabilir (varsayilan: 7462)
