# `user_apps` Pivot Audit

## Genel Bakış

`user_apps`, bir kullanıcının "takip ettiği" (tracked) uygulamaları temsil eden saf bir pivot tablodur. Klasik bir watchlist semantiği taşır: `users` ile `apps` arasında çok-çok ilişki. Hiçbir ek davranış (rol, notlar, etiket vb.) tutmaz; sadece "bu kullanıcı bu app'i ne zaman takip etmeye başladı?" sorusunu cevaplar.

Bu pivot, ürünün birçok yerinde "kullanıcının sahibi olduğu kümeyi" filtrelemek için referans veri olarak kullanılır:

- Dashboard, publisher görünümü, değişiklik izleme (Change Monitor), competitor yönetimi ve scheduled sync; hepsi `user_apps` üzerinden "bu kullanıcının ilgisindeki app'ler" kümesini çekiyor.
- `app_competitors` operasyonlarında "parent app gerçekten benim mi?" yetki kontrolü (`isTrackedBy`) doğrudan bu pivot üzerinden yapılıyor.

`AppRegistrar` servisi, "App katalogda var mı?" akışını "Kullanıcının listesine eklendi mi?" akışından net olarak ayırıyor: `ensureExists` pivot'a dokunmaz, `register` ise dokunur.

## Şema

Migration: `/Users/ismail/Projects/opensource/appstorecat/server/database/migrations/2026_04_06_000001b_create_user_apps_table.php`

| Sütun | Tip | Açıklama |
|-------|-----|----------|
| `id` | bigint unsigned, PK | Otomatik artan sentetik PK. |
| `user_id` | bigint unsigned, NOT NULL, FK → `users.id` | Takip eden kullanıcı. `cascadeOnDelete`. |
| `app_id` | bigint unsigned, NOT NULL, FK → `apps.id` | Takip edilen app. `cascadeOnDelete`. |
| `created_at` | timestamp, default `CURRENT_TIMESTAMP` | Takibe başlama zamanı. **`updated_at` yok** — kayıt immutable kabul ediliyor. |

Belirgin not: tablo `$table->id()` ile bir sentetik PK içeriyor; (user_id, app_id) çiftine ek olarak ayrıca bir unique index var (aşağıda).

## Index'ler & FK'lar

Migration'da explicit olarak tanımlanan tek index:

- `unique(['user_id', 'app_id'])` — Aynı kullanıcının aynı app'i iki kez takip etmesini engelliyor. Aynı zamanda kompozit (user_id, app_id) sorguları için seek desteği sağlıyor.

Laravel'in `constrained()->cascadeOnDelete()` çağrıları MySQL düzeyinde örtük olarak `user_id` ve `app_id` üzerinde birer index daha oluşturur (foreign key index'leri). Yani pratikte tabloda:

- `users` silinince → satırlar siliniyor (cascade).
- `apps` silinince → satırlar siliniyor (cascade).

Tek yönlü `app_id` üzerinden ters sorgular (`App::users()`, `whereHas('users')`) bu örtük FK index'i sayesinde performanslı.

## Model

İlişki her iki taraftan da `belongsToMany` olarak tanımlı; **özel bir pivot model yok**, Laravel'in default `Pivot` sınıfı kullanılıyor.

`/Users/ismail/Projects/opensource/appstorecat/server/app/Models/User.php`

```php
public function apps(): BelongsToMany
{
    return $this->belongsToMany(App::class, 'user_apps')->withPivot('created_at');
}
```

`/Users/ismail/Projects/opensource/appstorecat/server/app/Models/App.php`

```php
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'user_apps')->withPivot('created_at');
}

public function isTrackedBy(User $user): bool
{
    return $this->users()->where('user_id', $user->id)->exists();
}
```

İki taraf da `withPivot('created_at')` çağırıyor; ancak Laravel'in `withTimestamps()` özelliği **çağrılmıyor** (zaten tabloda `updated_at` yok). `latest()` çağrıları (aşağıda) bu nedenle pivot'un değil, `apps` tablosunun `created_at` sütununu sıralıyor.

## Yazan Yerler

| Konum | İşlem | Notlar |
|-------|-------|--------|
| `App\Services\AppRegistrar::register` | `attach($app->id)` | "Track" akışının tek doğru yolu. `isTrackedBy` ile idempotent. |
| `App\Http\Controllers\Api\V1\App\AppController::store` | `register()` üzerinden attach | `POST /apps` — kayıt + track tek adımda. |
| `App\Http\Controllers\Api\V1\App\AppController::track` | `attach($app->id)` | `POST /apps/{platform}/{externalId}/track`. `isTrackedBy` guard'ı ile idempotent. |
| `App\Http\Controllers\Api\V1\App\AppController::untrack` | `detach($app->id)` + `AppCompetitor::where(...)->delete()` | `DELETE /apps/{platform}/{externalId}/track`. Pivot satırı silinirken kullanıcının bu app için tüm competitor kayıtları da temizleniyor. |
| Cascade (DB) | `users` veya `apps` satırı silindiğinde | DB tarafından tetiklenir; uygulama event'i yok. |

Önemli: `AppRegistrar::register` `attach` çağrısını koşullu yapıyor (`isTrackedBy` `false` ise), `AppController::track` da aynı kontrolü tekrar yapıyor. Yani çift yazma yok; ama korumalar **kod düzeyinde**, DB'deki `unique(['user_id','app_id'])` constraint'i ikinci bir güvence katmanı.

## Okuyan Yerler

Pivot doğrudan veya `apps()` / `users()` ilişkisi üzerinden okunan başlıca yerler:

| Konum | Sorgu |
|-------|-------|
| `AppController::index` | `$request->user()->apps()->...->latest()->get()` — kullanıcının tracked listesi. |
| `AppController::show` | `$app->isTrackedBy($request->user())` — sahibiyse competitors'ı set ediyor. |
| `AppController::track` | `$app->isTrackedBy($request->user())` — idempotent attach guard. |
| `CompetitorController::index` | `isTrackedBy` (yetki) + parent kontrol. |
| `CompetitorController::all` | `$request->user()->apps()->...->get()` — tüm tracked app'lerle competitors join'i. |
| `CompetitorController::store` | `isTrackedBy` yetki kontrolü. |
| `CompetitorController::destroy` | `isTrackedBy` yetki kontrolü. |
| `ChangeMonitorController` | `$user->apps()->pluck('apps.id')->diff($competitorAppIds)` ve `whereIn('app_id', $user->apps()->pluck('apps.id'))`. |
| `PublisherController` | `$request->user()->apps()->pluck('apps.id')` ve `apps.external_id` — publisher kapsamı kullanıcıyla sınırlanıyor. |
| `DashboardController` | `$user->apps()->pluck('apps.id')` — dashboard agregasyonlarının kapsamı. |
| `SyncTrackedCommand::trackedAppIds` | `App::query()->whereHas('users')->...->pluck('id')` — scheduled sync'in 1. tier'ı. |
| `AppResource::is_tracked` | `$this->resource->isTrackedBy($request->user())` — collection item'larda her satır için bir `exists()` sorgusu. |
| `AppDetailResource::is_tracked` | Aynı. |
| `AppSearchResultResource::is_tracked` | Aynı. |

## API Yüzeyi

Routes: `/Users/ismail/Projects/opensource/appstorecat/server/routes/api.php`

| Method | Path | Handler | Etki |
|--------|------|---------|------|
| `GET` | `/apps` | `AppController@index` | Kullanıcının tracked listesi (platform & search filtreli, `latest()`). |
| `POST` | `/apps` | `AppController@store` | App'i tanı + listeye ekle. |
| `POST` | `/apps/{platform}/{externalId}/track` | `AppController@track` | Pivot'a ekle (idempotent). |
| `DELETE` | `/apps/{platform}/{externalId}/track` | `AppController@untrack` | Pivot'tan sil + ilgili `app_competitors` satırlarını sil. |

Web client (Orval-generated): `/Users/ismail/Projects/opensource/appstorecat/web/src/api/endpoints/apps/apps.ts`

- `listApps` / `useListApps` → `GET /apps`
- `trackApp` / `useTrackApp` → `POST /apps/{platform}/{externalId}/track`
- `untrackApp` / `useUntrackApp` → `DELETE /apps/{platform}/{externalId}/track`

MCP: `/Users/ismail/Projects/opensource/appstorecat/mcp/src/tools/apps.ts`

- `track_app` → `POST /apps/{platform}/{externalId}/track` (annotation: `destructiveHint: false`, `idempotentHint: true`)
- `untrack_app` → `DELETE /apps/{platform}/{externalId}/track` (annotation: `destructiveHint: true`, `idempotentHint: true`)
- `list_tracked_apps` → `GET /apps`

## Gözlemler & Kokular

1. **`latest()` aslında pivot'u sıralamıyor.** `AppController::index` `latest()->get()` diyor; `apps()` ilişkisinde son join `apps` tablosu olduğundan bu, app'in `apps.created_at` alanına göre sıralıyor — yani "kullanıcının takibe alma sırası" değil, "app'in katalogta oluşma sırası". Komite varsayımı muhtemelen "en son eklediğim app üstte" yönündeydi; mevcut davranış bunu sağlamayabilir (aynı app birden çok kullanıcı tarafından farklı zamanlarda eklenmişse). Pivot'taki `created_at`'a göre sıralamak için `orderByPivot('created_at', 'desc')` veya açık `orderBy('user_apps.created_at', 'desc')` gerekir.

2. **Soft-delete yok.** Untrack işlemi kalıcı sil. Geri al/undo ya da "şu tarihte takipten çıktım" bilgisi kaybediliyor. Untrack edildikten sonra tekrar track edilirse `created_at` sıfırlanıyor — "ilk takibe alma" gibi tarihsel bir metrik yok.

3. **Audit yok.** Pivot satırının oluşma/silinme'si için ne bir event firing'i (`pivotAttached`, `pivotDetached` Eloquent event'i tetiklenir ama dinleyen yok), ne de bir audit log var. Sync stratejisi, dashboard agregasyonları ve faturalama vb. ileride bunlara dayanabilir.

4. **`is_tracked` N+1 riski.** `AppResource::is_tracked` her render'da `isTrackedBy()` çağırıyor; bu da `exists()` ile ayrı bir sorgu. `apps()` ilişkisi zaten yüklenmiş olduğundan (`AppController::index` zaten kullanıcının kendi listesini dönüyor), bu çağrı her satırda gereksiz round-trip. Search/listing resource'larda ise gerçek N+1.

5. **`AppController::untrack`'te transaction yok.** `apps()->detach()` ve `AppCompetitor::...->delete()` ayrı statement'lar; arada bir hata olursa kullanıcı tracked değil ama competitor satırları sahipsiz kalabilir. (Cascade FK competitor → app yok; sadece app → competitor ve user → competitor cascadeleri var. Ayrıca `app_competitors` modelinde `app_id` zaten user'a bağlı olduğundan yalnız kalan satırlar erişilemez ama temizlenmemiş kalır.)

6. **`isTrackedBy($user)` boolean'ı her yere yayılmış, policy yok.** `CompetitorController::index/store/destroy` ve `AppController::show`'da `abort_unless($app->isTrackedBy(...), 404)` patterni tekrar ediyor. Bu Laravel Policy/Gate ile soyutlanabilir.

7. **`PublisherController::index`'te `pluck('apps.id')` iki kez (87, 121) ve `pluck('apps.external_id')` (170) ile birlikte aynı request içinde üç ayrı sorgu çıkıyor.** Cache veya tek seferlik yükleme yok.

8. **`SyncTrackedCommand::trackedAppIds`** `whereHas('users')` kullanıyor — yani "en az bir kullanıcı tarafından takip ediliyor" demek, "*bu* kullanıcı tarafından" değil. Bu doğru (tüm tracked app'leri her tick'te tarıyoruz), ama isimlendirme yanıltıcı; pivot'u multi-tenant düşünmüyor.

9. **`updated_at` yokluğu kasıtlı ve uygun**, ancak `withTimestamps()` çağrılmadığı için Laravel hiçbir zaman otomatik `created_at` ataması yapmaz; veriyi DB default'una bırakır. Bu doğru ama `attach($appId, ['created_at' => now()])` denemesi bekleyen kişiler için pürüzlü olabilir; tabloda `useCurrent()` olduğundan pratikte sorun yok.

10. **Pivot model sınıfı yok.** Eğer ileride `note`, `pinned`, `notification_settings` gibi pivot-level alanlar gelirse `Pivot` extend eden bir `UserApp` modeli gerekecek. Şu an `withPivot('created_at')` boyunca sadece array erişimi var.

## Refactor / İyileştirme Fırsatları

1. **`AppController::index` sıralaması açıklığa kavuşturulmalı.** Niyet "takibe alma sırası" ise `->orderByPivot('created_at', 'desc')` (veya `->orderBy('user_apps.created_at', 'desc')`) eklenmeli ve test edilmeli. Niyet "app create tarihi" ise yorum/test ile sabitlenmeli.

2. **`AppController::untrack`'i `DB::transaction` içine almak**, ya da daha temiz: `AppCompetitor` modelinde `user_id` + `app_id` üzerinden bir scope/repository helper'ı + tek transaction.

3. **`UserAppService` (veya `AppTracker`) servisi**. `attach`/`detach` çağrılarını ve cascade temizlemeyi tek bir yerden geçirip event fırlatmak (`AppTracked`, `AppUntracked`) audit, queue triggering ve idempotent davranış için tek noktayı oluşturur. Şu an `AppRegistrar::register` ve `AppController::track` ayrı; ilki ayrıca `ensureExists` da yapıyor.

4. **`is_tracked`'in N+1 maliyetini düşür**: collection bağlamında controller'da bir kez kullanıcının `app_id`'lerini set olarak yükle ve resource'a additional context olarak geçir (`AppResource::collection($apps, ['trackedIds' => $set])`). Tek `App` cevaplarında mevcut hâli kalabilir.

5. **Policy / Authorization soyutlama**: `AppPolicy::view($user, $app) => $app->isTrackedBy($user)`. Controller'larda `$this->authorize('view', $app)` ile tek satıra inebilir.

6. **`Pivot` modeli için typed sınıf** (`App\Models\UserApp extends Pivot`). `$user->apps[0]->pivot->created_at`'i Carbon olarak almak ve gelecekteki kolonları (örn. `pinned_at`, `notifications_enabled`) tip güvenliğiyle eklemek için zemin.

7. **`SyncTrackedCommand::trackedAppIds`'in adı `appsWithAnyTracker` gibi daha açık bir şeyle değiştirilebilir** veya yorum eklenmeli — şu an "tracked" terimi kullanıcı bazlıymış gibi okunuyor ama global.

8. **Audit ihtiyacı varsa**: ya pivot tablosuna `detached_at` ekleyip soft-delete semantiği kurmak, ya da ayrı bir `user_app_events` log tablosu (attach/detach event'leri). Mevcut tabloyu kirletmemek için ikinci seçenek daha temiz.

9. **`updated_at` eksikliğini explicit yapmak için pivot'u model olarak yazmak ve `public $timestamps = false;` veya `const UPDATED_AT = null;` ile niyeti kodda belirtmek**.

10. **`AppController::store` ve `AppController::track` arasındaki örtüşme**: `store` zaten attach yapıyor. Eğer "register without tracking" kullanıcı yüzünde yoksa, `track` endpoint'i `store`'un yerine alternatif olarak görülebilir; ama `store` yeni external_id kabul ediyor, `track` ise mevcut bir app gerektiriyor. Bu fark dokümante edilmeli (Swagger summary'lerinde `summary` farkı yeterli değil).
