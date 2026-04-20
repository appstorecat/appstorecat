# Veri Modeli

## Varlik Iliski Diyagrami

```
User ──M:N──▶ App ──1:N──▶ StoreListing
                │             │
                │             └──▶ StoreListingChange
                │
                ├──1:N──▶ AppVersion ──1:N──▶ AppMetric
                │
                ├──N:1──▶ Publisher
                │
                ├──N:1──▶ StoreCategory
                │
                ├──1:1──▶ SyncStatus
                │
                └──1:N──▶ AppCompetitor ──▶ App (competitor)

ChartSnapshot ──1:N──▶ ChartEntry ──▶ App

Country (referans tablosu — `zz` dahili "Global" sentinel'i API'de filtrelenir)
```

## Ana Tablolar

### apps

Merkezi varlik. Her kayit, belirli bir platformdaki benzersiz bir uygulamayi temsil eder.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `id` | bigint | Birincil anahtar |
| `platform` | tinyint | Int destekli enum: `1` (iOS) veya `2` (Android). Tum JSON yanitlarinda slug (`ios` / `android`) olarak serilestirilir. |
| `external_id` | string | Magaza ID'si (ornegin `com.example.app` veya `123456789`) |
| `publisher_id` | FK | publishers tablosuna baglanti |
| `category_id` | FK | store_categories tablosuna baglanti |
| `display_name` | string | Onbellekte tutulan uygulama adi (varsayilan dilden) |
| `icon_url` | text | Onbellekte tutulan ikon URL'si |
| `origin_country_code` | char(2) | Uygulamanin ilk bulundugu ulke (FK `countries.code`) |
| `supported_locales` | json | Uygulamanin destekledigi dil kodlari dizisi |
| `original_release_date` | date | Ilk yayin tarihi |
| `is_free` | boolean | Ucretsiz veya ucretli |
| `discovered_from` | tinyint | Uygulamanin nasil kesfedildigi (enum: search, trending, publisher, vb.) |
| `discovered_at` | datetime | Ilk kesif zamani |
| `last_synced_at` | datetime | Son tam senkronizasyon zamani |
| `is_available` | boolean | En az bir magazada hala erisilebilir olup olmadigi (ulke bazinda kullanilabilirlik `app_metrics.is_available` uzerinden yonetilir) |

**Benzersizlik kisitlamasi:** `(platform, external_id)`

### app_store_listings

Locale bazinda magaza listesi verileri. Uygulama basina surum basina locale basina bir kayit.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `app_id` | FK | apps tablosuna baglanti |
| `version_id` | FK | app_versions tablosuna baglanti (nullable) |
| `locale` | varchar(10) | BCP-47 locale kodu (ornegin `en-US`, `tr`) |
| `title` | string | Bu dildeki uygulama basligi |
| `subtitle` | string | Uygulama alt basligi (yalnizca iOS) |
| `promotional_text` | text, nullable | iOS tanitim metni (Android'de `NULL`) |
| `description` | text | Tam aciklama |
| `whats_new` | text | Surum notlari |
| `screenshots` | json | Ekran goruntusu URL'leri dizisi |
| `icon_url` | string | Ikon URL'si |
| `video_url` | string | On izleme video URL'si |
| `price` | decimal | Yerel para biriminde fiyat |
| `currency` | string | Para birimi kodu |
| `fetched_at` | datetime | Bu listenin alinma zamani |
| `checksum` | string | Degisiklik tespiti icin hash |

**Benzersizlik kisitlamasi:** `(app_id, version_id, locale)`

> Not: Bu tabloda `is_available` sutunu **yoktur**. Locale bir magazada mevcut degilse, karsi satir yalnizca yazilmaz; ulke bazinda kullanilabilirlik `app_metrics.is_available` icinde tutulur.

### app_versions

Her uygulama icin surum gecmisi.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `app_id` | FK | apps tablosuna baglanti |
| `version` | string | Surum dizesi (ornegin `2.1.0`) |
| `release_date` | date | Yayin tarihi |
| `whats_new` | text | Surum notlari |
| `file_size_bytes` | bigint | Uygulama dosya boyutu |

**Benzersizlik kisitlamasi:** `(app_id, version)`

### app_metrics

Ulke + uygulama basina gunluk metrik goruntusu. Magazalar arasi karsilastirma ve ulke bazinda kullanilabilirlik icin gercek kaynak.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `app_id` | FK | apps tablosuna baglanti |
| `version_id` | FK | app_versions tablosuna baglanti (nullable) |
| `country_code` | char(2) | FK `countries.code`. Android icin rating global oldugundan `zz` "Global" sentinel ulkesi kullanilir |
| `date` | date | Goruntu tarihi |
| `rating` | decimal(3,2) | Ortalama puan (ornegin 4.56) |
| `rating_count` | uint | Toplam puan sayisi |
| `rating_breakdown` | json | Yildiz bazinda sayilar `{1: 100, 2: 50, ...}` |
| `rating_delta` | int | Onceki goruntuden bu yana rating_count degisimi |
| `price` | decimal, nullable | `NULL` = bilinmiyor, `0` = dogrulanmis ucretsiz |
| `installs_range` | string | Yukleme araligi (yalnizca Android, ornegin `10M+`) |
| `file_size_bytes` | bigint | Bu tarihteki dosya boyutu |
| `is_available` | boolean | Uygulamanin bu ulke+tarih icin storefront'ta mevcut olup olmadigi |

**Benzersizlik kisitlamasi:** `(app_id, country_code, date)`

### app_store_listing_changes

Magaza listelerinde tespit edilen degisiklikleri takip eder.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `app_id` | FK | apps tablosuna baglanti |
| `version_id` | FK | app_versions tablosuna baglanti (nullable) |
| `locale` | varchar(10) | BCP-47 locale kodu |
| `field_changed` | string | `title`, `subtitle`, `promotional_text`, `description`, `whats_new`, `screenshots`, `locale_added`, `locale_removed` |
| `old_value` | text | Onceki deger |
| `new_value` | text | Yeni deger |
| `detected_at` | datetime | Degisikligin tespit edildigi zaman |

### sync_statuses

`AppSyncer` pipeline calismalarinin durumunu takip eder. Uygulama basina bir kayit.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `app_id` | FK | apps tablosuna baglanti (benzersiz) |
| `status` | string | `pending`, `running`, `succeeded`, `failed`, `reconciling` |
| `current_step` | string | Son calisan faz (`identity`, `listings`, `metrics`, `finalize`, `reconciling`) |
| `progress_done` | int | Geziyle tamamlanan oge sayisi |
| `progress_total` | int | Planlanmis oge sayisi |
| `failed_items` | json | Yeniden denemeye uygun ogeler (reason etiketi ile) |
| `error_message` | text, nullable | Son hata mesaji |
| `job_id` | string, nullable | Geziyle iliskili Laravel kuyruk job UUID'si |
| `next_retry_at` | datetime, nullable | `ReconcileFailedItemsJob` icin planlanmis zaman |

## Destekleyici Tablolar

### publishers

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `name` | string | Yayinci/gelistirici adi |
| `external_id` | string | Magazaya ozgu gelistirici ID'si |
| `platform` | tinyint | Int destekli enum (`1` iOS / `2` Android), JSON'da slug olarak serilestirilir |
| `url` | string | Yayinci magaza URL'si |

### store_categories

App Store ve Google Play kategori listelerinden beslenir.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `external_id` | string | Magazaya ozgu kategori ID'si (nullable — `NULL`, genel/kategorisiz chart'lar icin kullanilan "All" sentinel kaydini isaret eder) |
| `name` | string | Kategori adi |
| `slug` | string | URL dostu ad |
| `platform` | tinyint | Int destekli enum (`1` iOS / `2` Android), JSON'da slug olarak serilestirilir |
| `type` | string | `app`, `game` veya `magazine` |
| `parent_id` | FK | Alt kategoriler icin kendine referans |
| `priority` | int | Goruntulenme sirasi |

### countries

Platform bazinda dil yapilandirmasi ile desteklenen ulkelerin referans tablosu.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `code` | char(2) | ISO ulke kodu (birincil anahtar). `zz` dahili "Global" sentinel'dir — `/countries` yaniti bunu filtreler |
| `name` | string | Ulke adi |
| `emoji` | string | Bayrak emojisi |
| `is_active_ios` | boolean | iOS islemleri icin aktif |
| `is_active_android` | boolean | Android islemleri icin aktif |
| `ios_languages` | json | Desteklenen iOS dil kodlari |
| `android_languages` | json | Desteklenen Android dil kodlari |

## Chart Tablolari

### trending_charts

Gunluk chart goruntuleri.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `platform` | tinyint | Int destekli enum (`1` iOS / `2` Android), JSON'da slug olarak serilestirilir |
| `collection` | enum | `top_free`, `top_paid`, `top_grossing` |
| `category_id` | FK | Magaza kategorisi (NOT NULL; genel chart'lar platform basina "All" sentinel kaydini gosterir — iOS id=1, Android id=43) |
| `country_code` | char(2) | FK `countries.code` |
| `snapshot_date` | date | Chart tarihi |

**Benzersizlik kisitlamasi:** `(platform, collection, country_code, category_id, snapshot_date)`

### trending_chart_entries

Bir chart icindeki tekil uygulama siralamalari.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `trending_chart_id` | FK | trending_charts tablosuna baglanti |
| `rank` | smallint | Chart'taki sira (1-200) |
| `app_id` | FK | apps tablosuna baglanti |
| `price` | decimal | Goruntusu anindaki uygulama fiyati |
| `currency` | string | Para birimi kodu |

## Pivot Tablolar

### user_apps

Kullanicilar ve takip edilen uygulamalar arasindaki coktan-coga iliski.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `user_id` | FK | users tablosuna baglanti |
| `app_id` | FK | apps tablosuna baglanti |

### app_competitors

Kullanici tarafindan tanimlanan uygulamalar arasi rakip iliskileri.

| Sutun | Tip | Aciklama |
|-------|-----|----------|
| `user_id` | FK | users tablosuna baglanti |
| `app_id` | FK | Ana uygulama |
| `competitor_app_id` | FK | Rakip uygulama |
| `relationship` | string | Iliski turu (varsayilan: `direct`) |
