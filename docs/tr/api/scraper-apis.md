# Scraper API'leri

Iki scraper mikroservisi, server'in connector'lar araciligiyla tuketigi REST API'leri sunar. Bu API'ler dogrudan dis kullanim icin tasarlanmamistir — server API'si herkese acik arayuzdur.

## App Store Scraper (scraper-ios)

**Base URL:** `http://localhost:7462`
**Framework:** Fastify 5 (Node.js/TypeScript)
**Dokumantasyon:** http://localhost:7462/docs

### Endpoint'ler

| Endpoint | Parametreler | Aciklama |
|----------|--------------|----------|
| `GET /health` | — | `{status: "ok"}` dondurur |
| `GET /charts` | `collection` (zorunlu), `category`, `country`, `num` | Siralama listeleri (maks 200) |
| `GET /apps/search` | `term` (zorunlu), `limit`, `country` | Uygulama ara |
| `GET /apps/:appId/identity` | `country`, `lang` | Uygulama meta verileri (`is_free` boolean'i doner) |
| `GET /apps/:appId/listings` | `country`, `lang` | Magaza listesi (`promotional_text` dahildir) |
| `GET /apps/:appId/listings/locales` | `countries` (virgul ile ayrilmis) | Coklu ulke listeleri |
| `GET /apps/:appId/metrics` | `country`, `lang` | Puan, dosya boyutu |
| `GET /developers/:developerId/apps` | — | Gelistiricinin uygulamalari |
| `GET /developers/search` | `term`, `limit`, `country` | Gelistirici ara |

### Siralama Koleksiyonlari

- `top_free` — En Iyi Ucretsiz Uygulamalar
- `top_paid` — En Iyi Ucretli Uygulamalar
- `top_grossing` — En Cok Hasılat Yapan Uygulamalar

---

## Google Play Scraper (scraper-android)

**Base URL:** `http://localhost:7463`
**Framework:** FastAPI (Python)
**Dokumantasyon:** http://localhost:7463/docs

### Endpoint'ler

| Endpoint | Parametreler | Aciklama |
|----------|--------------|----------|
| `GET /health` | — | `{status: "ok"}` dondurur |
| `GET /charts` | `collection`, `category`, `country`, `count` | Siralama listeleri (maks 200) |
| `GET /apps/search` | `term` (zorunlu), `limit`, `country` | Uygulama ara |
| `GET /apps/{app_id}/identity` | `country` | Uygulama meta verileri |
| `GET /apps/{app_id}/listings` | `locale`, `country` | Magaza listesi (`promotional_text` her zaman `null`) |
| `GET /apps/{app_id}/listings/locales` | `locales` (virgul ile ayrilmis) | Coklu yerel ayar listeleri |
| `GET /apps/{app_id}/metrics` | `country` | Puan (global), yukleme sayisi |
| `GET /developers/{developer_id}/apps` | — | Gelistiricinin uygulamalari |
| `GET /developers/search` | `term`, `limit`, `country` | Gelistirici ara |

### Siralama Varsayilanlari

- Varsayilan koleksiyon: `top_free`
- Varsayilan kategori: `APPLICATION`
- Maksimum sayi: 200 (ancak genellikle 100'e kadar dondurur)

---

## Platform Farkliliklari

| Ozellik | App Store Scraper | Google Play Scraper |
|---------|-------------------|---------------------|
| Dil parametresi | `lang` | `locale` |
| Coklu yerel ayar parametresi | `countries` | `locales` |
| Siralama maks sonuc | 200 | ~100 |
| Altyazi destegi | Evet | Hayir |
| `promotional_text` | Evet | Hayir (`null`) |
| Yukleme sayisi | Hayir | Evet (aralik dizesi) |
| Metriklerde dosya boyutu | Evet | Hayir |
| Puan kapsami | Ulke bazinda | Global (`zz` sentinel'i altinda saklanir) |

## Hata Formati

Her iki scraper da hatalari su sekilde dondurur:

```json
{
  "error": "Error message",
  "statusCode": 404
}
```

Yaygin hatalar:
- `404` — Uygulama bu storefront'ta bulunamadi. Android scraper'i bunu `AppNotFoundError` olarak yayar ve FastAPI istisna isleyicisi 404'e cevirir. iOS scraper'i da 404 doner. Server connector'lari 404'u `empty_response` olarak ele alir — ilgili ulke icin kalici "mevcut degil" olarak isaretlenir ve yeniden denenmez.
- `500` — Upstream scraper kutuphane hatasi
