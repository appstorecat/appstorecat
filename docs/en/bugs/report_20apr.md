# Sync Pipeline Bug Report — 20 Apr 2026

Branch: `feat/sync-pipeline-rewrite`
Context: After the multi-country sync refactor, smoke tests on three apps
(ChatGPT `6448311069`, Virtual Phone Number `io.sms.virtualnumber`, Mavi
`927394856`) surfaced several bugs. Each item below records the live
symptom, how it reproduces against the running scrapers, and the root
cause — no fixes are applied here, this file is a punch list.

## Summary

| # | Bug | Severity | Layer |
|---|-----|----------|-------|
| 1 | Scraper returns 500 for "App not found (404)" responses | High | scraper-ios |
| 2 | `classifyError` treats 404-in-500-wrapped errors as `http_500` | High | Laravel AppSyncer |
| 3 | `syncIdentity` fallback uses `origin_country` but discovery sets it to `us` | High | Laravel AppSyncer + App::discover |
| 4 | `syncAll` keeps running listing + metrics phases after identity fails | High | Laravel AppSyncer |
| 5 | `app_metrics.price` / `currency` always null | Medium | scraper-ios + AppSyncer |
| 6 | `app_metrics.rating_breakdown` occasionally null on successful syncs | Medium | scraper-ios + pipeline |
| 7 | `progress_total` counter flips between listings/metrics phases | Low | SyncStatus tracking |
| 8 | `apps.is_available` is global but availability is country-specific | Medium | data model |

---

## Bug 1 — Scraper returns 500 for "App not found"

**Symptom (repro):**

```bash
curl -s -w "\nHTTP=%{http_code}\n" \
  "http://localhost:7462/apps/6448311069/metrics?country=ru"
# {"error":"App not found (404)"}
# HTTP=500

curl -s -w "\nHTTP=%{http_code}\n" \
  "http://localhost:7462/apps/6448311069/listings?country=hk&lang=zh-Hant"
# {}
# HTTP=500
```

The scraper body even says "App not found (404)" but the HTTP status is
`500`.

**Root cause:**

Every route handler in `scraper-ios/src/main.ts` has a blind catch:

```ts
} catch (e: any) {
  return reply.status(500).send({ error: e.message });
}
```

`app-store-scraper`'s `store.app()` throws `Error("App not found (404)")`
when the storefront does not carry the app. The route never inspects the
error before deciding the HTTP status.

**Impact:**

Laravel can't distinguish "app missing in this country" (permanent) from
"scraper / upstream Apple error" (transient). Every 404-means-500 response
flows into bug #2 and then into bug #3.

**Observed on scrapers:**

ChatGPT (`6448311069`) returns 500 for `cn`, `hk`, `mo`, `ru`, `by`, `ve`,
`zh-Hans`, `zh-Hant`. Mavi (`927394856`) returns 500 for every country
except `tr`.

---

## Bug 2 — `classifyError` treats wrapped 404 as `http_500`

**Symptom:**

`sync_statuses.failed_items` for ChatGPT shows `reason: "http_500"` for
cn / ru / hk / mo / by / ve — each error message reads
`500 - App not found (404)`. `http_500` maps to
`max_attempts.http_500 = 10`, so reconciliation will retry those items
ten times before giving up, even though the app is genuinely unavailable
in those storefronts.

**Root cause:**

`AppSyncer::classifyError` checks string matches in this order:

```php
if (str_contains($lower, '429') || ...) return HTTP_429;
if (str_contains($lower, '500') || ...) return HTTP_500;   // matches first
if (str_contains($lower, 'timeout') ...) return TIMEOUT;
if (str_contains($lower, 'not found') || str_contains($lower, '404')) return EMPTY_RESPONSE;
```

The error text contains both "500" and "404" — whichever is tested first
wins. Since "500" is listed above "not found", every wrapped 404 becomes
`http_500`.

**Dependency:** Directly tied to bug #1. If the scraper returned the
correct status, this classifier would still need to be defensive because
Laravel composes errors as `"Scraper API request failed: 500 - ..."`.

---

## Bug 3 — Identity fallback misses countries beyond `origin_country`

**Symptom (repro):**

Mavi's direct scraper probe:

```bash
for c in us tr gb de jp; do
  curl -s -w " HTTP=%{http_code}\n" "http://localhost:7462/apps/927394856/identity?country=$c"
done
# us: {"error":"App not found (404)"} HTTP=500
# tr: {"app_id":"927394856","name":"Mavi",...} HTTP=200
# gb/de/jp: 404
```

Mavi exists on the Turkish storefront only, yet Laravel still marks it
`apps.is_available=0`.

**Root cause:**

`AppSyncer::syncIdentity` tries two country candidates:

1. `'us'` (hardcoded)
2. `$app->origin_country` — only if it is not already `'us'`

Mavi's row has `origin_country='us'` (the default value set by
`App::discover` when no country is supplied). Both attempts target the US
storefront, both return 404. Mavi never gets tested against `tr` even
though Apple has the data there.

This row was not created by a chart sync (there is no matching
`trending_chart_entries.app_id = 102`); `discovered_from = 8` →
`DiscoverSource::DirectVisit`. A user opened the app detail page via URL.
`App::discover` was called with a `$country` argument, but the argument
has a default of `'us'` and the `AppController` call site does not pass
the real storefront.

**Impact:**

- Any app that is live in only one or two storefronts (and not US) ends
  up flagged unavailable.
- The identity failure cascades into bug #4, generating hundreds of
  wasted scraper calls.

---

## Bug 4 — Pipeline continues after identity failure

**Symptom:**

Mavi's sync on 20 Apr at 15:23–15:24 issued **449 scraper requests** to
`scraper-ios` even though identity failed. The logs show three repeated
`identity?country=us` attempts, then the listing phase for 37
`(country, language)` pairs, then the metric phase for 111 countries,
each retried three times — all returning 500 because the app does not
exist.

`sync_statuses` for app 102 also shows `progress_total=0` with
`failed_items` holding 148 entries.

**Root cause:**

`AppSyncer::syncAll`:

```php
$identityData = $this->syncIdentity($app, $syncStatus);
$version = $this->saveVersion($app, $identityData);

$syncStatus->update(['current_step' => STEP_LISTINGS]);
$this->syncListingsPhase($app, $version, $syncStatus);

$syncStatus->update(['current_step' => STEP_METRICS]);
$this->syncMetricsPhase($app, $version, $syncStatus);
```

When `syncIdentity` returns `[]` (identity failed), `saveVersion` yields
`null`, but subsequent phases run regardless. Listings and metrics each
contain their own retry loop, so a dead app produces a burst of
`(3 attempts × 39 locales) + (3 attempts × 111 countries) = 450` calls.

**Impact:**

Wasted scraper budget, wasted reconciliation retries (items pile into
`failed_items`), and misleading UI (`progress` data does not match
reality).

---

## Bug 5 — `price` and `currency` never populated in `app_metrics`

**Symptom:**

```sql
SELECT COUNT(*), SUM(currency IS NULL) FROM app_metrics WHERE app_id=2;
-- total=105, null_currency=105
```

Every metric row for ChatGPT has `price=0` and `currency=NULL`, even for
paid-currency storefronts like TR (TRY) or GB (GBP).

**Root cause:**

Two independent reasons:

1. The scraper's `/apps/:id/metrics` endpoint does not include price or
   currency in its response:
   ```json
   {"rating":..., "rating_count":..., "rating_breakdown":..., "file_size_bytes":...}
   ```
   No `price`, no `currency`.

2. The `/apps/:id/listings` endpoint **does** carry `price` and `currency`
   per storefront — but `AppSyncer::saveMetric` only reads from the
   metrics response. The planned listing→metric piggyback was not
   implemented.

**Impact:**

Country-specific price / currency intelligence (a headline capability of
the refactor) is missing. The database schema has the columns but they
stay empty.

---

## Bug 6 — `rating_breakdown` occasionally null for successful syncs

**Symptom:**

ChatGPT (`app_id=2`) has 105 metric rows. 104 of them carry
`rating_breakdown`, one (Zimbabwe) does not. Re-running the scraper live:

```bash
curl "http://localhost:7462/apps/6448311069/metrics?country=zw"
# rating 4.75, rating_count 14106, rating_breakdown {"1":334,"2":133,...}
```

Now the data is present. The null was captured during the original sync.

**Root cause:**

`fetchMetrics` in `scraper-ios/src/scraper.ts` relies on two inputs:

1. `info.histogram` from `app-store-scraper`'s lookup call.
2. A fallback web scrape of `apps.apple.com/<country>/app/id<id>`
   (`scrapeAppStorePage`) that parses the HTML `serialized-server-data`
   blob.

The web scrape silently swallows exceptions. For low-traffic storefronts
or during transient upstream hiccups, neither source returns a
histogram, and the scraper sends back `rating_breakdown: null` without
signalling the partial success.

The AppSyncer only retries a metric request when it fails outright;
"success with missing breakdown" is considered completed. Reconciliation
never revisits the row, so it stays `null` forever unless the whole sync
re-runs.

**Impact:**

Country-level rating breakdowns become a coin-flip. No retry path covers
"successful response but incomplete payload".

---

## Bug 7 — `progress_total` flips when phases change

**Symptom:**

- Android app (Virtual Phone Number, `id=101`): `progress_done=1`,
  `progress_total=1`. Actual work: 60 locale listings + 1 GLOBAL
  metric.
- Mavi (`id=102`): `progress_done=111`, `progress_total=0` — impossible
  ratio.

**Root cause:**

`syncListingsPhase` and `syncMetricsPhase` each call
`$syncStatus->update(['progress_done' => 0, 'progress_total' => N])` at
entry. When metrics overwrites the listing total, the UI loses the
listing progress context. On failure paths (Mavi), the last update wins
and leaves nonsensical numbers.

**Impact:**

UI progress bar is misleading. Not a data corruption, but a UX
regression.

---

## Bug 8 — `apps.is_available` has wrong granularity

**Symptom:**

Mavi: `apps.is_available=0` but the app is live in TR (and presumably
reachable in several neighbouring storefronts). ChatGPT:
`apps.is_available=1` even though it is unavailable in cn / ru / hk /
mo / by / ve.

**Root cause:**

`apps.is_available` is a single boolean. `syncIdentity` sets it to
`false` whenever the US (+ origin fallback) lookup returns 404. App
availability is inherently `(app_id, country_code)`; the metric table
already encodes this with `app_metrics.is_available`, which is the
correct level.

**Impact:**

- Dashboard badges and filters read `apps.is_available` and get a
  globally coarse answer.
- Pipelines (sync-tracked, sync-discovery) filter on it and can skip
  apps that are fully live on non-US storefronts.
- When we trust `app_metrics.is_available` per country, `apps.is_available`
  becomes a redundant source of truth prone to disagreement.

---

## Supporting observations

- `discovered_from=8` (DirectVisit) is currently the only way Mavi
  entered the DB. Turning DirectVisit off would hide bug #3 for this
  specific row but not fix it — Chart discovery can create the same
  shape when the US chart happens to list a non-US app, or when Apple's
  regional charts are swapped.
- Reconciliation cron runs every 15 min. Any `failed_items` tagged
  `http_500` currently lingers in the queue for days under bug #2.
- Scraper's web-scrape fallback (`scrapeAppStorePage`) is best-effort;
  silent failures are acceptable only if another data source is
  authoritative, which is not the case for the breakdown field.

## Priority recommendation

Fix order — each layer unblocks the next:

1. **Bug 1** (scraper HTTP status) — upstream of 2, 3, 4.
2. **Bug 2** (classifyError priority) — even without #1, protects
   reconciliation from pointless retries.
3. **Bug 4** (abort pipeline on identity failure) — stops the worst of
   the wasted-request fan-out while #3 is still in progress.
4. **Bug 3** (identity multi-country fallback + discovery passes real
   country) — unlocks TR-only, JP-only, DE-only apps.
5. **Bug 5** (price/currency piggyback) — delivers the price
   intelligence the schema promises.
6. **Bug 8** (remove `apps.is_available`, lean on `app_metrics.is_available`).
7. **Bug 7** (progress counter hygiene).
8. **Bug 6** (partial-payload retry for rating_breakdown).
