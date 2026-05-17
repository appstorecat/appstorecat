# `personal_access_tokens` Tablosu — Audit

## Genel Bakış

`personal_access_tokens` tablosu, **Laravel Sanctum**'un standart "personal access token" tablosudur. AppStoreCat'te iki farklı bearer token akışı için tek depo olarak kullanılır:

1. **Web SPA oturum token'ı** — `POST /v1/auth/register` ve `POST /v1/auth/login` her seferinde `auth-token` adında bir token üretir. SPA bu token'ı localStorage'da tutup `Authorization: Bearer …` header'ı ile gönderir. (`config/sanctum.php` `stateful` ayarı tanımlı olsa da kullanılan akış cookie-based SPA değil, Bearer akışıdır.)
2. **MCP / harici API token'ları** — `POST /v1/account/api-tokens` ile kullanıcı tarafından adlandırılmış, `mcp` ability'sine sahip token üretilir; `mcp/` paketi `APPSTORECAT_API_TOKEN` env değişkeni üzerinden bunu kullanır.

Sanctum migration'ı stock haldedir, hiçbir özelleştirme yoktur. Custom `PersonalAccessToken` model override'ı yok — Sanctum'un kendi `Laravel\Sanctum\PersonalAccessToken` modeli kullanılır.

## Şema

Migration: `server/database/migrations/2026_04_06_134342_create_personal_access_tokens_table.php:14-28`

| Sütun | Tip | Null | Default | Açıklama |
|---|---|---|---|---|
| `id` | `bigint unsigned` (PK, AI) | Hayır | — | Birincil anahtar. API'de token revoke ederken bu id kullanılır (`DELETE /account/api-tokens/{tokenId}`). |
| `tokenable_type` | `varchar(255)` | Hayır | — | Polymorphic owner sınıfı. AppStoreCat'te pratikte sadece `App\Models\User`. |
| `tokenable_id` | `bigint unsigned` | Hayır | — | Polymorphic owner id'si. `users.id`'ye morph ile bağlı; FK constraint yok. |
| `name` | `text` | Hayır | — | Kullanıcı tarafından verilen etiket. `auth-token` (SPA) veya UI'dan girilen serbest metin. Comment: "Human-friendly label set by the user when creating the token." |
| `token` | `varchar(64)` | Hayır | — | Plaintext token'ın SHA-256 hash'i. Unique. Plaintext sadece oluşturma anında bir kere döner. |
| `abilities` | `text` (JSON) | Evet | `null` | Granted Sanctum scopes. SPA token'ında `null` (yani `["*"]` — unrestricted). MCP token'ında `["mcp"]`. |
| `last_used_at` | `timestamp` | Evet | `null` | Token ile yapılan son authenticated request zamanı. Sanctum guard tarafından otomatik güncellenir. |
| `expires_at` | `timestamp` | Evet | `null` | Opsiyonel expiry. Şu an her zaman `null` — kod hiç set etmiyor (`config/sanctum.php` `expiration` da `null`). |
| `created_at` | `timestamp` | Evet | `null` | — |
| `updated_at` | `timestamp` | Evet | `null` | — |

## Index'ler & FK'lar

- **PK**: `id`
- **UNIQUE**: `personal_access_tokens_token_unique` → `token`
- **Composite index**: `personal_access_tokens_tokenable_type_tokenable_id_index` → `(tokenable_type, tokenable_id)` — `morphs()` helper'ı tarafından otomatik eklenir; owner'a göre token listeleme için kullanılır.
- **Index**: `personal_access_tokens_expires_at_index` → `expires_at` — cleanup amaçlı (migration comment'inde "Indexed for cleanup" denmiş ama tablo şu an çöp toplamıyor).
- **Outgoing FK**: yok. Sanctum migration'ı bilinçli olarak FK constraint koymaz (morph relation olduğu için).
- **Incoming FK**: yok.

## Model

Custom model yok. `User` modelinde Sanctum'un default modeli kullanılır:

- `server/app/Models/User.php:13` — `use Laravel\Sanctum\HasApiTokens;`
- `server/app/Models/User.php:33` — `use HasApiTokens, HasFactory, Notifiable;`

`HasApiTokens` trait şunları sağlar:
- `tokens(): MorphMany` — kullanıcının tüm token'ları.
- `createToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken` — plaintext + DB row üretir.
- `currentAccessToken(): PersonalAccessToken|TransientToken` — istek üzerindeki aktif token.
- `tokenCan(string $ability): bool` — ability kontrolü.

`bootstrap/app.php` ve `AppServiceProvider`'da `Sanctum::usePersonalAccessTokenModel(...)` çağrısı yok → Sanctum'un kendi `Laravel\Sanctum\PersonalAccessToken` modeli kullanılır.

## Yazan Yerler

| Konum | Ne yazıyor | Notlar |
|---|---|---|
| `server/app/Http/Controllers/Api/V1/Account/AuthController.php:44` (register) | `$user->createToken('auth-token')->plainTextToken` | İlk kayıtta SPA için token üretir; abilities = `['*']` (Sanctum default). |
| `server/app/Http/Controllers/Api/V1/Account/AuthController.php:75-77` (login) | Önce `tokens()->where('name', 'auth-token')->delete()` ile eski SPA token'larını temizler, sonra yenisini yaratır. | Single-session SPA policy — her login yeni token üretir, eskileri silinir. **Multi-device login yapılırsa diğer cihazlar logout olur.** |
| `server/app/Http/Controllers/Api/V1/Account/AuthController.php:94` (logout) | `$request->user()->currentAccessToken()->delete()` | Sadece o anki request'in token'ını siler. |
| `server/app/Http/Controllers/Api/V1/Account/ApiTokenController.php:65` (store) | `$request->user()->createToken($request->name, ['mcp'])` | Named token, `mcp` ability'si ile. Plaintext **sadece bu response'da** döner (`ApiTokenCreatedResource`). |
| `server/app/Http/Controllers/Api/V1/Account/ApiTokenController.php:88-93` (destroy) | `tokens()->where('id', $tokenId)->where('name', '!=', 'auth-token')->firstOrFail()->delete()` | UI üzerinden revoke. `auth-token` adlı SPA token'ı kasıtlı olarak listeden ve silme akışından gizlenir. |
| Sanctum guard (vendor) | `last_used_at` otomatik update | Her authenticated request'te. |

## Okuyan Yerler

| Konum | Nasıl okuyor | Amaç |
|---|---|---|
| Sanctum `Guard` (vendor: `laravel/sanctum/src/Guard.php`) | Bearer header'dan token alıp `token` kolonu üzerinden lookup. | `auth:sanctum` middleware'inin temeli. |
| `server/routes/api.php:16` | `Route::middleware(['auth:sanctum', "throttle:{$rateLimit}"])` | Tüm korumalı API endpoint'leri. |
| `server/app/Http/Controllers/Api/V1/Account/ApiTokenController.php:35-39` (index) | `$request->user()->tokens()->where('name', '!=', 'auth-token')->orderByDesc('created_at')->get()` | Kullanıcının kendi token listesi. SPA token'ı maskelenir. |
| `server/app/Http/Controllers/Api/V1/Account/AuthController.php:94` | `currentAccessToken()` | Logout'ta. |
| `server/app/Http/Resources/Api/Account/ApiTokenResource.php:25-31` | `id`, `name`, `abilities`, `last_used_at`, `created_at` | UI listesi (web settings ApiTokens). `token` (hash) hiç döndürülmez. |
| `server/app/Http/Resources/Api/Account/ApiTokenCreatedResource.php:32-37` | `NewAccessToken::$plainTextToken` | One-time secret. |

`tokenCan('mcp')` veya `CheckAbilities` middleware'inin kullanıldığı bir yer **yok** — `mcp` ability tablo'ya yazılıyor ama hiçbir endpoint bunu enforce etmiyor (aşağıdaki "Kokular" bölümüne bkz.).

## API Yüzeyi

Tümü `/api/v1` prefix'i altında.

| Method | Path | Controller | Ne yapıyor |
|---|---|---|---|
| `POST` | `/auth/register` | `AuthController::register` | User + `auth-token` üretir. |
| `POST` | `/auth/login` | `AuthController::login` | Eski `auth-token`'ları siler, yenisini üretir. |
| `POST` | `/auth/logout` | `AuthController::logout` | `currentAccessToken()->delete()`. Auth gerekli. |
| `GET` | `/account/api-tokens` | `ApiTokenController::index` | `auth-token` hariç user token'ları. |
| `POST` | `/account/api-tokens` | `ApiTokenController::store` | `mcp` ability'li named token. Plaintext bir defa. |
| `DELETE` | `/account/api-tokens/{tokenId}` | `ApiTokenController::destroy` | Sadece `auth-token` olmayanlar silinebilir; başkasının token'ı `firstOrFail()` ile 404. |

Auth route'ları 5/dakika throttle altında; korumalı route'lar 60/dakika (prod) veya 500/dakika (local) throttle.

## Web Tarafı

- Endpoint hook'ları (Orval generated): `web/src/api/endpoints/account/account.ts` — `useListApiTokens`, `useCreateApiToken`, `useRevokeApiToken`, `getListApiTokensQueryKey`.
- TS model'leri: `web/src/api/models/apiTokenResource.ts`, `apiTokenCreatedResource.ts`, `storeApiTokenRequest.ts`, `createApiToken201.ts`.
- UI: `web/src/pages/settings/ApiTokens.tsx` — listeleme, create dialog (plaintext'i bir kez gösterip clipboard'a kopyalama), revoke için confirm dialog.
- MCP setup ekranı: `web/src/pages/settings/Mcp.tsx:40-65` — kullanıcının token'ını `APPSTORECAT_API_TOKEN` olarak Docker / Claude Desktop config snippet'ine basar. Burada token plaintext olarak **ekranda durur** (URL parametresi veya geçici state ile), kalıcı saklanmıyor.

## MCP Paketi

- `mcp/src/client.ts:1-7` — `APPSTORECAT_API_TOKEN` env var olarak okunur; yoksa process exit. Plaintext token uzun ömürlü olarak kullanıcı tarafında (`mcp.json`, Docker `-e`, vs.) yaşar.
- `mcp/src/client.ts:90-97` — Her istek `Authorization: Bearer ${API_TOKEN}` header'ı ile gider.
- MCP paketinin kendisi token üretmez/saklamaz — sadece okuyup gönderir.

## Bağımlı Tablolar

- **`users`** — `tokenable_type` = `App\Models\User`, `tokenable_id` = `users.id`. Morph; FK constraint **yok** → kullanıcı silindiğinde token'lar otomatik silinmez. (`02_users.md` audit'inde de işaret edilmiş: "personal_access_tokens.tokenable_id … cascade YOK — manuel silinmesi gerekiyor.")
- Başka tablo bağımlılığı yok.

## Gözlemler & Kokular

1. **`expires_at` hiç kullanılmıyor.** `createToken()` çağrılarının ikisi de (`AuthController` ve `ApiTokenController`) üçüncü argümanı (`$expiresAt`) geçmiyor. `config/sanctum.php` `'expiration' => null`. Sonuç: hem SPA hem MCP token'ları **kalıcı**. Sızdırılan bir token revoke edilmedikçe sonsuza dek geçerli.
2. **`mcp` ability tanımlı ama enforce edilmiyor.** `ApiTokenController::store` token'a `['mcp']` veriyor, fakat hiçbir route veya middleware `tokenCan('mcp')` / `abilities:mcp` kontrolü yapmıyor. Yani MCP token'ı tüm korumalı endpoint'lere SPA token'ı kadar erişebiliyor. Scope sadece kozmetik.
3. **SPA token'ı listede maskeleniyor, ama oluşumu yine de aynı tabloda.** `name = 'auth-token'` filter'ı `index` ve `destroy`'da var, ama `auth-token` yine de aynı `personal_access_tokens` satırı. Eğer kullanıcı `POST /account/api-tokens` çağrısında `name = "auth-token"` gönderirse (validation `max:255` dışında kısıt yok), bir sonraki login'de kendi MCP token'ı silinir. **Validation eksik.**
4. **Multi-device SPA logout side-effect.** `login` her seferinde tüm `auth-token`'ları siler. Aynı kullanıcı iki tarayıcıda açık ise birinde login yapmak diğerini logout eder. Bilinçli bir tasarım kararı mı belgelenmemiş.
5. **Cascade delete yok.** Bir user silindiğinde token'ları orphan kalır (`tokenable_id` artık olmayan user'ı işaret eder). `ProfileController::destroy`'un manuel token cleanup yapıp yapmadığı bu audit kapsamı dışında ama tablo seviyesinde bir güvence yok.
6. **`last_used_at` tabanlı revoke politikası yok.** Stale token cleanup için bir scheduled job veya artisan komutu yok. Aktif olmayan token'lar tabloda birikir.
7. **`token` kolonu `varchar(64)` (SHA-256 hex = 64 char).** Migration `unique` index'i hex string üzerinde — bu Sanctum'un default davranışı, sorun değil ama InnoDB key size (utf8mb4 ile 256 byte) sınırına yakın; başka utf8mb4 string unique index eklenirken dikkat.
8. **`name` kolonu `text`**, listede sort/filter veya benzersizlik gerekiyorsa pratik değil. Sanctum default'u böyle; UI bir varchar gibi davranıyor (`max:255` validation ile).
9. **`token_prefix` set edilmemiş.** `SANCTUM_TOKEN_PREFIX` env'i boş → GitHub secret-scanning entegrasyonu devre dışı. Token formatı `{id}|{40+ char random}` — sızdırılırsa GitHub otomatik tespit edemez.
10. **Stateful SPA vs. Bearer karışıklığı.** `config/sanctum.php` `stateful` domain listesi env-driven (`FRONTEND_URL`'ten otomatik); fakat web frontend cookie auth değil Bearer kullanıyor. `stateful` ayarı pratikte sadece CSRF + session bypass için bir back-up, kafa karıştırıcı.

## Refactor / İyileştirme Fırsatları

- **A. Default `expires_at` ekle.** SPA token için kısa (örn. 30 gün rolling), MCP token için kullanıcı seçebileceği bir TTL (3 ay / 6 ay / 1 yıl / never). En azından `config('sanctum.expiration')` set edip Sanctum'un built-in TTL'ini açmak tek satırlık iyileştirme.
- **B. `mcp` ability'sini gerçek kullan.** MCP'nin yazma kapsamı genişledikçe (`add_competitor`, vb.) destructive endpoint'lerde `Route::middleware('abilities:mcp,write')` ile scope ayrımı yapılmalı; veya SPA token'ları için ayrı `web` ability'si tanımlayıp endpoint'lerde ability bazlı erişim modeli kurmalı.
- **C. `name = 'auth-token'` rezerve edilsin.** `StoreApiTokenRequest`'e `Rule::notIn(['auth-token'])` ekle ki kullanıcı oluşturduğu token'la kendini logout etmesin.
- **D. User cascade.** `User` modelinde `deleting` event'inde `tokens()->delete()` veya migration'a manuel `ON DELETE CASCADE` ekle (morph olduğu için FK koyamıyoruz; event hook daha pratik).
- **E. Stale token cleanup.** `php artisan sanctum:prune-expired --hours=…` Laravel scheduler'a eklensin; `expires_at` doluyken otomatik temizler. `last_used_at` > 90 gün önce olanları flag/uyarı ile listeleme UI'a eklenebilir.
- **F. Token preview.** UI'da plaintext'i tek seferlik gösterirken token'ın ilk 6 + son 4 karakterini DB'de ayrı bir kolonda tut (yeni migration: `last_four`, `prefix`) — kullanıcı listeden hangi token'ın hangi cihazda kullanıldığını tanıyabilsin. Sanctum 4.x'te native değil, custom kolon gerekir.
- **G. `SANCTUM_TOKEN_PREFIX` aktive et.** `appstorecat_` gibi sabit bir prefix env'e koy → GitHub secret-scanning regex'i yazılabilir, sızıntı tespit edilir.
- **H. Multi-session SPA support.** `auth-token`'ı her login'de silmek yerine `auth-token-{device-fingerprint}` veya `auth-token-{ip}` adıyla saklayıp paralel oturumlara izin ver; kullanıcıya "active sessions" listesi göster.
- **I. `LoginResource` swagger schema'sını netleştir.** `token` field'ı `LoginResource` içinde ama `auth-token` ile MCP token'ı arasındaki farkı dokümante eden bir not yok. README/OpenAPI'a "this token is rotated on every login" notu eklenmeli.
- **J. Audit log.** Token create/revoke event'leri loglanmıyor. Güvenlik açısından `TokenAuthenticated` Sanctum event'ini dinleyip stale IP / impossible-travel uyarısı üretmek ilerideki bir çalışma.
