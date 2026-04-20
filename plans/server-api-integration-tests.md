# Plan: Server API Integration Test Runner

**Status:** Proposed — not started
**Target service:** `server/` (Laravel API)
**Test style:** Blackbox curl + OpenAPI schema validation (no PHPUnit/Pest)

## Goal

When the developer runs `make test-api`, a standalone runner hits every
endpoint exposed by the Laravel server against a live instance, validates
each response body against the Swagger/OpenAPI schema published at
`server/storage/api-docs/api-docs.json`, and reports A→Z coverage.

## Technology choice

- **Bash + curl + jq** — no runtime deps beyond what is already installed.
- **ajv-cli** (Node) — OpenAPI schema validation. Runs via `npx` so no
  global install required.
- Output: colored pass/fail per case, summary totals, non-zero exit on
  any failure.

## Directory layout

```
tests/api/
├── run.sh                  # Main runner — entry point for `make test-api`
├── lib/
│   ├── http.sh             # curl wrapper: auth header, timing, status log
│   ├── assert.sh           # status + schema assertion helpers
│   └── validate.mjs        # ajv wrapper for OpenAPI component schemas
├── fixtures/
│   └── apps.json           # Test apps (see table below)
├── cases/                  # One file per endpoint, ordered by prefix
│   ├── 00_health.sh
│   ├── 01_auth_register.sh
│   ├── 02_auth_login.sh
│   ├── 03_auth_me.sh
│   ├── 10_account_profile.sh
│   ├── 11_account_password.sh
│   ├── 12_account_api_tokens.sh
│   ├── 20_dashboard.sh
│   ├── 30_countries.sh
│   ├── 31_store_categories.sh
│   ├── 40_apps_index.sh
│   ├── 41_apps_search.sh
│   ├── 42_apps_store.sh
│   ├── 43_apps_show.sh
│   ├── 44_apps_listing.sh
│   ├── 45_apps_track.sh
│   ├── 46_apps_sync.sh
│   ├── 47_apps_sync_status.sh
│   ├── 48_apps_competitors.sh
│   ├── 49_apps_keywords.sh
│   ├── 50_apps_rankings.sh
│   ├── 60_competitors_all.sh
│   ├── 61_changes_apps.sh
│   ├── 62_changes_competitors.sh
│   ├── 70_charts.sh
│   ├── 80_explorer_screenshots.sh
│   ├── 81_explorer_icons.sh
│   ├── 90_publishers_index.sh
│   ├── 91_publishers_search.sh
│   ├── 92_publishers_show.sh
│   ├── 93_publishers_store_apps.sh
│   ├── 94_publishers_import.sh
│   ├── 99_apps_untrack.sh
│   └── zz_auth_logout.sh
└── README.md
```

## Test fixtures

| Platform | App              | External ID                        |
|----------|------------------|------------------------------------|
| ios      | MAVİ             | `927394856`                        |
| ios      | ChatGPT          | `6448311069`                       |
| android  | e-Devlet Kapısı  | `tr.gov.turkiye.edevlet.kapisi`    |
| android  | ChatGPT          | `com.openai.chatgpt`               |

## Execution flow (A → Z)

1. **Setup** — health probe, register random user, login, capture token.
2. **Catalog** — `GET /countries`, `GET /store-categories` (public data
   shape).
3. **App registration** — `POST /apps` for all 4 fixtures, capture
   `platform`/`external_id` pairs.
4. **App detail matrix** — for each fixture: `show`, `listing`,
   `competitors`, `keywords`, `rankings`, `sync-status`.
5. **Mutations** — `track` → `sync` → poll `sync-status` until
   `completed` (timeout 300s, 2s interval) → `untrack`.
6. **Cross-resource** — `dashboard`, `changes/apps`,
   `changes/competitors`, `charts`, `explorer/screenshots`,
   `explorer/icons`, publishers (index/search/show/store-apps/import).
7. **Teardown** — revoke API token, logout, delete test account.

## Schema validation approach

- Parse `api-docs.json` once at runner start; emit per-endpoint schema
  fragments into `/tmp/appstorecat-schemas/`.
- Each response passes through
  `npx ajv validate -s <schema> -d <response>`.
- On mismatch: emit endpoint, HTTP status, expected schema path, actual
  JSON, and a diff.

## Makefile hooks

```make
## Run full API integration test (curl + OpenAPI schema validation)
test-api:
	@./tests/api/run.sh

## Run a single case: make test-api-case c=42_apps_store
test-api-case:
	@./tests/api/run.sh --case $(c)
```

## Configuration (env)

```
APPSTORECAT_API_BASE=http://localhost:7460/api/v1
APPSTORECAT_SWAGGER=server/storage/api-docs/api-docs.json
APPSTORECAT_TEST_EMAIL=apitest+<timestamp>@appstorecat.dev
APPSTORECAT_TEST_PASSWORD=Password!234
APPSTORECAT_SYNC_TIMEOUT=300
APPSTORECAT_SYNC_POLL_INTERVAL=2
```

## Output format

```
[PASS] GET  /countries                  → 200 (84ms, schema OK)
[PASS] POST /apps                       → 201 (312ms, schema OK)
[FAIL] GET  /apps/ios/927394856/listing → 200 (241ms, schema MISMATCH)
       missing field: supported_locales
...
Summary: 45 passed, 2 failed, 1 skipped
```

Exit code: `0` on full pass, `1` on any failure.

## Open decisions

1. **Schema validator:** `ajv-cli` via `npx` (proposed) vs Python
   `openapi-spec-validator` + `jsonschema`. Pick before implementation.
2. **Sync wait window:** 300 s timeout with 2 s poll — decided.
3. **Test user lifecycle:** create a new random user per run and delete
   it at teardown — decided.
4. **Android ChatGPT package:** `com.openai.chatgpt` — confirmed live
   against `scraper-android` (OpenAI / Productivity, HTTP 200).

## Out of scope

- Unit tests for individual services / connectors — removed from the
  project (see commit `b6d752b`).
- Scraper-side tests (`scraper-ios`, `scraper-android`) — this plan
  covers the Laravel API only.
- Load / performance testing — use a dedicated tool if needed later.
