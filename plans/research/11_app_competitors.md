# `app_competitors` Tablosu Audit

## Genel Bakas

`app_competitors` tablosu, AppStoreCat'in **per-user rakip mapping** mekanizmasinin tek dayanagidir. Tablo, "X kullanicisinin Y app'i icin tanimladigi rakipler" iliskisini tutar; **global / ekip bazinda** degildir. Ayni rakip mapping'ini iki farkli kullanici bagimsiz olarak yaratabilir; aralarinda hicbir baglanti yoktur.

Tablo, su uc kavrami birbirine baglar:

- **Owner**: `users.id` (mapping'i tanimlayan kullanici).
- **Parent app**: `apps.id` (rakip listesinin ait oldugu, kullanicinin track ettigi uygulama).
- **Competitor app**: `apps.id` (kiyaslanacak uygulama; track edilmis olmak zorunda degil; bkz. commit `454e1a5`).

`relationship` alani ile rakibin niteligi (`direct`, `indirect`, `aspiration`) etiketlenir.

Tablo "rakibin track edilmis olmasi" zorunlulugu **yok**. `apps` satirinin var olmasi yeterli; commit `454e1a5` `AppRegistrar::ensureExists()` yardimi ile `user_apps` watchlist'ine dokunmadan satir uretebiliyor.

## Sema

Migration: `/Users/ismail/Projects/opensource/appstorecat/server/database/migrations/2026_04_06_000007_create_app_competitors_table.php`

| Kolon | Tip | Default | Yorum |
|---|---|---|---|
| `id` | bigint unsigned PK | auto | Surrogate primary key. |
| `user_id` | bigint unsigned FK | - | `users.id` -> cascade on delete. Mapping sahibi. |
| `app_id` | bigint unsigned FK | - | `apps.id` -> cascade on delete. Parent app. |
| `competitor_app_id` | bigint unsigned FK | - | `apps.id` -> cascade on delete. Rakip app. |
| `relationship` | string (varchar) | `'direct'` | DB seviyesinde `varchar`; CHECK constraint **yok**. Enum cast model katmaninda. |
| `created_at`, `updated_at` | timestamp | - | Laravel `timestamps()`. |

## Index'ler & Foreign Key'ler

- **Unique**: `(user_id, app_id, competitor_app_id)`. Ayni kullanicinin ayni app icin ayni rakibi iki kere eklemesi DB seviyesinde engellenir. Composite index ayni zamanda `WHERE user_id = ? AND app_id = ?` sorgularina hizmet eder (controller'larin temel access pattern'i).
- **FK'lar** (uceu de `cascadeOnDelete`):
  - `user_id` -> `users.id` — kullanici silinince tum mapping'leri otomatik silinir.
  - `app_id` -> `apps.id` — parent app silinince mapping'ler silinir.
  - `competitor_app_id` -> `apps.id` — rakip app silinince mapping'ler silinir.
- **Eksik index gozlemi**: `competitor_app_id` ve `user_id`-only ayri index yok. `ChangeMonitorController::apps()` icindeki `AppCompetitor::where('user_id', $user->id)->pluck('competitor_app_id')` sorgusu unique composite'in leftmost prefix'i sayesinde calisir (user_id ilk kolon). `SyncTrackedCommand::competitorAppIds()` ise `pluck('competitor_app_id')` ile **full table scan**'e gider (`user_id` filter'i yok, tum kullanicilarin tum rakipleri).

## Model

`/Users/ismail/Projects/opensource/appstorecat/server/app/Models/AppCompetitor.php`

- `protected $table = 'app_competitors';` — Laravel pluralizer'i `app_competitors` uretir ama explicit deklare edilmis.
- `#[Fillable([...])]` PHP attribute ile fillable: `user_id`, `app_id`, `competitor_app_id`, `relationship`.
- **Casts**:
  ```php
  'relationship' => CompetitorRelationship::class,
  ```
  String-backed enum cast. DB'den `'direct'|'indirect'|'aspiration'` string'i `CompetitorRelationship` instance'ina cevrilir; null veya gecersiz deger gelirse Laravel `ValueError` firlatir.
- **Iliskiler**:
  - `app(): BelongsTo<App>` — `app_id` uzerinden parent.
  - `competitorApp(): BelongsTo<App>` — `competitor_app_id` uzerinden rakip (ozel FK).
- **OpenAPI**: `#[OA\Schema(schema: 'AppCompetitor')]` ile shared schema; `CompetitorResource` bunu `allOf` ile genisletir.

Ters yon: `App::competitors(): HasMany<AppCompetitor>` (`App.php:147-150`). **Not**: bu hasMany `user_id` ile scope edilmemis; herhangi bir kullanicinin o app icin tanimladigi tum mapping'leri dondurur. Production access path'leri her zaman `AppCompetitor::where('user_id', ...)` ile baslayip bu rölu kullanmaz.

## Enum: `CompetitorRelationship`

`/Users/ismail/Projects/opensource/appstorecat/server/app/Enums/CompetitorRelationship.php`

```php
enum CompetitorRelationship: string {
    case Direct = 'direct';
    case Indirect = 'indirect';
    case Aspiration = 'aspiration';
    public function label(): string;
}
```

OpenAPI'ye `#[OA\Schema(schema: 'CompetitorRelationship')]` ile expose edilir; `StoreCompetitorRequest`'te `Rule::enum(CompetitorRelationship::class)` ile validate edilir.

## Yazan Yerler

| Konum | Operasyon |
|---|---|
| `CompetitorController::store` | `AppCompetitor::create([...])` — tek insertion noktasi (UI + MCP + REST). |
| `CompetitorController::destroy` | `$competitor->delete()` — tek mapping silme (route model binding). |
| `AppController::untrack` (`AppController.php:305-307`) | `AppCompetitor::where('user_id', $u)->where('app_id', $app)->delete()` — kullanici parent app'i untrack edince **o parent altindaki tum rakip mapping'leri** silinir. **Onemli**: bu `app_id`'ye dayalidir; rakip olarak baska app'lerde gozuken bu app **kalir** (ornek: A app'inden untrack edilse de B app'inin rakibi olarak A varsa, o mapping silinmez). |
| Cascade delete (DB) | Kullanici / parent app / rakip app silinince mapping'ler. |

Migration / seeder / factory **yok** (`database/seeders` icinde competitor yok).

## Okuyan Yerler

| Konum | Amac |
|---|---|
| `AppController::show` (`:123-133`) | Detay sayfasinda parent app track edilmisse rakip listesini `with('competitorApp.publisher', 'competitorApp.category')` ile yukleyip `competitors` relation'ina set eder. |
| `CompetitorController::index` | `GET /apps/{p}/{eid}/competitors` — tek bir app'in rakip listesi. |
| `CompetitorController::all` | `GET /competitors` — tum tracked app'lerin rakipleri, parent'a gore grupli (`CompetitorGroupResource`); `platform` ve `search` filtreleri SQL LIKE + post-fetch filter ile uygulanir. |
| `ChangeMonitorController::apps` (`:45-49`) | "Tracked feed"den competitor app'leri **cikarmak** icin: `tracked = user.apps - user.competitor_app_ids`. Yani ayni anda hem tracked hem competitor olan bir app `changes/apps` feed'inde gosterilmez. |
| `ChangeMonitorController::competitors` (`:84-87`) | Sadece kullanicinin tracked app'lerine bagli rakip app id'lerini toplayip change feed'i bu set uzerinden kurar. |
| `KeywordCompareRequest::rules` | Keyword compare endpoint'i icin allowed `app_ids` listesini parent'in rakipleri ile sinirlar (whitelist validation). |
| `SyncTrackedCommand::competitorAppIds` (`:181`) | Scheduled sync icin tum rakip app'lerin id'lerini toplar (**user-agnostic** — global pool). |

## API Yuzeyi

| Method | Path | Controller | OperationId |
|---|---|---|---|
| GET | `/apps/{platform}/{externalId}/competitors` | `CompetitorController::index` | `listCompetitors` |
| POST | `/apps/{platform}/{externalId}/competitors` | `CompetitorController::store` | `storeCompetitor` |
| DELETE | `/apps/{platform}/{externalId}/competitors/{competitor}` | `CompetitorController::destroy` | `deleteCompetitor` |
| GET | `/competitors` | `CompetitorController::all` | `listAllCompetitors` |
| GET | `/changes/competitors` | `ChangeMonitorController::competitors` | (degisiklik feed'i, secondary reader) |

Route binding: `destroy` route'unda `{competitor}` -> `AppCompetitor` route model binding ile resolve edilir; controller ayrica `user_id` ve `app_id` cross-check'leri yaparak 404 firlatir (IDOR'a karsi savunma).

**MCP karsiliklari** (`mcp/src/tools/competitors.ts`):

| MCP Tool | API Cagrisi |
|---|---|
| `list_app_competitors` | `GET /apps/{p}/{eid}/competitors` |
| `list_all_competitors` | `GET /competitors` |
| `add_competitor` | `POST /apps/{p}/{eid}/competitors` (commit `3d0bc82` ile `competitor_external_id` parametresini one cikariyor) |
| `remove_competitor` | `DELETE /apps/{p}/{eid}/competitors/{competitor}` |

**Web hook'lari** (`web/src/api/endpoints/apps/apps.ts`, Orval generated): `useListCompetitors`, `useListAllCompetitors`, `useStoreCompetitor`, `useDeleteCompetitor`, plus query key helpers. Tuketiciler:

- `web/src/pages/competitors/Index.tsx` — `useListAllCompetitors` ile global gorunum.
- `web/src/components/tabs/CompetitorsTab.tsx` — app detay sayfasinda rakip ekleme/silme; halen `competitor_app_id` (internal id) gonderiyor (web search sonuclarindan `selected.id`), `competitor_external_id` yolunu kullanmiyor.

## Bagimli Tablolar

`app_competitors`'a FK ile bagli **hicbir tablo yoktur**. Tablo terminal: kendisi `users`, `apps`'e referans verir, kimse ona referans vermez. Bu sebeple:

- `app_competitors` cascade delete'in **alici tarafindadir**, hicbir tabloyu silinmeye zorlamaz.
- Mapping'lerin yumusak silinmesi (soft delete) **yok**; delete iz birakmaz.

`apps` silinince hem parent hem competitor yonunden mapping'ler temizlenir. Pratikte `apps` rowlari nadiren silindigi icin (kayit kalici tutuluyor) bu cascade asagi yukari teorik.

## Gozlemler & Kokular

1. **`relationship` DB tipi `varchar`, default `'direct'`, CHECK constraint yok**. Enum sadece application-layer'da (`CompetitorRelationship::class` cast + `Rule::enum` validation) zorunlu kilinmis. Migration'a manuel SQL ile yazilan bir satir veya `DB::table()` ile yapilan insert kolayca gecersiz string birakabilir; sonra model deserialize ederken `ValueError` firlatir. CHECK constraint veya MySQL ENUM tipi gozonune alinabilir.

2. **Per-user model — paylasimli workspace icin kirilgan**. Mevcut sema `user_id` ile kilitlenmis; ekibe/organizasyona competitor mapping paylasimi eklemek istenirse `user_id` -> `workspace_id`/`team_id` migration'i, unique key revizyonu ve tum controller-level `where('user_id', ...)` cagrilarinin (8+ noktada) refactor edilmesi gerekir. MEMORY'de "no bulk/mass scraping" + "organic" prensiplerine sadik ama paylasim icin scope hazirligi yok.

3. **Otomatik track davranisi yok (artik)**. Commit `454e1a5` oncesinde rakip eklemek icin once rakibin track edilmesi gerekiyordu — bu da `user_apps`'e kirletici insert yapiyordu. Yeni davranis: `AppRegistrar::ensureExists()` yalniz `apps` row'unu firstOrCreate eder, `user_apps` pivot'a **dokunmaz**. Yan etki: untrack flow'u (`AppController::untrack`) competitor app'leri **silmez** cunku zaten track edilmemis olabilirler; sadece parent app'in mapping'lerini siler.

4. **`relationship` field'ini guncelleme yolu yok**. Ne PATCH/PUT endpoint var ne controller method. `relationship`'i degistirmek icin kullanici delete + recreate yapmak zorunda — bu da `id` degisikligine ve MCP/web cache invalidation kirilmasina yol acar.

5. **`SyncTrackedCommand::competitorAppIds()` user-agnostic**. Tum kullanicilarin tum rakiplerini global olarak sync siraisina alir. Tek bir kullanicinin "indirect" diye tanimladigi yuksek hacimli rakip listesi tum sistemin scraper bütçesini etkileyebilir. Per-user / per-tier rate limiting modeli yok.

6. **Composite unique tek `relationship` icin yeterli degil**. Unique key `(user_id, app_id, competitor_app_id)` — yani ayni rakip iki farkli `relationship`'le eklenemez. Buradaki semantik karari ("relationship'i unique anahtarin parcasi mi tutmali, yoksa upsert'le mi degistirilmeli") aciktan yazilmamis; commit history'de tartisma izi yok.

7. **`web` katmani halen legacy `competitor_app_id` yolunu kullaniyor**. Backend `competitor_external_id`'yi tercihli yapti, MCP buna gore yenilendi, ancak `CompetitorsTab.tsx:107` hala `competitor_app_id: selected.id` gonderiyor (search sonucundan internal id alindigi icin sorun degil ama yeni patern'le hizali degil).

8. **`changes/apps` feed'inde "tracked - competitor" set difference**. Bir app hem tracked hem competitor ise sadece competitor change feed'inde gosterilir (`ChangeMonitorController::apps:49`). Bu davranis dokuman dısı ve surprising; bir feature flag veya UI hint olmadan kullanici "tracked feed'imde kayip degisikler" diye saskinlik yasayabilir.

9. **`App::competitors()` HasMany'si user'a scope edilmemis**. Eger ilerde bir Eloquent code path bu rölu direkt cagirirsa, baska kullanicilarin mapping'leri sızar. Su an kullanım yok ama trap olarak duruyor.

## Refactor / Iyilestirme Firsatlari

- **`relationship`'i DB seviyesinde kisitla**: ya `ENUM('direct','indirect','aspiration')` ile migrate ya da `CHECK (relationship IN (...))` constraint ekle. App-layer validation'i hala koru.
- **PATCH endpoint**: `PATCH /apps/{p}/{eid}/competitors/{competitor}` -> sadece `relationship` guncelleyen `UpdateCompetitorRequest`. MCP'ye karsilik `update_competitor_relationship` tool'u. Mevcut delete-then-create kullanim akisini bozmadan eklenebilir.
- **Workspace scope'a hazirlik**: `user_id` kolonunu `owner_type/owner_id` morph haline cevirmek yerine, kisa vadede yeni bir nullable `workspace_id` ekleyip `(workspace_id, user_id, app_id, competitor_app_id)` unique'ine gecmek geriye-uyumlu (NULL workspace = personal) bir migration olabilir.
- **`SyncTrackedCommand` icin per-user pool**: `competitorAppIds()` su an global. `AppCompetitor::query()->whereIn('user_id', $activeUserIds)` veya per-user batching ile organic-data prensibine daha sadik kalinabilir.
- **`App::competitors()` rölu kaldir veya `userScoped()` macro'su ekle** — gunluk kullanim icin sadece `AppCompetitor::where('user_id', ...)` patern'ini birakmak code review safety'sini artirir.
- **`untrack` flow icin opsiyonel competitor temizligi**: `untrack` su an parent mapping'lerini siler ama bir baska app'in rakibi olarak yer alan bu app'in mapping'lerini birakir. Bu davranisin `swagger.yaml` veya `mcp/src/tools/apps.ts` aciklamasinda netlestirilmesi gerekiyor.
- **Web tarafi: `useSearchApps` sonucu olmayan free-text rakip ekleme**. `competitor_external_id` API'sini kullanan bir "store URL yapistir" akisi UX'i acabilir; backend zaten destekliyor.

## Ilgili Dosyalar

- `/Users/ismail/Projects/opensource/appstorecat/server/database/migrations/2026_04_06_000007_create_app_competitors_table.php`
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Models/AppCompetitor.php`
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Models/App.php` (HasMany `competitors`)
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Enums/CompetitorRelationship.php`
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Http/Controllers/Api/V1/App/CompetitorController.php`
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Http/Controllers/Api/V1/App/AppController.php` (show, untrack)
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Http/Controllers/Api/V1/ChangeMonitorController.php` (apps, competitors)
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Http/Requests/Api/App/StoreCompetitorRequest.php`
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Http/Requests/Api/App/CompetitorAllRequest.php`
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Http/Requests/Api/App/KeywordCompareRequest.php`
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Http/Resources/Api/App/CompetitorResource.php`
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Http/Resources/Api/App/CompetitorGroupResource.php`
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Services/AppRegistrar.php` (commit `454e1a5` ile `ensureExists()`)
- `/Users/ismail/Projects/opensource/appstorecat/server/app/Console/Commands/Apps/SyncTrackedCommand.php` (competitor sync pool)
- `/Users/ismail/Projects/opensource/appstorecat/server/routes/api.php`
- `/Users/ismail/Projects/opensource/appstorecat/mcp/src/tools/competitors.ts`
- `/Users/ismail/Projects/opensource/appstorecat/web/src/api/endpoints/apps/apps.ts` (Orval hooks)
- `/Users/ismail/Projects/opensource/appstorecat/web/src/pages/competitors/Index.tsx`
- `/Users/ismail/Projects/opensource/appstorecat/web/src/components/tabs/CompetitorsTab.tsx`
