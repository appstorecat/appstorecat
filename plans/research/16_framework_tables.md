# Laravel Framework Default Tabloları — Audit

## Genel Bakış

AppStoreCat şemasında Laravel skeleton'undan gelen altı default tablo yer alıyor: `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`. Migration'lar Laravel 13 stub'larıyla birebir aynı (sadece comment'ler eklenmiş).

Runtime driver konfigürasyonu Redis ağırlıklı. `.env.example` ve `.env`'de `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`. Sadece `SESSION_DRIVER=database` kalmış. Sonuç olarak `cache`, `cache_locks`, `jobs`, `job_batches` tabloları schema'da var ama runtime'da hiç yazılmıyor — Redis bu işleri üstleniyor. Yalnızca `failed_jobs` (Redis queue başarısızlıkları DB'ye yazılıyor; `config/queue.php:124` `QUEUE_FAILED_DRIVER=database-uuids`) ve `sessions` aktif olarak kullanılıyor.

Migration dosyaları:
- `server/database/migrations/0001_01_01_000000_create_users_table.php` — `sessions` burada yaratılıyor (Laravel default user-skeleton'da gömülü)
- `server/database/migrations/0001_01_01_000001_create_cache_table.php` — `cache`, `cache_locks`
- `server/database/migrations/0001_01_01_000002_create_jobs_table.php` — `jobs`, `job_batches`, `failed_jobs`

## Tablolar Listesi

### `cache`
- PK: `key` (varchar)
- Sütunlar: `value` (mediumText), `expiration` (int, indexed)
- Şu an: 0 satır, ~0.02 MB. Runtime'da dolmuyor.

### `cache_locks`
- PK: `key`
- Sütunlar: `owner`, `expiration` (indexed)
- Şu an: 0 satır, ~0.02 MB. Mutex lock'ları Redis'te.

### `jobs`
- PK: `id` (bigint AI)
- Sütunlar: `queue` (indexed), `payload` (longText), `attempts`, `reserved_at`, `available_at`, `created_at`
- Şu an: 0 satır. Tüm queue'lar (`sync-tracked-{platform}`, `sync-on-demand-{platform}`, `charts-{platform}`, `default`) Redis üzerinde.

### `job_batches`
- PK: `id` (UUID string)
- Sütunlar: `name`, `total_jobs`, `pending_jobs`, `failed_jobs`, `failed_job_ids` (JSON), `options`, `cancelled_at`, `created_at`, `finished_at`
- Şu an: 0 satır. Codebase'de `Bus::batch(...)` çağrısı yok (sadece `Bus::dispatch`).

### `failed_jobs`
- PK: `id` (bigint AI), unique `uuid`
- Sütunlar: `connection`, `queue`, `payload` (longText), `exception` (longText), `failed_at` (timestamp, default current)
- Şu an: **1913 satır, ~65.5 MB data + 0.14 MB index** (`information_schema.tables` ölçümü). Son 7 gün:
  - 2026-05-09: 135 / 2026-05-10: 155 / 2026-05-11: 199 / 2026-05-12: 197 / 2026-05-13: 230 / 2026-05-14: 213 / 2026-05-15: 159
  - Günde ~150–230 yeni satır birikiyor.
- En son hata: `Illuminate\Queue\TimeoutExceededException` — `App\Jobs\Sync\ReconcileFailedItemsJob` içinde GooglePlay scraper'a yapılan HTTP GET timeout'a düşmüş. `payload` + `exception` longText alanları büyük (her satır ~30–40 KB).

### `sessions`
- PK: `id` (varchar 255), FK-yok `user_id` (indexed)
- Sütunlar: `ip_address` (varchar 45), `user_agent` (text), `payload` (longText, base64), `last_activity` (int, indexed)
- Şu an: 7 satır, ~0.02 MB. Web SPA Bearer token akışı kullansa da Sanctum stateful + Swagger UI cookie'leri için session burada tutuluyor.

## Driver Konfigürasyonu

`server/.env.example` ve `server/.env` default'ları:

| Env | Değer | Config defaults (`config/*.php`) |
|---|---|---|
| `QUEUE_CONNECTION` | `redis` | `config/queue.php:16` fallback `database` |
| `CACHE_STORE` | `redis` | `config/cache.php:18` fallback `database` |
| `SESSION_DRIVER` | `database` | `config/session.php:21` fallback `database` |
| `QUEUE_FAILED_DRIVER` | (set yok, default) | `config/queue.php:124` → `database-uuids` |
| `REDIS_CLIENT` | `phpredis` | — |
| `REDIS_HOST` | `appstorecat-redis` | docker-compose service |

Yani:
- **Queue** → Redis (failures `database-uuids` üzerinden MySQL'e yazılıyor)
- **Cache** → Redis
- **Cache locks** → Redis (lock'lar cache store ile aynı driver'ı kullanıyor)
- **Session** → MySQL
- **Failed jobs** → MySQL (varsayılan failed driver)

## Gerçek Kullanım

Redis driver aktif olduğu için `cache`, `cache_locks`, `jobs`, `job_batches` tabloları schema'da boş duruyor. Migration'lar Laravel skeleton'undan gelen stub'lar; framework upgrade ergonomisi için tutulmuş ya da driver geri çevrilirse (örn. lokal debug için `CACHE_STORE=database`) hazır olsun diye.

`sessions` aktif ama hacim çok düşük (7 satır) çünkü web SPA Bearer token üzerinden çalışıyor; tablo esas olarak Sanctum stateful akışı + Swagger UI + olası web guard kullanımları için yedekte.

`failed_jobs` tek "gerçek" ağır framework tablosu. Tüm scraper retry ve `ReconcileFailedItemsJob` timeout'ları buraya düşüyor.

## Scheduler & Pruning

`server/routes/console.php`:
```
appstorecat:apps:sync-tracked --ios            */20 * * * *
appstorecat:apps:sync-tracked --android        */20 * * * *
appstorecat:sync:reconcile                     */15 * * * *
appstorecat:charts:sync-daily --ios            0 0:30 * * *
appstorecat:charts:sync-daily --android        0 0:30 * * *
```

Aşağıdakilerin **hiçbiri** scheduler'da yok:
- `queue:prune-failed` (failed_jobs retention)
- `queue:prune-batches` (job_batches retention)
- `auth:clear-resets` (password_reset_tokens GC)
- Session GC için cron (Laravel kendi GC lottery'sini çalıştırıyor, ayrı schedule gerekmiyor)

`bootstrap/app.php`'de de `withSchedule(...)` veya manuel prune-failed referansı yok.

## Gözlemler & Kokular

- **`failed_jobs` retention politikası yok.** 1913 satır × ~35 KB ortalama = 65.5 MB. Günde ~200 yeni başarısızlık ekleniyor; pruning olmadan tablonun aylık büyümesi ~6 MB. Hem bağımsız bir disk smell, hem de `plans/database/trending-chart-entries-sparse-storage.md:442` çerçevesinde out-of-scope kalemi.
- **Aynı timeout exception ezici çoğunlukta.** Son kayıtlardan en azından bir örnek `ReconcileFailedItemsJob` içinde GooglePlay timeout'u. `exception` longText'i her seferinde tam stack trace yazıyor (60+ frame, ~30 KB). Aynı root cause için tekrar tekrar 30 KB yazmak hem disk hem index/scan açısından maliyetli.
- **`jobs` / `job_batches` / `cache` / `cache_locks` schema'da ölü.** Driver Redis olduğu sürece bu migration'lar runtime'a hiçbir şey eklemiyor. Migration silinemese bile (rollback bozulur), `down()` invariant'ları yine de Laravel skeleton'undan ibaret.
- **`Bus::batch()` kullanılmıyor.** `job_batches` tablosu temamen gereksiz — kod tabanında batch dispatch yok.
- **Migration comment'leri yanıltıcı olabilir.** Örn. `failed_jobs.connection` comment'i "e.g. redis, database" diyor — pratikte tüm satırlarda `redis` olacak.
- **Failed driver `database-uuids` seçimi mantıklı.** Redis queue başarısızlıklarının Redis'e geri yazılması retention ve görünürlük için kötü olurdu; MySQL'de tutulması doğru tercih. Ama pruning olmadan tek yönlü birikim.

## Refactor / İyileştirme Fırsatları

1. **`failed_jobs` pruning schedule'a eklensin.** `routes/console.php`'e:
   ```php
   Schedule::command('queue:prune-failed --hours=168')->daily(); // 7 gün
   ```
   veya kullanım profiline göre 48–72 saat. Mevcut hacmi tek seferlik `queue:flush` ile temizleyip sonrasında günlük prune açmak da bir seçenek.
2. **`job_batches` pruning** — `Bus::batch()` kullanılmıyorsa skip; eklenirse `queue:prune-batches` schedule'a alınsın.
3. **Ölü migration'lar.** Database driver'a geri dönüş ihtimali yoksa `cache`, `cache_locks`, `jobs`, `job_batches` tabloları drop edilebilir (yeni migration + `down()`'da yeniden create). Bu kazanç çok küçük (toplam ~0.1 MB) ve framework upgrade ergonomisini bozar — düşük öncelik, sadece "schema temizliği" amaçlıysa.
4. **`failed_jobs.exception` boyutu.** Stack trace'i tam tutmak yerine fingerprint + ilk N frame saklayan custom failed handler düşünülebilir, ama framework `database-uuids` driver'ını override etmek gerekir — ROI düşük.
5. **`sessions` için outgoing FK** — Migration `user_id` indexed ama FK yok; `users` silindiğinde orphan kayıt kalıyor. Düşük öncelik, çünkü tablo zaten küçük ve session GC kendi temizliğini yapıyor.
6. **`SESSION_DRIVER=redis` opsiyonu.** Failed jobs hariç tüm framework state Redis'te; session de Redis'e taşınırsa `sessions` tablosu da düşürülebilir. Çok düşük öncelik (~0.02 MB ve 7 satır).

## Sonuç

Tek anlamlı sorun: `failed_jobs` retention yokluğu (65.5 MB, günde ~200 satır birikim). Tek satırlık scheduler entry'si ile çözülür. Diğer framework default tabloları büyük ölçüde ölü kod ama operasyonel maliyetleri sıfır; framework upgrade güvenliği için bırakmak makul.
