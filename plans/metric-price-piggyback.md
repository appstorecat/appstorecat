# Plan: Metric price/currency piggyback from listings

**Status:** Backlog — deferred
**Related bug:** Bug #5 in `report_20apr.md` (20 Apr 2026)
**Related table:** `app_metrics.price`, `app_metrics.currency`

## Context

`app_metrics.price` and `app_metrics.currency` columns exist and are
per-country/per-date, but they are **never populated**. A current audit
shows 226/226 metric rows have `price = NULL`.

Root cause (two parts):

1. The scraper's `/apps/:id/metrics` endpoint does not include price or
   currency in its response:
   ```json
   { "rating": ..., "rating_count": ..., "rating_breakdown": ..., "file_size_bytes": ... }
   ```
2. The `/apps/:id/listings` endpoint **does** carry `price` and
   `currency` per storefront, but `AppSyncer::saveMetric` only reads
   from the metrics response. The planned listing→metric piggyback was
   never implemented.

## Goal

Populate `app_metrics.price` and `app_metrics.currency` without adding
extra scraper calls. Reuse the per-country listing price that the
listings phase already captures.

## Out of scope

- Price history over time (already covered — `app_metrics` is daily).
- Multi-currency normalization (e.g. converting to USD).
- Scraper-side changes — the metrics endpoint contract stays as is.

## Approach

### Option A — pass price/currency into `saveMetric` from the listings phase

The listings phase runs before the metrics phase
(`syncListingsPhase` → `syncMetricsPhase`). The per-country price is
captured in `app_store_listings.price` / `app_store_listings.currency`
keyed by `(app_id, version_id, locale)`.

During `fetchAndSaveMetric(country)`, look up the most recent listing
for this app+country combination and pipe its price/currency into the
`saveMetric` payload:

```php
private function fetchAndSaveMetric(App $app, ?AppVersion $version, string $country, SyncStatus $syncStatus): void
{
    // ... existing attempt loop ...

    if ($result->success) {
        $data = $result->data;

        // Piggyback per-country price from the matching listing captured
        // in the prior phase. Fall back to null if no listing exists
        // (e.g. country is unavailable, scraper 404'd).
        [$price, $currency] = $this->lookupListingPrice($app, $version, $country);
        $data['price'] = $price;
        $data['currency'] = $currency;

        $this->saveMetric($app, $version, $country, $data);
        return;
    }
}

private function lookupListingPrice(App $app, ?AppVersion $version, string $country): array
{
    $locale = $this->defaultLocaleForCountry($app, $country) ?? 'en-US';

    $listing = StoreListing::where('app_id', $app->id)
        ->when($version, fn ($q) => $q->where('version_id', $version->id))
        ->where('locale', $locale)
        ->orderByDesc('fetched_at')
        ->first(['price', 'currency']);

    return [$listing?->price, $listing?->currency];
}
```

### Option B — connector-level enrichment

Change `ITunesLookupConnector::fetchMetrics` to also fetch listings in
parallel and merge price/currency into the metric payload. Adds an
extra scraper call per country, doubling the metric-phase load. **Not
recommended** — Option A is free in scraper budget.

## Tricky edges

1. **`country_code = 'zz'` (Android global)** — Android metrics aren't
   per-country. The Android connector still doesn't populate
   price/currency in metric responses, but the listing is captured once
   at the app level and its price/currency can be piggybacked onto the
   single `zz` row.
2. **Listing not yet captured** — if the listings phase failed for this
   country's default locale (country-wide 404), the lookup returns
   null. Leave price null — accurate.
3. **Listing version mismatch** — `app_versions` row may be created
   after saveMetric in edge cases (race with saveVersion). Guard with
   `when($version, ...)` and fall back to latest listing regardless of
   version.
4. **Default locale resolution** — `defaultLocaleForCountry()` already
   exists on `AppSyncer`; reuse it. For Android pass `'zz'` through to
   the app's origin locale.

## Migration path

No schema change. `app_metrics.price` is already nullable (see bug 1.1
fix, migration `2026_04_06_000005_...`).

## Verification plan

1. Track ChatGPT iOS (known storefronts with paid-currency variance:
   US→USD, TR→TRY, GB→GBP).
2. Run a fresh sync.
3. Assert:
   - `app_metrics` rows for `us, tr, gb` have `price >= 0` (may be 0 for
     free app) and `currency` matching the storefront (`USD`, `TRY`,
     `GBP`).
   - Rows for `ru, cn, hk, by` (unavailable) still have
     `price=NULL, currency=NULL, is_available=false` — untouched by
     piggyback.
4. Confirm no extra scraper calls by comparing request counts to the
   previous sync.

## Rollout

- Ship behind no feature flag; single commit, single service (Laravel).
- `make queue-restart` after merge so running workers pick up the
  change.
- Back-fill historical rows: optional one-off artisan command that
  walks all `app_metrics` rows, looks up the sibling listing by
  `(app_id, version_id, country → locale)`, and updates price/currency
  in place. Drop if historical accuracy isn't needed.

## Done when

- Fresh syncs populate price/currency for every `is_available=true`
  metric row.
- Unavailable rows remain `price=NULL, currency=NULL`.
- Documented in `docs/tr/architecture/sync-pipeline.md` alongside the
  existing metrics phase description.
