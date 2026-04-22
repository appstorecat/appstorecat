# Skip listing-change detection within the same version

## Goal

Stop writing rows to `app_store_listing_changes` when a listing is updated
inside the same `app_version` it was first recorded against. Listing change
tracking should only fire when the current listing belongs to a **different**
version than the incoming payload.

## Root cause

`server/app/Services/AppSyncer.php:254-261`

```php
$existing = StoreListing::where('app_id', $app->id)
    ->where('locale', $locale)
    ->orderByDesc('id')
    ->first();

if ($existing && $existing->checksum !== $checksum) {
    $this->detectChanges($app, $existing, $data, $version);
}
```

- `$existing` is fetched by `(app_id, locale)` only — no version filter.
- If the same `(app, locale)` pair is processed twice in a single sync run
  (two partial scrapes with divergent fields — one returning a value, the
  other returning `null`), the second iteration finds the row written by the
  first iteration, sees a checksum delta, and logs a change.
- For a fresh app with only one version there is no prior version to diff
  against, yet changes still get written.

Observed symptom: app 34860 (`SMS io: Virtual Verification`, external_id
6756608507) has exactly one `app_versions` row (v1.2.7) but 8 rows in
`app_store_listing_changes`, all `field_changed = subtitle`, all on that
single version, paired as `NULL ↔ "Receive SMS Verification Code"` across
different locales.

## Proposed change (single edit)

Guard `detectChanges` so it only fires when `$existing` belongs to a strictly
earlier version than the incoming payload:

```php
if (
    $existing
    && $version !== null
    && $existing->version_id !== null
    && $existing->version_id !== $version->id
    && $existing->checksum !== $checksum
) {
    $this->detectChanges($app, $existing, $data, $version);
}
```

Guard clauses, in order:

- `$version !== null` — without a target version the diff is meaningless.
- `$existing->version_id !== null` — skip legacy/orphan listings.
- `$existing->version_id !== $version->id` — the core rule: silence same-
  version upserts.
- `checksum !== $checksum` — preserve the existing no-op fast path.

## Impact analysis

**Fixed**
- Same-version listing upserts no longer emit change rows → scraper
  flakiness stops producing phantom diffs.
- A freshly ingested app (single version) now writes zero change rows.

**Preserved**
- Real version transitions (v1.2.7 → v1.2.8): `$existing` is the last
  listing on the prior version; `version_id` differs → changes logged as
  before.
- `locale_added` / `locale_removed` path (`detectVersionChanges`, lines
  442-505) is independent and already short-circuits when there is no
  prior version. Unaffected.

**Edge cases**
- Prior version exists but the current locale only exists on a much older
  version: `$existing` points to whichever row has the highest `id` for
  this `(app, locale)`. If that row's `version_id` != current, the diff is
  still valid (and desired).
- Prior version exists but this locale is brand-new: `$existing = null` →
  no row written here. `locale_added` is handled separately.

**Regression risk: low.** The behaviour change strictly silences writes
that are currently noise for the observed single-version case.

## Files touched

- `server/app/Services/AppSyncer.php` — widen the guard at ~line 259.

## One-off cleanup of existing noise (optional)

After the code fix, current noise rows remain in the table. Two options:

**A) Leave them.** Historical record; no new noise appears. Users may still
see the 8 rows in the Change Monitor UI.

**B) Tidy up with a single artisan command or raw SQL.** Delete change
rows whose version has no earlier version for the same app (i.e. the app's
only or first version):

```sql
DELETE c FROM app_store_listing_changes c
JOIN app_versions v ON v.id = c.version_id
WHERE NOT EXISTS (
    SELECT 1 FROM app_versions v2
    WHERE v2.app_id = v.app_id
      AND v2.id < v.id
);
```

Currently this touches only app 34860 (8 rows) in prod.

## Test plan

1. **Unit: same-version update stays silent.** Seed an app with a version,
   call `processListing` twice with differing payloads for the same locale;
   assert `app_store_listing_changes` is empty.
2. **Unit: cross-version update logs change.** Seed two versions; write a
   listing for v1, then call `processListing` for v2 with a different
   payload; assert one change row with matching old/new values.
3. **Unit: first-ever listing writes nothing.** No `$existing` → no change
   row regardless of payload.
4. **Manual regression:** re-run `appstorecat:apps:sync` against a single
   tracked iOS app with one version (e.g. 34860 after cleanup). Confirm
   `app_store_listing_changes` stays at zero rows for that app after the
   sync.

## Deploy sequence

1. Fix + tests in one PR.
2. Merge, bump patch (1.1.3), tag release.
3. Dokploy picks up the tag and redeploys.
4. Optional: run the cleanup SQL from option B once on prod.

## Rollback

Revert the PR. No schema migration, no data mutation beyond the optional
one-off `DELETE` (which is non-recoverable unless the pre-delete row set
is snapshotted — take a dump before running the cleanup).
