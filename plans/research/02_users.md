# `users` Tablosu — Audit

## Genel Bakış

`users` tablosu, AppStoreCat'in tek tip kimlik (identity) entity'sidir. Sistemde rol/team/organization katmanı yoktur; her şey kullanıcıya per-user bağlanır (tracked apps, competitors, API tokens). Auth modeli:

- **Public**: `POST /v1/auth/register`, `POST /v1/auth/login` (5 req/dakika rate limit)
- **Protected**: `auth:sanctum` middleware + 60/dakika (prod) veya 500/dakika (local) throttle
- **Token tipi**: `auth-token` (web SPA için, abilities boş) + `mcp` ability'li named tokens (UI'dan oluşturulur)
- **SPA stateful auth**: `config/sanctum.php` `stateful` domain destekli, ancak web frontend Bearer token akışı kullanıyor (localStorage)

Tablo Laravel default skeleton'undan minimum sapmayla geliyor — Fortify/Jetstream yok, custom rol/permission tablosu yok.

## Şema

Migration: `server/database/migrations/0001_01_01_000000_create_users_table.php:14-26`

| Sütun | Tip | Null | Default | Açıklama |
|---|---|---|---|---|
| `id` | `bigint unsigned` (PK, AI) | Hayır | — | Birincil anahtar. |
| `name` | `varchar(255)` | Hayır | — | UI display name; serbest format, unique değil. |
| `email` | `varchar(255)` | Hayır | — | Login identifier. Unique index. Migration comment "lowercased on insert" diyor ama kodda lowercasing yapılmıyor. |
| `email_verified_at` | `timestamp` | Evet | `null` | Set edildiğinde email doğrulanmış sayılır. Şu an doğrulama akışı yok; sadece email değişince null'a düşürülüyor. |
| `password` | `varchar(255)` | Hayır | — | Bcrypt hash (`'hashed'` cast). |
| `remember_token` | `varchar(100)` | Evet | `null` | Laravel "remember me" cookie token'ı. SPA akışında kullanılmıyor. |
| `created_at` | `timestamp` | Evet | `null` | — |
| `updated_at` | `timestamp` | Evet | `null` | — |

## Index'ler & FK'lar

- **PK**: `id`
- **UNIQUE**: `users_email_unique` → `email`
- **Outgoing FK**: yok.
- **Incoming FK** (cascade on delete):
  - `user_apps.user_id` → cascade
  - `app_competitors.user_id` → cascade
  - `personal_access_tokens.tokenable_id` (morph; cascade YOK — manuel silinmesi gerekiyor)
  - `sessions.user_id` (FK constraint yok, sadece index)
  - `password_reset_tokens.email` (FK constraint yok)

## Model

`server/app/Models/User.php`

- **Base**: `Illuminate\Foundation\Auth\User as Authenticatable`
- **Traits**: `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`
- **Fillable** (PHP 8.4 attribute, satır 28): `['name', 'email', 'password']`
- **Hidden** (satır 29): `['password', 'remember_token']`
- **Casts** (satır 43-49): `email_verified_at => datetime`, `password => hashed`
- **İlişkiler**:
  - `apps(): BelongsToMany` → `App` via `user_apps` pivot, `withPivot('created_at')` (satır 38-41)
- **OpenAPI schema** (`#[OA\Schema]`, satır 16-27): `id, name, email, email_verified_at, created_at, updated_at`.

Not: `MustVerifyEmail` interface'i implement edilmemiş (import satırı yorum satırında, satır 5).

## Yazan Yerler

| İşlem | Dosya:Satır | Set edilen kolonlar |
|---|---|---|
| **Register** | `app/Http/Controllers/Api/V1/Account/AuthController.php:38-42` | `name`, `email`, `password` (cast ile bcrypt) |
| **Profile update** | `app/Http/Controllers/Api/V1/Account/ProfileController.php:47-57` | `name`, `email`; ayrıca email değişirse `email_verified_at = null` |
| **Password change** | `app/Http/Controllers/Api/V1/Account/SecurityController.php:33-39` | `password` (current_password rule ile doğrulanır) |
| **Account delete** | `app/Http/Controllers/Api/V1/Account/ProfileController.php:74-82` | Hard delete (`$user->delete()`); önce `tokens()->delete()` |
| **Factory** | `database/factories/UserFactory.php:17-26` | `name`, `email`, `email_verified_at = now()`, bcrypt'lenmiş `password`, `remember_token` |

`remember_token`'ı uygulama kodu yazmıyor (sadece factory). `email_verified_at`'i bir yerden `true`'ya çevirme akışı yok.

## Okuyan Yerler

| Konum | Erişim | Amaç |
|---|---|---|
| `Account/AuthController.php:67` | `User::where('email', …)->first()` | Login lookup |
| `Account/AuthController.php:109-112` | `$request->user()` | `GET /auth/me` |
| `Account/ProfileController.php:27-29` | `$request->user()` | `GET /account/profile` |
| `Account/ApiTokenController.php:33-41` | `$request->user()->tokens()->where('name', '!=', 'auth-token')` | Token listesi |
| `App/AppController.php:53, 279, 303` | `$request->user()->apps()` | Tracked apps listesi/attach/detach |
| `App/CompetitorController.php:49, 80-90, 106, 186, 217` | `$request->user()->id` ve `->apps()` | Competitor scoping & ownership |
| `App/AppController.php:126, 305` | `AppCompetitor::where('user_id', …)` | Competitor cleanup on untrack |
| `ChangeMonitorController.php:45, 84` | `AppCompetitor::where('user_id', …)` | Change feed scoping |
| `DashboardController.php:50` | `request()->user()` | Dashboard counts |
| `PublisherController.php:86, 121, 170` | `$request->user()->apps()` | Publisher scoping |
| `Models/App.php:80, 85` | `belongsToMany(User::class, 'user_apps')`, `users()->where('user_id', …)` | Ters ilişki & "isTrackedBy" |

`User`'a Eloquent `findOrFail` veya direkt id ile yükleme **yok** — tüm okumalar `$request->user()` (Sanctum guard) veya `where('email', …)` üzerinden.

## API Yüzeyi

Route tanımları: `server/routes/api.php:9-31`

| Method | Path | Controller | Auth | Rate limit |
|---|---|---|---|---|
| POST | `/v1/auth/register` | `AuthController::register` | public | `throttle:5,1` |
| POST | `/v1/auth/login` | `AuthController::login` | public | `throttle:5,1` |
| POST | `/v1/auth/logout` | `AuthController::logout` | sanctum | 60/dk (prod) |
| GET | `/v1/auth/me` | `AuthController::me` | sanctum | 60/dk |
| GET | `/v1/account/profile` | `ProfileController::show` | sanctum | 60/dk |
| PATCH | `/v1/account/profile` | `ProfileController::update` | sanctum | 60/dk |
| DELETE | `/v1/account/profile` | `ProfileController::destroy` | sanctum | 60/dk |
| PUT | `/v1/account/password` | `SecurityController::updatePassword` | sanctum | 60/dk |
| GET | `/v1/account/api-tokens` | `ApiTokenController::index` | sanctum | 60/dk |
| POST | `/v1/account/api-tokens` | `ApiTokenController::store` | sanctum | 60/dk |
| DELETE | `/v1/account/api-tokens/{tokenId}` | `ApiTokenController::destroy` | sanctum | 60/dk |

Token davranışı:
- **`auth-token`** — SPA login/register'da `createToken('auth-token')` ile üretilir. Yeni login öncesi eskisi silinir (`AuthController.php:75`). Abilities: yok (default unrestricted ya da `*`).
- **MCP tokens** — `ApiTokenController::store` `createToken($name, ['mcp'])` (satır 65). API listesi `auth-token` adlı satırı filtreler (satır 37, 91).

Web tarafı:
- Store: `web/src/stores/auth.ts` (Zustand). Token `localStorage.token`'da; SSR uyumsuz, XSS'e açık.
- Pages: `web/src/pages/auth/Login.tsx`, `Register.tsx`, ayrıca `web/src/pages/settings/ApiTokens.tsx`, `Mcp.tsx`.
- Generated client: `web/src/api/endpoints/auth/auth.ts`, `web/src/api/endpoints/account/account.ts`, model `web/src/api/models/user.ts` (Orval).

## Bağımlı Tablolar

| Tablo | FK | Davranış | Migration |
|---|---|---|---|
| `user_apps` | `user_id` → `users.id` | `cascadeOnDelete()` | `2026_04_06_000001b_create_user_apps_table.php:13-15` |
| `app_competitors` | `user_id` → `users.id` | `cascadeOnDelete()` | `2026_04_06_000007_create_app_competitors_table.php:13-15` |
| `personal_access_tokens` | `tokenable_type` + `tokenable_id` (morph) | **Cascade YOK** — `ProfileController::destroy` (satır 78) elle siliyor | `2026_04_06_134342_create_personal_access_tokens_table.php` |
| `sessions` | `user_id` (sadece index, FK yok) | Otomatik temizlik yok; Laravel session GC çalışır | `0001_01_01_000000_create_users_table.php:40` |
| `password_reset_tokens` | `email` (FK yok) | E-posta değişince/silinince orphan kalır | `0001_01_01_000000_create_users_table.php:28-35` |

## Gözlemler & Kokular

1. **Soft delete yok** — `users` tablosunda `deleted_at` yok; `ProfileController::destroy` hard delete yapıyor. Hesap silindiğinde `user_apps` ve `app_competitors` cascade ile gidiyor, ancak audit/forensic için kurtarma imkansız.
2. **Email verification ölü kod** — `email_verified_at` kolonu var, factory `now()` ile dolduruyor, `ProfileController::update` email değişince null'a çekiyor — ama doğrulama maili gönderen / link tıklatan akış yok. `MustVerifyEmail` implement edilmemiş, `auth.verified` middleware hiçbir route'da kullanılmıyor.
3. **Password reset akışı eksik** — `password_reset_tokens` tablosu mevcut ve `config/auth.php:95-101` broker konfigüre, ama `POST /password/email` / `POST /password/reset` endpoint'leri yok.
4. **`remember_token` kullanılmıyor** — SPA Bearer flow. Kolon ölü ağırlık; `Hidden` listesinde olsa da gerek yok.
5. **Email lowercase comment'i yalan** — Migration comment "lowercased on insert" diyor (satır 19) ama ne Model'de mutator, ne Request'te `lowercase` rule var. `Foo@x.com` ve `foo@x.com` ayrı kullanıcı olabilir; login `where('email', …)` MySQL collation'a (varsayılan `utf8mb4_unicode_ci`) güvenir.
6. **Login token rotation kısmi** — `AuthController::login:75` sadece `auth-token` isimli satırı siliyor; çoklu tarayıcı/oturum senaryosunda **diğer tarayıcının token'ı invalidate olur**. MCP token'ları korunuyor, doğru tasarım — ama davranış API kullanıcısına dokümante değil.
7. **Rate limit aşırı global** — `throttle:5,1` IP başına; aynı IP'den birden fazla geliştirici test ederse register/login boğulur. Login için ayrı, email-bazlı throttle yok → credential stuffing'e açık.
8. **`Sanctum::expiration` null** — `config/sanctum.php:53`. Token'lar süresiz; SPA logout çağrılmadıkça `localStorage` token sonsuza dek geçerli.
9. **`current_password` rule** (`PasswordValidationRules.php:27`) `auth.web` guard'a varsayılan bakar; Sanctum bearer auth'ta da çalışıyor ama Laravel sürüm yükseltmelerinde kırılma riski var — explicit `current_password:sanctum` yazılabilir.
10. **`ProfileController::update` 3 ayrı save dokümantasyonu** — `fill()` + `isDirty()` + `save()` (satır 49-55) pattern doğru, ancak update sonrası response'ta `email_verified_at` null geliyor. Frontend bunu kullanmıyor (yukarıdaki #2 nedeniyle).
11. **Cascade delete loglama yok** — Hesap silindiğinde N tracked app + M competitor silinir, hiçbir audit log yok.
12. **`HasApiTokens` + SPA çakışması** — `auth:sanctum` SPA cookie ve Bearer'ı aynı anda kabul eder. `config/sanctum.php:40` `'guard' => ['web']`. Stateful domain ayarlı ama frontend `Authorization: Bearer` ile çalışıyor; her iki yolu da test edilmiş değil, dual-mode davranışı belirsiz.
13. **Migration filename `0001_01_01_...`** — Laravel default. `personal_access_tokens` migration'ı 4 ay sonra (`2026_04_06_134342_...`) gelmiş; iki migration'ı tek "auth setup" altında konsolide etmek gerekebilir.
14. **`User` modelinde scope yok** — `verified()`, `unverified()` gibi yok. Factory'de `unverified()` state var ama hiç kullanılmıyor (test arama: `unverified` referansı yok).
15. **`MessageResource` 'Invalid credentials' login fail** — `AuthController::login:70` HTTP 401. Standart Laravel `ValidationException` formatı yerine `MessageResource` döndüğü için frontend `fieldErrors` formatına uymayan ayrı bir branch tutuyor.

## Refactor / İyileştirme Fırsatları

1. **Email verification akışını tamamla veya kolonu kaldır** — Karar ver: ya `MustVerifyEmail` implement et + `auth.verified` middleware grubu ekle + `POST /v1/auth/email/verify` endpoint'i + mail template, ya da kolonu drop et (`email_verified_at`, ilgili factory state, profile-update-null-coercion).
2. **Password reset endpoint'leri** — `password_reset_tokens` tablosu zaten var. `POST /v1/auth/password/forgot`, `POST /v1/auth/password/reset` ekle. (Open source kurulumlarda mail driver konfigürasyonu gerekecek; doc'a ekle.)
3. **Soft delete + scrub job** — `softDeletes()` ekle. `destroy` artık trash'e atsın; 30 gün sonra cron ile hard delete + PII scrub job. Cascade'ler `restrictOnDelete` ya da queue temizleyici ile değiştirilir.
4. **Email normalization** — Model'de `email` set mutator (lowercase + trim). Request rule olarak `lowercase` ekle. Migration comment ile davranış uyumlu hale gelir; mevcut data için bir backfill migration düşünülmeli.
5. **Login throttle iyileştir** — IP+email kompozit throttle (`Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'))`). Login failure response'unu validation error formatına çevir → frontend tek branch.
6. **Token rotation politikası** — `auth-token` davranışı için karar: (A) tek aktif `auth-token` (mevcut, ama her yeni login diğer tarayıcıları atar), (B) her login için ayrı isim (`auth-token-{ip}` veya UUID) + UI'da "active sessions". (B) daha güvenli.
7. **Sanctum expiration** — `config/sanctum.php` `'expiration'` env'ye al, default 30 gün. SPA için refresh akışı gerekir; MCP token'ları için (TTL=null + abilities=`mcp`) korunur. Token ability bazlı ayrı expiration için `Sanctum::tokenExpiration` callback.
8. **`remember_token` kolonu drop** — SPA-only flow için ihtiyaç yok. Migration ile drop.
9. **`destroy` cascade audit** — `personal_access_tokens` morph FK olmadığı için Laravel cascade çalışmıyor; `ProfileController::destroy` elle `tokens()->delete()` çağırıyor (doğru). Bu davranış Model event'e (`deleting`) taşınabilir; controller'dan logic uzaklaşır.
10. **`auth:sanctum` davranışı netleştir** — Eğer SPA gerçekten Bearer kullanıyorsa `config/sanctum.php` `'guard'` `[]` yapılabilir (stateful'ı kapat). Aksi takdirde CSRF + cookie + Bearer üçlüsü saldırı yüzeyini artırır.
11. **`User` resource exposure** — `UserResource` `updated_at` döndürmüyor ama OpenAPI schema'da var → senkronizasyon hatası. Ya OA schema'dan düş ya da resource'a ekle.
12. **`HasApiTokens` ability'leri tipla** — Magic string `'mcp'` 2 yerde geçiyor (`ApiTokenController.php:65`, MCP package). Bir enum/const'a çıkar (`App\Enums\TokenAbility::MCP`).
13. **Test coverage** — `users` tablosuna direkt test yok (factory `unverified` state hiç çağrılmıyor). Login throttle, register validation, soft-delete-after-30d gibi senaryolar için feature test eksik.
14. **Migration konsolidasyonu** — `personal_access_tokens` migration'ı `0001_01_01_...` ile aynı dönemde olmalı; gelecek "fresh install" hikayesi için sıralı tutmak okunabilirlik adına iyi olur (cosmetik).
15. **Auth model namespace** — `App\Models\User` yerine `App\Domain\Auth\User` benzeri bir domain altına taşıma (mevcut `.arc/` konvansiyonu ile uyumlu mu kontrol edilmeli) — uzun vadeli refactor.
