# `sync_statuses` Tablo Auditi

## Genel Bakış

Her `app` için **bire bir** (`hasOne`) tutulan pipeline state satırı. Tek satır, son veya devam eden tam sync'in tüm aşamasını taşır: kuyruğa girdiği an, hangi step'te ilerlediği, kaç birim tamamlandığı, hangi locale/country birim başarısız oldu, son hata, retry zamanı.

Tablo aslında iki rolü birden üstleniyor:
- Kullanıcıya UI üzerinden "şu an %X tamamlandı" göstergesi (`useSyncStatus` polling 2 sn).
- Worker tarafında "bu app için worker çalışıyor mu, hangi failed_items'ı reconcile etmem lazım, ne zaman" sorularına cevap (`ReconcileCommand` + `ReconcileFailedItemsJob`).

## Şema

Migration: `server/database/migrations/2026_04_20_170000_create_sync_statuses_table.php`

| Kolon | Tip | Default | Not |
|---|---|---|---|
| `id` | bigIncrements | — | PK |
| `app_id` | foreignId **unique** | — | FK `apps.id`, `cascadeOnDelete`. UNIQUE constraint → app başına tek satır |
| `status` | `enum('queued','processing','completed','failed')` | `queued` | Genel sync durumu |
| `current_step` | `enum('identity','listings','metrics','finalize','reconciling')` nullable | NULL | `processing` sırasında dolu, idle iken NULL |
| `progress_done` | unsignedSmallInteger | 0 | Step başına sıfırlanır (0-65535) |
| `progress_total` | unsignedSmallInteger | 0 | UI yüzdesi için pair |
| `failed_items` | json nullable | NULL | `[{type, locale?, country_code?, reason, retry_count, last_attempted_at, next_retry_at, permanent_failure, last_error}, ...]` |
| `error_message` | text nullable | NULL | Sadece `status=failed` terminal hata için |
| `job_id` | char(36) nullable | NULL | Queue job UUID (aşağıya bkz) |
| `started_at` | timestamp nullable | NULL | Son run `processing`'e geçtiği an |
| `completed_at` | timestamp nullable | NULL | Run bittiği an (success veya failure) |
| `next_retry_at` | timestamp nullable | NULL | Reconcile zamanlayıcısı için earliest retry |
| `created_at` / `updated_at` | timestamps | — | Standart |

### Index'ler & FK'lar

- `app_id` UNIQUE + FK CASCADE → app silinirse status satırı da gider.
- `INDEX (status, next_retry_at)` → `ReconcileCommand` querysi için.
- `INDEX (completed_at)` → kullanım yerini kod tarafında **doğrulayamadım**; muhtemelen "en son tamamlanmış sync'ler" listelemesi için reserved.
- `INDEX (job_id)` → bir UUID'den status satırına geri haritalama, ama kodda bu yönde okuma yok.

## Model

`server/app/Models/SyncStatus.php`

### Const'lar (enum mirror)
- `STATUS_QUEUED`, `STATUS_PROCESSING`, `STATUS_COMPLETED`, `STATUS_FAILED`
- `STEP_IDENTITY`, `STEP_LISTINGS`, `STEP_METRICS`, `STEP_FINALIZE`, `STEP_RECONCILING`
- `REASON_HTTP_500`, `REASON_HTTP_429`, `REASON_TIMEOUT`, `REASON_EMPTY_RESPONSE`, `REASON_NETWORK_ERROR`

### Helper'lar
- `isActive(): bool` → `queued|processing`
- `hasFailedItems(): bool`
- `pushFailedItem(array $item): void` → in-memory append, **`save()` çağıran yapar**
- `removeFailedItem(callable $matcher): void` → `collect()->reject()` ile filtreler, yine save dışarıdan
- `app()` BelongsTo

### Cast
`failed_items=array`, `progress_*=integer`, `started_at/completed_at/next_retry_at=datetime`

### Fillable
Tüm operasyonel kolonlar fillable, `id` ve timestamps hariç.

## Yazan Yerler

### `App\Services\AppSyncer::syncAll()`
Tek yazıcı pipeline. Sırayla:
1. `forceFill(status=processing, current_step=identity, progress=0/0, failed_items=[], error_message=null, started_at, completed_at=null)->save()`
2. `syncIdentity()` — başarısız ise `status=failed, error_message, completed_at=now()` set edip return.
3. `update(current_step=listings)` → `syncListingsPhase()` içinde:
   - `update(progress_done=0, progress_total=count($map))`
   - her 5 locale'de bir `update(progress_done=$done)`
   - başarısız item'da `pushFailedItem()` + `save()`
4. `update(current_step=metrics)` → `syncMetricsPhase()` (10 country'de bir tick), aynı pattern
5. `update(current_step=finalize)` → DB-only finalize
6. `forceFill(status=completed, current_step=null, completed_at=now())->save()`

Notlar:
- `pushFailedItem` çağrıları öncesi `refresh()` çekilerek **race önleniyor** (`AppSyncer::pushFailedItem` private helper).
- `ensureSyncStatus()` private: `firstOrCreate` ile `status=processing` defaultlu satır yaratıyor — `SyncAppJob` zaten kendi `firstOrCreate`'ini yapıyor, bu defansif yedek.

### `App\Jobs\Sync\SyncAppJob::handle()`
- `SyncStatus::firstOrCreate(['app_id'=>$app->id], ['status'=>QUEUED])`
- Duplicate guard: `status=processing && job_id!=null` → erken return.
- `forceFill(job_id = $this->job?->getJobId() ?? Str::uuid())->save()` — gerçek Laravel queue UUID'si veya fallback olarak yerel UUID.
- `$syncer->syncAll($app, $syncStatus)` çağrısı.
- Exception → `forceFill(status=failed, error_message, completed_at=now())->save()` + rethrow.
- `ShouldBeUnique` + `uniqueId() = "sync-app-{appId}"`, `uniqueFor=3600s`, `tries=3`, `backoff=[30,60,120]`, `timeout=600s`.

### `App\Jobs\Sync\ReconcileFailedItemsJob::handle()`
- `SyncStatus::find($syncStatusId)`; yoksa veya `failed_items` boşsa exit.
- Eğer `status=processing && current_step!=reconciling` → full sync devam ediyor, atla.
- `update(current_step=reconciling)`.
- `failed_items` üzerinde rotasyon:
  - `permanent_failure` set ise olduğu gibi bırak.
  - `next_retry_at > now` ise dokunma.
  - Aksi: `$syncer->retryFailedItem()` çağır. Başarılıysa item silinir. Başarısızsa `retry_count++`, `last_attempted_at=now`, `next_retry_at = now + backoff[index]`. `retry_count >= maxAttempts[reason]` ise `permanent_failure=true`, `next_retry_at=null`.
- Son: `forceFill(failed_items, next_retry_at=earliestRetry, current_step=null, status=COMPLETED, completed_at=completed_at ?? now)`.
- `ShouldBeUnique`, `uniqueFor=1800s`, `tries=1`.

### `App\Http\Controllers\Api\V1\App\AppController::ensureSyncJob()`
- `firstOrCreate(app_id, status=QUEUED)`.
- Eğer `status=processing` ise erken return.
- Aksi: `forceFill(status=QUEUED, current_step=null, progress_done=0, progress_total=0, error_message=null, started_at=null, completed_at=null)->save()`.
- `SyncAppJob::dispatch($app->id)->onQueue("sync-on-demand-{platform}")`.

### `AppController::sync()` ve `AppController::syncStatus()`
İkisi de `SyncStatus::firstOrCreate(['app_id'], ['status'=>QUEUED])` çağırıyor. `sync()` ayrıca `ensureSyncJob`'ı tetikliyor.

### `AppController::show()` ve `AppController::listing()`
`last_synced_at` boşsa veya stale ise `ensureSyncJob()` → dolaylı yazıcı.

### `Console\Commands\Apps\SyncTrackedCommand`
`SyncAppJob::dispatch` → dolaylı yazıcı (kuyruk: `sync-tracked-{platform}`).

## Okuyan Yerler

- `AppController::show()` — `$app->load([..., 'syncStatus'])` ile `AppDetailResource` üzerinden eager-load (controller satır 121). Detail resource'ta `SyncStatus` doğrudan referans yok; relation taşıyıcı olarak App üzerinden serialize ediliyor.
- `AppController::sync()` (POST `/apps/{platform}/{externalId}/sync`) ve `AppController::syncStatus()` (GET `/apps/{platform}/{externalId}/sync-status`) → `SyncStatusResource::make($syncStatus)`.
- `App\Console\Commands\Sync\ReconcileCommand` → `failed_items` non-empty + `next_retry_at <= now OR null` + `status != processing` filtreli batch read; `ReconcileFailedItemsJob` dispatch ediyor (`sync-reconcile` kuyruğuna).
- `ReconcileFailedItemsJob` → `find($syncStatusId)`.
- `App` modeli `syncStatus(): HasOne` relation.
- Web tarafı `web/src/hooks/useSyncStatus.ts` → `useAppSyncStatus` (Orval), 2sn polling `queued|processing` iken; `completed` olunca app detail invalidate. Consumer'lar: `PartialSyncBanner.tsx`, `SyncingOverlay.tsx`, `pages/apps/Show.tsx`.

## API Yüzeyi

`server/routes/api.php`:
- `POST  /apps/{platform}/{externalId}/sync` → `SyncStatusResource`
- `GET   /apps/{platform}/{externalId}/sync-status` → `SyncStatusResource`

`SyncStatusResource` (`app/Http/Resources/Api/App/SyncStatusResource.php`) çıktısı:
```
{ app_id, status, current_step,
  progress: { done, total },
  failed_items: [...], failed_items_count,
  error_message, job_id,
  started_at, completed_at, next_retry_at,
  elapsed_ms }
```
`elapsed_ms` runtime hesaplanıyor: `(completed_at ?? now) - started_at` ms cinsinden.

## Bağımlı Tablolar

Yok. Hiçbir tablo `sync_statuses.id`'ye FK koymuyor. Tablo state taşıyıcı, ilişkisel grafiğin yaprak düğümü. `app` silinirse cascade ile temizlenir.

## Gözlemler & Kokular

1. **`enum` MySQL'de katı.** `status`, `current_step` DB seviyesinde enum olarak yaratılmış. Yeni bir step (örn. `screenshots`) veya yeni bir reason ekleneceğinde:
   - `current_step` için migration şart (`ALTER TABLE ... MODIFY COLUMN ...`).
   - `REASON_*` const'ları `failed_items` JSON içine yazıldığı için yeni reason eklemek için migration gerekmez; yine de `maxAttempts[reason]` config'i güncellenmeli (`config/appstorecat.php`).

2. **`failed_items` JSON büyüyebilir.** `AppSyncer::syncAll` her run başında `failed_items=[]` ile **sıfırlıyor**, yani tamamlanmış sync'te kalıntı olmuyor — bu iyi. Ancak `ReconcileFailedItemsJob` `permanent_failure=true` item'ları **dropping etmiyor**, "olduğu gibi bırak" diyor. Yıllar içinde permanent failure'lar birikebilir. Şu anda manuel temizleme komutu yok.

3. **`job_id` Redis queue UUID eşlemesi.** `SyncAppJob::handle` şunu yazıyor:
   ```php
   $syncStatus->forceFill(['job_id' => $this->job?->getJobId() ?? (string) Str::uuid()])
   ```
   `$this->job?->getJobId()` Laravel queue driver'ının döndürdüğü id'dir — Redis driver'da bu Horizon UUID ile aynı. Eşleşiyor. Fallback olarak yerel `Str::uuid()` kullanılıyor (job context yokken — çağrılması beklenmiyor ama defansif). **Ancak** UUID üretildikten sonra fail olunca `job_id` temizlenmiyor (hâlâ eski UUID kalıyor), `INDEX(job_id)` üzerinden lookup yapılırsa stale veriye düşülebilir. Şu anda kodda `where('job_id', ...)` araması yok, index ölü gibi duruyor.

4. **`ShouldBeUnique` guard.**
   - `SyncAppJob::uniqueId() = "sync-app-{appId}"`, `uniqueFor=3600`.
   - Redis cache lock (`cache.default` veya `unique_via`) üzerinden duplicate dispatch'leri engelliyor.
   - Kuyruk uniqueliği + DB seviyesinde `status=processing && job_id!=null` çift kontrolü var. Cache lock TTL'i sync max süresi ile uyumlu (1h, `timeout=600s`).

5. **`next_retry_at` index'i partial değil.** `INDEX (status, next_retry_at)` tam index; tabloda binlerce `next_retry_at=NULL` satırı olduğunda (her `completed` sync için) MySQL bu null'ları da tarar. Tablo büyürse `status != 'processing'` koşulu + `next_retry_at` NULL OR `<= now` formu seqscan benzeri davranabilir. PostgreSQL olsaydı `WHERE failed_items IS NOT NULL` partial index ideal olurdu; MySQL'de generated column + functional index ile çözülebilir.

6. **`firstOrCreate` her okuma noktasında.** `sync()`, `syncStatus()`, `ensureSyncJob()`, `SyncAppJob::handle()`, `AppSyncer::ensureSyncStatus()` — beş ayrı yerde `firstOrCreate`. Her biri savunmacı. Yeni app eklenip de henüz status satırı yokken `GET /sync-status` çağrılırsa otomatik `queued` satırı yaratıyor — bu "lazy initialization" pattern'i ama UI sync henüz tetiklenmediği halde "queued" göstermeye sebep olabilir.

7. **`ReconcileFailedItemsJob` her durumda `status=COMPLETED` set ediyor.** Satır 119:
   ```php
   'status' => ($empty || $allPermanent) ? STATUS_COMPLETED : STATUS_COMPLETED,
   ```
   Üçlü operatör iki dalı da aynı değere döndürüyor — ölü kod / tipo. Muhtemelen niyet `($allPermanent ? FAILED : COMPLETED)` veya benzeri idi.

8. **`completed_at` index ölü.** `INDEX(completed_at)` migration'da var ama hiçbir sorguda `ORDER BY completed_at` / `WHERE completed_at` araması yok.

9. **`current_step=reconciling` ile race-condition guard'ı.** `ReconcileFailedItemsJob`: "eğer status=processing ve current_step `reconciling` değilse skip"; full sync sırasında reconcile çakışmasını önlüyor. Doğru, ama full sync `AppSyncer` `current_step=reconciling` set etmiyor — bu kontrol asimetrik. Sadece reconcile job kendisinin önceden çalıştığını işaret ediyor; full sync zaten `current_step=identity|listings|metrics|finalize` döngüsünde olduğu için skip olur. Mantık çalışıyor ama okunabilirliği zayıf.

10. **`progress_done/progress_total` step başına resetleniyor**, tek yüzde değil. UI bunu bilmek zorunda: %50 listings + %0 metrics → toplam %50 değil, sadece "şu an metrics 0/40". Resource'ta `current_step` ile beraber sunuluyor, frontend hesaplamayı yapıyor.

11. **`unsignedSmallInteger` (max 65535)**. Locale + country sayıları küçük; risk yok ama type bilinçli seçilmemiş gibi (her şey için yeterli marj).

## Refactor / İyileştirme Fırsatları

1. **Permanent failed_items'ları arşivle veya boşalt.** `ReconcileFailedItemsJob` sonunda `permanent_failure=true` item'lar başka bir tabloya (örn. `sync_failed_items_archive`) taşınmalı, çalışan JSON yalnızca aktif failure'ları içermeli. Aksi takdirde MCP/Resource'un `failed_items_count` ı kalıcı arızalarla şişer.

2. **Bug fix: `ReconcileFailedItemsJob` satır 119** — `$allPermanent ? STATUS_FAILED : STATUS_COMPLETED` veya en azından tek bir değer kullan.

3. **`current_step` enum'unu MySQL set/enum'dan string'e çevir.** Yeni step eklemek migration kostu doğuruyor; const'ları source-of-truth yapıp DB seviyesinde `VARCHAR(32)` kullanmak hareket alanını artırır. `status` için enum mantıklı (kapalı küme), step için açık küme olabilir.

4. **`INDEX(completed_at)` ve `INDEX(job_id)` kullanım kanıtı yok.** İkisini kaldırmadan önce `ORDER BY completed_at DESC` listelemesi veya `where job_id =` ile cancellation/observability planı var mı diye `.arc/` veya `plans/` araştırılmalı; yoksa kaldırılabilir.

5. **Partial index alternatifi.** MySQL 8.4 generated column + functional index ile `(failed_items IS NOT NULL AND JSON_LENGTH(failed_items) > 0)` koşulu indexlenebilir. `ReconcileCommand` query'sini hızlandırır.

6. **`firstOrCreate` mantığını tek noktaya topla.** Bir `SyncStatusManager` service'i (örn. `app/Services/Sync/SyncStatusManager.php`) tüm `firstOrCreate`, `markQueued`, `markProcessing`, `markCompleted`, `markFailed`, `recordFailedItem` çağrılarını sahiplenmeli. Şu an business logic (`forceFill` payload'ları) `AppController` + `SyncAppJob` + `AppSyncer` + `ReconcileFailedItemsJob` arasında dağılmış durumda.

7. **`job_id` lifecycle.** Sync bittikten / failed olduktan sonra `job_id` NULL'a çekilmeli; aksi halde stale UUID kalıyor.

8. **`stale` check ve `ensureSyncJob` döngüsü.** `show()` her çağrıda stale ise dispatch ediyor; üst üste view'da `ShouldBeUnique` koruması var ama `forceFill(status=QUEUED, ...)` her seferinde state'i sıfırlıyor — bu mid-sync devam eden işin progress'ini kullanıcıya görünmez hale getirebilir. `ensureSyncJob`'ı `status=queued` ise dokunmadan return etmek tasarruf sağlar.

9. **`SyncStatusResource` UI sözleşmesi.** `progress.done / progress.total` step başına resetleniyor; UI tarafı (`PartialSyncBanner`, `SyncingOverlay`) bunu doğru yorumluyor mu doğrulamadım — backend kontratı doc'lanırsa frontend daha güvenli.

10. **Reconcile için ayrı kuyruk var (`sync-reconcile`).** CLAUDE.md kuralı "scraper-related job'lar platform-separated" diyor. Reconcile job scraper çağırdığı için `sync-reconcile-ios` / `sync-reconcile-android` ayrımına ihtiyacı var. Şu anda tek kuyruk, iOS ve Android rate-limit'leri birbirini bloklayabilir.
