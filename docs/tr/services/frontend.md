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
frontend/
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
│   ├── components/          # Paylasilan UI bilesenleri
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
| `/discovery/apps` | Uygulama Kesfi | Uygulama arama |
| `/discovery/publishers` | Yayinci Kesfi | Yayinci arama |
| `/discovery/trending` | Trend Grafikleri | Trend grafiklerine gozat |
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

- **Listeleme** — Dil secicili magaza listesi
- **Surumler** — Surum gecmisi
- **Yorumlar** — Filtreli kullanici yorumlari
- **Anahtar Kelimeler** — Anahtar kelime yogunluk analizi
- **Rakipler** — Rakip yonetimi
- **Degisiklikler** — Magaza listesi degisiklik gecmisi

## Calistirma

```bash
make dev-frontend    # Frontend'i baslat
make logs-frontend   # Frontend loglarini goruntule
```

Frontend http://localhost:7461 adresinde kullanilabilir ve API isteklerini backend'e yonlendirir.
