# Makefile Komutlari

Tum komutlar proje kok dizininden calistirilir.

## Tum Yigin

| Komut | Aciklama |
|-------|----------|
| `make setup` | Ilk kurulum: derleme, bagimliliklari yukle, anahtar olustur, migrasyon calistir |
| `make dev` | Tum servisleri arka planda baslat |
| `make down` | Tum servisleri durdur |
| `make restart` | Tum servisleri durdur ve yeniden baslat |
| `make build` | Tum Docker konteynerlerini derle/yeniden derle |
| `make ps` | Calisan konteynerlerin durumunu goster |
| `make logs` | Tum servislerin loglarini takip et |

## Bireysel Servisler

| Komut | Aciklama |
|-------|----------|
| `make dev-server` | Backend + MySQL + Redis'i baslat |
| `make dev-web` | Yalnizca web'i baslat |
| `make dev-ios` | Yalnizca App Store scraper'i baslat |
| `make dev-android` | Yalnizca Google Play scraper'i baslat |

## Backend (Laravel)

| Komut | Aciklama |
|-------|----------|
| `make install` | Tum bagimliliklari yukle (Composer + npm) |
| `make key` | Laravel APP_KEY olustur |
| `make migrate` | Veritabani migrasyonlarini calistir |
| `make seed` | Veritabani seeder'larini calistir |
| `make fresh` | Seed ile birlikte temiz migrasyon |
| `make artisan cmd="..."` | Herhangi bir artisan komutu calistir (orn., `make artisan cmd="make:model Foo"`) |
| `make tinker` | Laravel Tinker REPL'i ac |
| `make pint` | PHP kod stili duzelticiyi calistir (Laravel Pint) |

## Testler

| Komut | Aciklama |
|-------|----------|
| `make test` | Tum testleri calistir (server + her iki scraper) |
| `make test-server` | Yalnizca PHPUnit testlerini calistir |
| `make test-ios` | App Store scraper testlerini calistir (vitest) |
| `make test-android` | Google Play scraper testlerini calistir (pytest) |

## Loglar

| Komut | Aciklama |
|-------|----------|
| `make logs` | Tum servis loglarini takip et |
| `make logs-server` | Yalnizca server loglarini takip et |
| `make logs-web` | Yalnizca web loglarini takip et |
| `make logs-ios` | App Store scraper loglarini takip et |
| `make logs-android` | Google Play scraper loglarini takip et |

## Temizlik

| Komut | Aciklama |
|-------|----------|
| `make clean` | Konteynerleri durdur, volume'leri ve artik konteynerleri kaldir |
| `make nuke` | Tam temizlik: konteynerler, volume'ler ve yerel imajlar |

## Production

| Komut | Aciklama |
|-------|----------|
| `make version` | VERSION dosyasindan mevcut surumu goster |
| `make build-prod` | Coklu platform imajlarini derle ve Docker Hub'a gonder |
| `make release v=X.Y.Z` | Tam surum: surum numarasini guncelle, derle, gonder, git etiketi olustur |
