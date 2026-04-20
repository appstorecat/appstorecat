# Plan: Rating breakdown retry for partial-success metric rows

**Status:** Backlog — deferred
**Related bug:** Bug #6 in `report_20apr.md` (20 Apr 2026)
**Related table:** `app_metrics.rating_breakdown`

## Context

A metric row can land in the DB with `rating_count > 0` but
`rating_breakdown = NULL`. Current audit: ChatGPT has 5 such rows
(`sa`, `ge`, `hr`, `nz`, `om` storefronts). Live re-probes of those
storefronts return full `rating_breakdown` — the null was a
transient capture failure that became permanent.

Root cause:

1. The iOS scraper's `fetchMetrics` sources `rating_breakdown` from two
   places:
   - `info.histogram` from `app-store-scraper` lookup.
   - Web scrape fallback (`scrapeAppStorePage`) parsing
     `serialized-server-data`.
2. For low-traffic storefronts or during transient upstream hiccups,
   **both** sources can return nothing, and the scraper returns
   `rating_breakdown: null` without signalling the partial success.
3. `AppSyncer::saveMetric` stores the null. The pipeline considers the
   metric "successful" — no retry, no entry in `sync_statuses.failed_items`.
4. Reconciliation (`ReconcileFailedItemsJob`) never revisits the row.

The scraper already emits a `console.warn` when both sources are empty
(added in commit `ceb3274`), so the problem is **observable in logs**
— but the DB stays null until the whole app re-syncs.

## Goal

Detect "success with incomplete payload" at the AppSyncer layer, push
those rows into the reconciliation queue so they get retried, and
eventually fill in the missing `rating_breakdown` on a later attempt.

## Out of scope

- Android — Google Play returns a reliable histogram or doesn't; no
  silent half-success pattern observed.
- A new DB column to track completeness (e.g. bitmask from improvement
  matrix 1.2) — nice-to-have, but this plan does not require it.
- Changing the scraper's contract (no new HTTP 503 / partial flags).

## Approach

### Detection

After `saveMetric` writes a row, check:

```php
$isIncomplete = ($data['rating_count'] ?? 0) > 0
    && empty($data['rating_breakdown']);
```

If true, this row is eligible for a future retry.

### Reconciliation entry

Push an entry into `sync_statuses.failed_items` with a new `type`:

```php
$this->pushFailedItem($syncStatus, [
    'type' => 'metric_breakdown',
    'country_code' => $country,
    'reason' => SyncStatus::REASON_PARTIAL_PAYLOAD,
    'retry_count' => 0,
    'last_attempted_at' => now()->toIso8601String(),
    'next_retry_at' => $this->nextRetryAt(1)->toIso8601String(),
    'permanent_failure' => false,
    'last_error' => 'rating_breakdown missing from scraper response',
]);
```

New enum value: `SyncStatus::REASON_PARTIAL_PAYLOAD`.
New `type` value: `metric_breakdown`.

### Reconciler handling

`AppSyncer::retryFailedItem` gains a new branch:

```php
if ($item['type'] === 'metric_breakdown') {
    $connector = $this->connector($app);
    $country = $item['country_code'];
    $fetchCountry = $country === AppMetric::GLOBAL_COUNTRY ? 'us' : $country;
    $result = $connector->fetchMetrics($app, $fetchCountry);

    if (! $result->success || empty($result->data['rating_breakdown'])) {
        return false; // still partial — try again later
    }

    AppMetric::where('app_id', $app->id)
        ->where('country_code', $country)
        ->whereDate('date', today())
        ->update(['rating_breakdown' => $result->data['rating_breakdown']]);

    return true;
}
```

### Retry budget

Add the new reason to `config/appstorecat.php`:

```php
'max_attempts' => [
    'http_500' => 10,
    'http_429' => 15,
    'timeout'  => 10,
    'empty_response' => 3,
    'partial_payload' => 5, // new
],
```

Five retries over the reconciliation cadence (~15 min each) = up to
~75 min of recovery window. After that the item is marked
`permanent_failure: true` and left as-is until the next full sync.

## Tricky edges

1. **Row not yet created when reconciler runs** — if the initial sync
   failed before writing the metric row at all, reconciler's
   `update()` is a no-op. Should be rare; add a `whereExists` guard
   and log if missing.
2. **Same-day overwrite** — the unique key
   `(app_id, country_code, date)` guarantees one row per country per
   day. Reconciler always updates today's row.
3. **Breakdown stabilizes on second attempt but not third** — unusual
   but possible. Treat each retry independently; don't accumulate.
4. **Android** — skip the whole detection for Android apps
   (`$app->isAndroid()`), they don't exhibit this pattern.

## Schema change

None. Uses existing `sync_statuses.failed_items` JSON column.

## Rollout

- Single commit, single service (Laravel).
- `make queue-restart` so `SyncAppJob` workers pick up the new detection.
- `make schedule` or wait for the 15-min reconciliation cron to process
  the first batch.
- No back-fill needed — the detection runs on every new sync; old null
  rows will get picked up whenever their app is re-synced.

## Verification plan

1. Snapshot current null-breakdown count:
   `SELECT COUNT(*) FROM app_metrics WHERE rating_breakdown IS NULL AND rating_count > 0`
2. Trigger a fresh sync for an app with known incomplete breakdown.
3. Assert `sync_statuses.failed_items` contains
   `type=metric_breakdown` entries for the bad storefronts.
4. Wait for the reconciliation cron (or run it manually).
5. Re-run the snapshot query — count should drop.
6. Manually inspect one row: `rating_breakdown` JSON populated with all
   five star buckets.

## Done when

- No new null-breakdown rows survive past one full reconciliation cycle
  for apps with `rating_count > 0`.
- Logs show
  `rating_breakdown_missing_both_sources` warnings correlating 1:1
  with `failed_items.type=metric_breakdown` entries.
- Reconciler successfully updates rows on retry.

## Open decisions

1. Should we add the completeness bitmask column (improvement matrix
   1.2) as part of this plan, or keep it as a separate follow-up?
   Proposed: separate — the partial_payload retry is enough to fix the
   immediate pain; the bitmask is an observability enhancement.
2. Should the `AppDetailResource.rating` pick the latest complete row
   instead of the latest row? For now it's a display nuance; punt.
