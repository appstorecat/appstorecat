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
| GET | `/dashboard` | Ozet istatistikler (toplam uygulama, surum, degisiklik) |

## Uygulamalar

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/apps` | Takip edilen uygulamalari listele (`?platform=ios\|android`) |
| POST | `/apps` | Daha onceden kesfedilmis bir uygulamayi takip et. `platform+external_id` DB'de bulunmalidir (once arama/chart ile kesif); yoksa 422 |
| GET | `/apps/search` | Magazalarda uygulama ara (`?term=X&platform=ios&country_code=us`) |
| GET | `/apps/{platform}/{externalId}` | Uygulama detaylarini getir. Yanit `unavailable_countries: string[]` icerir (`app_metrics.is_available = false` degerlerinden turetilir) |
| GET | `/apps/{platform}/{externalId}/listing` | Magaza listesini getir (`?country_code=us&locale=en-US`). `AppAvailableCountry` kurali uygulama secilen ulkede mevcut degilse 422 doner |
| GET | `/apps/{platform}/{externalId}/rankings` | Secilen gun icin uygulamanin liste siralamalari (`?date=YYYY-MM-DD`) |
| GET | `/apps/{platform}/{externalId}/sync-status` | `sync_statuses` kaydini dondurur (status, current_step, progress, failed_items) |
| POST | `/apps/{platform}/{externalId}/sync` | Platforma ozel on-demand kuyruguna yenileme gonderir |
| POST | `/apps/{platform}/{externalId}/track` | Bir uygulamayi takip et |
| DELETE | `/apps/{platform}/{externalId}/track` | Bir uygulamanin takibini birak |

**Rota kisitlamalari:** `platform` degeri `ios` veya `android` olmalidir, `externalId` `[a-zA-Z0-9._]+` ile eslesir.

> Dogrudan URL ile kesif varsayilan olarak kapalidir (`DISCOVER_{IOS,ANDROID}_ON_DIRECT_VISIT=false`). DB'de bulunmayan bir `platform+external_id` icin `show`/`listing` cagrisi 404 doner — kullanicilar once arama/chart araciligiyla kaydetmelidir.

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
| GET | `/apps/{platform}/{externalId}/keywords` | Anahtar kelime yogunlugu (`?locale=en-US&ngram=2`) — mevcut liste uzerinden talep uzerine hesaplanir |
| GET | `/apps/{platform}/{externalId}/keywords/compare` | Anahtar kelimeleri karsilastir (`?app_ids=1,2,3&locale=en-US`) |

## Degisiklikler

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/changes/apps` | Takip edilen uygulamalarin magaza listesi degisiklikleri. Query: `?field=title`, `?app_id=<int>` (tek uygulamayla sinirla), `?page=<int>`. Yanit sayfalanmistir (`PaginatedChangeResponse`: `data`, `links`, `meta`, `meta_ext.has_scope_apps: boolean`) |
| GET | `/changes/competitors` | Rakip uygulamalarin magaza listesi degisiklikleri. `/changes/apps` ile ayni query param'lari ve sayfalanmis yanit sekli |

## Siralamalalar

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/charts` | Siralama listeleri (`?platform=ios&collection=top_free&country_code=us&category_id=X`) |

## Kesif

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/explorer/screenshots` | Ekran goruntulerine goz at (`?platform=ios&category_id=X&search=term&per_page=12`) |
| GET | `/explorer/icons` | Simgelere goz at (`?platform=android&search=term&per_page=12`) |

## Ulkeler ve Kategoriler

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/countries` | Aktif ulkeleri listele. Dahili `zz` "Global" sentinel'i yanitta filtrelenir |
| GET | `/store-categories` | Magaza kategorilerini listele (`?platform=ios&type=app`) |

## Yayincilar

| Metod | Endpoint | Aciklama |
|-------|----------|----------|
| GET | `/publishers/search` | Yayinci ara (`?term=X&platform=ios&country_code=us`) |
| GET | `/publishers` | Takip edilen uygulamalardaki yayincilari listele |
| GET | `/publishers/{platform}/{externalId}` | Yayinci detaylari. Bilinmeyen yayinci icin 404 |
| GET | `/publishers/{platform}/{externalId}/store-apps` | Yayincinin magaza uygulamalari. Bilinmeyen yayinci icin 404 |
| POST | `/publishers/{platform}/{externalId}/import` | Yayincinin uygulamalarini ice aktar. Her `external_ids[*]` DB'de zaten bulunmalidir; aksi halde 422 |

**Yayinci rota kisitlamalari:** `externalId` `[a-zA-Z0-9._%+ -]+` ile eslesir (bosluk ve arti isaretine izin verir).

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

Yaygin HTTP durum kodlari: `401` (kimlik dogrulanmamis), `403` (yetkisiz), `404` (bulunamadi — ornegin bilinmeyen app/publisher), `422` (dogrulama hatasi — ornegin `AppAvailableCountry` basarisizligi veya henuz kesfedilmemis `platform+external_id`), `429` (hiz siniri asildi).
