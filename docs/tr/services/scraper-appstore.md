# App Store Scraper Servisi

Apple App Store'dan uygulama verilerini ceken durumsuz bir Node.js mikroservisidir.

## Teknoloji Yigini

| Bilesen | Teknoloji |
|---------|-----------|
| Framework | Fastify 5 |
| Dil | TypeScript |
| Scraper | app-store-scraper |
| API Dokumantasyonu | @fastify/swagger + Swagger UI |
| Testler | Vitest |

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
| GET | `/apps/:appId/reviews` | Kullanici yorumlari |
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

Tum endpoint'ler JSON dondurur. Hata yanitlari su formati kullanir:

```json
{
  "error": "Error message",
  "statusCode": 404
}
```

## Calistirma

```bash
make dev-appstore      # Servisi baslat
make logs-appstore     # Loglari goruntule
make test-appstore     # vitest calistir
```

## API Dokumantasyonu

Servis calisirken Swagger UI `/docs` adresinde kullanilabilir.

## Tasarim Ilkeleri

- **Durumsuz:** Veritabani yok, onbellek yok, kalici durum yok
- **Normallestirilmis yanitlar:** Ham App Store verileri tutarli JSON yapilarina normallestir
- **Hata iletimi:** Magaza hatalari (404, hiz siniri) uygun durum kodlariyla iletilir
- **Port:** `PORT` ortam degiskeni ile yapilandirilabilir (varsayilan: 7462)
