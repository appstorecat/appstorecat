# API Endpoint'leri

Tum API endpoint'leri `/api/v1` on eki ile baslar ve Sanctum token ile kimlik dogrulama gerektirir (kimlik dogrulama endpoint'leri haric).

**Base URL:** `http://localhost:7460/api/v1`

## Kimlik Dogrulama

### Herkese Acik Endpoint'ler

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| POST | `/auth/register` | Yeni kullanici kaydi |
| POST | `/auth/login` | Giris yap ve token al |

### Korumali Endpoint'ler

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| POST | `/auth/logout` | Cikis yap (token'i iptal et) |
| GET | `/auth/me` | Kimlik dogrulanmis kullaniciyi getir |

## Hesap

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/account/profile` | Kullanici profilini getir |
| PATCH | `/account/profile` | Profili guncelle (isim) |
| DELETE | `/account/profile` | Hesabi sil |
| PUT | `/account/password` | Parolayi guncelle |

## Kontrol Paneli

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/dashboard` | Ozet istatistikler (toplam uygulama, yorum, surum, degisiklik) |

## Uygulamalar

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/apps` | Takip edilen uygulamalari listele (`?platform=ios\|android`) |
| POST | `/apps` | Yeni bir uygulamayi kaydet ve takip et |
| GET | `/apps/search` | Magazalarda uygulama ara (`?term=X&platform=ios&country=us`) |
| GET | `/apps/{platform}/{externalId}` | Uygulama detaylarini getir |
| GET | `/apps/{platform}/{externalId}/listing` | Magaza listesini getir (`?country=us&language=en-US`) |
| GET | `/apps/{platform}/{externalId}/rankings` | Secilen gun icin uygulamanin liste siralamalari (`?date=YYYY-MM-DD`) |
| POST | `/apps/{platform}/{externalId}/track` | Bir uygulamayi takip et |
| DELETE | `/apps/{platform}/{externalId}/track` | Bir uygulamanin takibini birak |

**Rota kisitlamalari:** `platform` degeri `ios` veya `android` olmalidir, `externalId` `[a-zA-Z0-9._]+` ile eslesir

## Rakipler

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/apps/{platform}/{externalId}/competitors` | Bir uygulamanin rakiplerini listele |
| POST | `/apps/{platform}/{externalId}/competitors` | Rakip ekle |
| DELETE | `/apps/{platform}/{externalId}/competitors/{id}` | Rakip kaldir |
| GET | `/competitors` | Tum rakip uygulamalari listele |

## Anahtar Kelimeler

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/apps/{platform}/{externalId}/keywords` | Anahtar kelime yogunlugu (`?language=en-US&ngram=2`) — mevcut liste uzerinden talep uzerine hesaplanir |
| GET | `/apps/{platform}/{externalId}/keywords/compare` | Anahtar kelimeleri karsilastir (`?app_ids=1,2,3&language=en`) |

## Yorumlar

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/apps/{platform}/{externalId}/reviews` | Yorumlari listele (`?country_code=US&rating=5&sort=latest&per_page=25`) |
| GET | `/apps/{platform}/{externalId}/reviews/summary` | Puan ozeti ve dagilimi |

## Degisiklikler

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/changes/apps` | Takip edilen uygulamalarin magaza listesi degisiklikleri (`?field=title`) |
| GET | `/changes/competitors` | Rakip uygulamalarin magaza listesi degisiklikleri |

## Siralamalalar

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/charts` | Siralama listeleri (`?platform=ios&collection=top_free&country=us&category_id=X`) |

## Kesif

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/explorer/screenshots` | Ekran goruntulerine goz at (`?platform=ios&category_id=X&search=term&per_page=12`) |
| GET | `/explorer/icons` | Simgelere goz at (`?platform=android&search=term&per_page=12`) |

## Ulkeler ve Kategoriler

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/countries` | Aktif ulkeleri listele |
| GET | `/store-categories` | Magaza kategorilerini listele (`?platform=ios&type=app`) |

## Yayincilar

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/publishers/search` | Yayinci ara (`?term=X&platform=ios&country=us`) |
| GET | `/publishers` | Takip edilen uygulamalardaki yayincilari listele |
| GET | `/publishers/{platform}/{externalId}` | Yayinci detaylari |
| GET | `/publishers/{platform}/{externalId}/store-apps` | Yayincinin magaza uygulamalari |
| POST | `/publishers/{platform}/{externalId}/import` | Yayincinin tum uygulamalarini ice aktar |

**Yayinci rota kisitlamalari:** `externalId` `[a-zA-Z0-9._%+ -]+` ile eslesir (bosluk ve arti isaretine izin verir)

## Hiz Sinirlamasi

| Kapsam | Sinir |
|--------|-------|
| Kimlik dogrulama endpoint'leri (herkese acik) | Dakikada 5 istek |
| Diger tum endpoint'ler (yerel) | Dakikada 500 istek |
| Diger tum endpoint'ler (production) | Dakikada 60 istek |

## Hata Yanitlari

Tum hatalar asagidaki formati takip eder:

```json
{
  "message": "Error description"
}
```

Yaygin HTTP durum kodlari: `401` (kimlik dogrulanmamis), `403` (yetkisiz), `404` (bulunamadi), `422` (dogrulama hatasi), `429` (hiz siniri asildi).
