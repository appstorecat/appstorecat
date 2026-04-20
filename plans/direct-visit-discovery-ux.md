# Plan: Direct-Visit Discovery UX

**Status:** Backlog — deferred
**Related bug:** `docs/en/bugs/report_20apr.md` — Bug 3
**Config flip:** `DISCOVER_{IOS,ANDROID}_ON_DIRECT_VISIT=false` applied
separately (this plan only covers the UX follow-up).

## Context

`DiscoverSource::DirectVisit` was the fallback path that let the UI
create `apps` rows whenever a user opened `/apps/{platform}/{externalId}`
for an app that was not yet in the DB. `App::discover` was invoked with
empty metadata and a hard-coded `origin_country_code='us'`, which
cascaded into bug #3 in the 20 Apr report — TR-only apps (Mavi) got
marked unavailable because the identity probe only hit the US
storefront.

We've disabled DirectVisit at the config level. With the flag off,
`App::discover` now returns `null` for that source, so
`AppController::show` responds `404` for unknown external IDs. That is
safer for data quality but breaks the "paste a store URL → see it in
the dashboard" flow.

## Goal

Restore the bookmark / URL-paste ergonomics without re-enabling
DirectVisit:

1. The 404 from `/apps/{platform}/{externalId}` becomes a signal for
   the UI to offer an explicit registration step.
2. Registration goes through `POST /apps` with a user-picked
   `country_code`, so `origin_country_code` reflects reality rather
   than the US default.

## Out of scope

- Reopening DirectVisit as a fallback.
- Changing the public API surface beyond existing endpoints (`POST /apps`,
  `GET /apps/{platform}/{externalId}`).
- Identity multi-country fallback improvements — handled by the bug #3
  fix in the sync pipeline; this plan assumes that fix has shipped
  (or will, independently).

## User flow

1. User visits `/apps/ios/927394856` (e.g. from an external link).
2. Web app fetches `GET /apps/ios/927394856` → `404`.
3. Instead of a blanket "not found" page, the UI renders a
   **"Register this app"** dialog:
   - Read-only: platform, external ID (taken from the URL).
   - Required field: `country_code` selector (default to the browser
     locale's region, falling back to `us`).
   - Optional field: display name hint (for confirmation only; the
     real name lands after identity sync).
   - CTA: **Register and track**.
4. On submit: `POST /apps` with `{ platform, external_id, country_code }`.
5. On success: redirect the user back to
   `/apps/{platform}/{externalId}` (the same URL they originally hit).
   The detail page now shows the app with a sync-in-progress banner.

## Implementation notes

### Backend

- Verify `POST /apps` (`V1\App\AppController::store`) already accepts
  `country_code` and writes it to `origin_country_code`. If not, widen
  the Form Request to accept it and pass it through `App::discover`
  using `DiscoverSource::Register` (that enum case already exists and
  is already enabled in `appstorecat.php`).
- Return the created `AppResource` so the UI can immediately navigate
  without refetching.
- Ensure the follow-up sync job dispatch runs on the `sync-on-demand-{platform}`
  queue so the new row doesn't wait behind the cron backlog.

### Frontend

- Intercept `404` responses from `GET /apps/{platform}/{externalId}`
  in `pages/apps/Show.tsx` (or the shared route loader).
- Replace the existing "not found" render with a `RegisterAppDialog`
  component:
  - Props: `platform`, `externalId`, `defaultCountryCode`.
  - Uses `<CountrySelect>` already in the codebase.
  - Submits through the Orval-generated `registerApp` mutation (or
    `axios.post('/apps', …)` if Orval hasn't caught it yet).
- After a successful POST, invalidate the apps query cache and navigate
  back to the same path so the detail page re-renders with real data.
- Edge: if `POST /apps` returns a validation error for unknown
  `country_code`, surface it inline on the form.

## Testing checklist

- Open a fresh URL with a platform/external ID that isn't in the DB
  → dialog appears.
- Pick `tr` as the country, submit → DB row has
  `origin_country_code='tr'` and `discovered_from=Register`.
- Sync job fires, sync-status polls to `completed`.
- Mavi (`927394856`) regression: registering with `tr` produces a live,
  `is_available=true` row.
- Registering with an invalid country (e.g. `zz` when the user picks it
  by accident) returns a proper validation error.

## Rollout

- Config stays `false` for both platforms.
- Ship frontend + `POST /apps` widening together; no feature flag.
- Update `docs/en/bugs/report_20apr.md` to mark bug #3's UX side
  resolved (the data side is covered by the sync-pipeline fix).

## Decisions still open

1. Default country in the dialog — browser locale vs user's last
   selection vs always `us`? Proposed: browser locale → `us` fallback.
2. Should the same register dialog surface on **every** manual-entry
   surface (e.g. a future "paste a store URL" form), or only as the 404
   recovery path? Proposed: keep it 404-only for now; add a dedicated
   form in a later iteration if usage warrants.
