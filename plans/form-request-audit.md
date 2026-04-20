# Plan: Form Request audit — stop using inline `$request->validate()`

**Status:** Backlog — deferred
**Convention:** `.arc/02-api/form-requests.md` (project rule — every endpoint with input must use a dedicated `FormRequest` subclass, not inline `$request->validate([...])`).

## Context

Inline `$request->validate([...])` keeps rules buried inside controller
methods. The project convention is: every endpoint with user input
declares a `FormRequest` class under `server/app/Http/Requests/Api/...`,
with `#[OA\Schema]` attributes so the rules are visible in Swagger and
reusable across clients.

A spot-check in `AppController::index` revealed inline validation; the
same pattern likely exists on other endpoints added during rapid
iteration.

## Goal

Audit every controller under `server/app/Http/Controllers/Api/V1/` and
replace inline `$request->validate([...])` with a dedicated
`FormRequest` subclass. Each request class must carry an `#[OA\Schema]`
attribute describing its payload so the Swagger spec picks it up.

## Scope — files to audit

Run `grep -rn "\\$request->validate\\(" server/app/Http/Controllers/`
to seed the list. For each hit:

1. Extract the validation array into a new `FormRequest` class.
2. Add an `#[OA\Schema(...)]` attribute mirroring the fields.
3. Type-hint the controller action parameter with the new request.
4. Replace `->input()` / `->query()` calls with `->validated('field')`
   where it improves safety.

Known or suspected offenders (confirm during audit):

- `AppController::index` — fixed as part of this plan's trigger.
- `AppController::listing` — currently uses inline validation
  including the `AppAvailableCountry` rule. Move to a
  `ListingRequest` class that owns both rules.
- `AppController::show`, `sync`, `syncStatus`, `track`, `untrack` —
  path-only endpoints, no body input; leave as is.
- `AppSearchController::__invoke` — already uses `AppSearchRequest`.
- `ChartController::index` — inline validation for
  `platform / collection / country_code / category_id`. Move to a
  `ChartIndexRequest`.
- `ExplorerController::screenshots` / `icons` — inline validation
  with platform / category_id / search / per_page. Two request
  classes: `ExplorerScreenshotsRequest`, `ExplorerIconsRequest`.
- `DashboardController::__invoke` — no input; leave as is.
- `CompetitorController::store` — already uses
  `StoreCompetitorRequest`. Verify no drift.
- `KeywordController::index` / `compare` — already use
  `KeywordIndexRequest` / `KeywordCompareRequest`. Verify.
- `PublisherController::search` — already uses
  `PublisherSearchRequest`.
- `PublisherController::show` / `storeApps` — path + optional query
  params; audit to decide if a request class is warranted (if only
  one optional param, may stay controller-inline; apply the rule
  consistently).
- `PublisherController::import` — already uses
  `PublisherImportRequest`.
- `CountryController::__invoke` — no input; leave as is.
- `ChangeMonitorController::apps` / `competitors` — check for
  `field_changed` filter validation.
- `Account/*` controllers — check profile update, password update,
  register, login rules.

## Non-goals

- Do not change response shapes or business logic.
- Do not change validation semantics — one-to-one move.
- Do not introduce new rules unless auditing reveals a missing one.
- Do not touch the scraper or web services.

## Deliverables

- One `FormRequest` class per endpoint that needs input validation.
- Each class has `#[OA\Schema]` and `rules()`.
- `make swagger && make api-generate` refresh the OpenAPI spec and
  Orval client. Web types should reflect the new `*Request` schemas.
- No `->validate(...)` calls in any `Api/V1/*` controller.
- `make pint` passes.

## Verification

- `grep -rn "->validate(" server/app/Http/Controllers/Api/V1/`
  returns zero results.
- `docs/en/api/endpoints.md` parameter lists still accurate.
- A spot-check of Swagger UI shows the new schemas.

## Rollout

- Single PR. No feature flags.
- `make queue-restart` not required (no queue-touching code changes).
- `make cache-clear` to flush config cache after regenerating swagger.

## Open decisions

1. Request classes for endpoints with **only one** optional query
   param (`platform` alone, `search` alone) — worth the boilerplate,
   or leave inline with a comment exempting them? Proposed: keep
   rule absolute (always `FormRequest`) so the swagger spec stays
   consistent.
2. Shared base request for common `platform` / `country_code` /
   `per_page` fields? Proposed: skip; Laravel's
   `FormRequest::prepareForValidation` doesn't compose cleanly with
   trait-level schema attributes.
