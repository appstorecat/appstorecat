# Mimari Genel Bakis

AppStoreCat, HTTP uzerinden iletisim kuran 4 servisten olusan bir monorepo'dur:

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────────┐
│  Frontend   │────▶│  Backend (API)   │────▶│  scraper-ios   │
│  React SPA  │     │  Laravel 13      │     │  Fastify + Node.js  │
│  :7461      │     │  :7460           │     │  :7462              │
└─────────────┘     └──────┬───────────┘     └─────────────────────┘
                           │           │
                           │           │     ┌─────────────────────┐
                           │           └────▶│  scraper-android      │
                           │                 │  FastAPI + Python   │
                    ┌──────▼───────┐         │  :7463              │
                    │    MySQL     │         └─────────────────────┘
                    │    :7464     │
                    └──────────────┘
```

## Tasarim Ilkeleri

### Gateway Olarak Backend

Backend, tum veri islemleri icin tek giris noktasidir. Frontend hicbir zaman dogrudan scraper'larla iletisim kurmaz. Bu yaklasim sunlari saglar:

- Merkezi kimlik dogrulama ve hiz sinirlandirma
- Farkli magaza formatlarinda veri normalizasyonu
- Frontend'den bagimsiz arka plan senkronizasyonu

### Durumsuz Scraper'lar

Scraper mikroservislerin veritabani, onbellek veya durumu yoktur. Bir istek alir, magazayi tarar, yaniti normalize eder ve geri dondurur. Bu sayede basit, bagimsiz dagitilabilir ve kolay degistirilebilir olurlar.

### Platform Ayirimi

iOS ve Android bagimsiz pipeline'lar olarak ele alinir. Her birinin ayri olarak sunlari vardir:

- Kuyruk isleyicileri (`sync-tracked-ios`, `sync-tracked-android`, vb.)
- Throttle oranlari (App Store ve Google Play'in farkli hiz sinirlari vardir)
- Yapilandirma (senkronizasyon araliklari, kesif kaynaklari, chart ayarlari)
- Connector'lar (`ITunesLookupConnector`, `GooglePlayConnector`)

Bu yaklasim, bir platformun hiz sinirlari veya hatalarinin digerini asla engellemeyecegini garanti eder.

## Servisler

| Servis | Teknoloji | Rol |
|--------|-----------|-----|
| **server** | Laravel 13, PHP 8.4 | API gateway, is mantigi, veritabani sahibi, kuyruk isleyicileri |
| **web** | React 19, Vite, TypeScript | Kullanici arayuzu |
| **scraper-ios** | Fastify 5, Node.js | App Store veri tarama |
| **scraper-android** | FastAPI, Python | Google Play veri tarama |
| **mysql** | MySQL 8.4 | Kalici depolama |
| **redis** | Redis 7 | Onbellek, kuyruk aracisi, throttling (yalnizca gelistirme) |

## Veri Akisi

### Kullanici Tarafindan Baslatilan (Senkron)

1. Kullanici web'de bir uygulama arar
2. Frontend `GET /api/v1/apps/search?term=...&platform=ios&country_code=us` adresini cagrir
3. Backend istegi `ITunesLookupConnector` araciligiyla `scraper-ios`'a iletir
4. Scraper sonuclari dondurur, server normalize eder ve web'e iletir
5. Kullanici bir uygulamaya tiklar → server aramada/chart'ta zaten kesfedilmis olmasini dogrular (bilinmeyen uygulamalar icin 404/422). Dogrudan URL ile kesif varsayilan olarak kapalidir (`DISCOVER_{IOS,ANDROID}_ON_DIRECT_VISIT=false`).

### Arka Plan (Asenkron)

1. Laravel zamanlayici senkronizasyon job'larini gonderir (ornegin `SyncAppJob`, `SyncChartSnapshotJob`, `ReconcileFailedItemsJob`)
2. Job'lar platforma ozel kuyruklara yerlestirilir (`sync-tracked-ios`, `sync-on-demand-android`, `charts-android`, vb.)
3. Kuyruk isleyicileri job'lari alir, Redis throttle uygular, connector'lari cagrir
4. Connector'lar scraper mikroservislerini cagrir; scraper 404 dondurdugunde pipeline sonucu `empty_response` olarak isaretler (sonsuz yeniden deneme yok)
5. Sonuclar normalize edilir ve veritabanina kaydedilir; `sync_statuses` her asamanin ilerlemesini ve basarisiz ogelerini takip eder

## Altyapi

### Gelistirme

Tum servisler `docker-compose.yml` ile Docker uzerinde calisir. Redis kuyruklari, onbellegi ve throttling'i yonetir.

### Uretim

Onceden olusturulmus Docker Hub imajlariyla `docker-compose.production.yml` kullanir. Temel farklar:

- **Redis yok** — kuyruklar `database` surucusunu kullanir
- **Harici ag** — servisler bir ters proxy (Dokploy) arkasindadir
- Servisler `ports` yerine `expose` kullanir (proxy yonlendirmeyi yonetir)
- `unless-stopped` yeniden baslatma politikasi

Uretim kurulumu detaylari icin [Dagitim](../deployment/docker.md) sayfasina bakin.
