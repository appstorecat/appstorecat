# Frontend Servisi

React tek sayfa uygulamasi, AppStoreCat icin kullanici arayuzunu saglar.

## Teknoloji Yigini

| Bilesen | Teknoloji |
|---------|-----------|
| Framework | React 19 |
| Derleme Araci | Vite 8 |
| Dil | TypeScript |
| UI Kutuphanesi | shadcn/ui |
| CSS | Tailwind CSS v4 |
| API Istemcisi | Orval ile olusturulmus |

## Dizin Yapisi

```
web/
├── src/
│   ├── pages/
│   │   ├── auth/            # Giris, Kayit
│   │   ├── apps/            # Uygulama listesi, Uygulama detayi
│   │   ├── changes/         # Uygulama degisiklikleri, Rakip degisiklikleri
│   │   ├── competitors/     # Rakip listesi
│   │   ├── discovery/       # Uygulama arama, Yayinci arama, Trendler
│   │   ├── explorer/        # Ikonlar, Ekran goruntuleri
│   │   ├── publishers/      # Yayinci listesi, Yayinci detayi
│   │   └── Settings.tsx     # Kullanici ayarlari
│   ├── components/          # Paylasilan UI bilesenleri (CountrySelect, SyncingOverlay, ...)
│   └── lib/                 # API istemcisi, yardimci araclar
├── orval.config.ts          # API istemci olusturma yapilandirmasi
└── Dockerfile               # Gelistirme konteyneri
```

## Sayfalar

| Rota | Sayfa | Aciklama |
|------|-------|----------|
| `/login` | Giris | Kullanici kimlik dogrulamasi |
| `/register` | Kayit | Hesap olusturma |
| `/apps` | Uygulama Listesi | Platform filtresine sahip takip edilen uygulamalar |
| `/apps/:platform/:id` | Uygulama Detayi | Sekmeli tam uygulama gorunumu |
| `/discovery/apps` | Uygulama Kesfi | Uygulama arama (`country_code`) |
| `/discovery/publishers` | Yayinci Kesfi | Yayinci arama (`country_code`) |
| `/discovery/trending` | Trend Grafikleri | Trend grafiklerine gozat (`country_code`) |
| `/changes/apps` | Uygulama Degisiklikleri | Takip edilen uygulamalarin magaza listesi degisiklikleri |
| `/changes/competitors` | Rakip Degisiklikleri | Rakip uygulamalardaki degisiklikler |
| `/competitors` | Rakipler | Tum rakip uygulamalar |
| `/explorer/screenshots` | Ekran Goruntuleri | Uygulamalar arasi ekran goruntulerine gozat |
| `/explorer/icons` | Ikonlar | Uygulamalar arasi uygulama ikonlarina gozat |
| `/publishers` | Yayincilar | Yayinci listesi |
| `/publishers/:platform/:id` | Yayinci Detayi | Yayinci bilgisi + uygulama katalogu |
| `/settings` | Ayarlar | Kullanici profili ve guvenlik |

## Uygulama Detay Sekmeleri

Uygulama detay sayfasinda birden fazla sekme bulunur:

- **Listeleme** — Ulke + yerel ayar (`locale`) secicili magaza listesi. Secilen ulke `unavailable_countries` icinde ise `StoreListingTab` icerigi gizler ve muted bir bilgilendirme banner'i gosterir.
- **Surumler** — Surum gecmisi
- **Anahtar Kelimeler** — Anahtar kelime yogunluk analizi
- **Rakipler** — Rakip yonetimi
- **Degisiklikler** — Magaza listesi degisiklik gecmisi

## Ulke ve Yerel Ayar Semantigi

- `CountrySelect` bileseni bir `disabledCodes` prop'u alir; uygulamanin `unavailable_countries` listesindeki ulkeler secilemez olarak isaretlenir ve dahili `zz` sentineli listeden gizlenir.
- Tum arama/discovery ekranlari `country_code` parametresi gonderir.
- Listeleme ve degisiklik bilesenleri yerel ayar icin `selectedLocale` state'ini ve `ChangeCard` bilesenindeki `locale` prop'unu kullanir.

## Senkronizasyon Deneyimi

`SyncingOverlay` 4 adimli pipeline zaman cizelgesini gosterir: **identity → listings → metrics → finalize** durumlari active / done / pending olarak isaretlenir. UI'dan tetiklenen tazeleme `POST /apps/{p}/{id}/sync` uzerinden gider, durum `GET /apps/{p}/{id}/sync-status` ile yoklanir.

## Tip Uretimi

Orval tarafindan uretilen tipler sunucu semasini yansitir; `country_code`, `locale`, `unavailable_countries`, `SyncStatus`, `promotional_text` ve `icon_url` alanlari dahildir.

## Calistirma

```bash
make dev-web    # Frontend'i baslat
make logs-web   # Frontend loglarini goruntule
```

Frontend http://localhost:7461 adresinde kullanilabilir ve API isteklerini server'a yonlendirir.
