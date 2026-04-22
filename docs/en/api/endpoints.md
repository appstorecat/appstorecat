# API Endpoints

All API endpoints start with the `/api/v1` prefix and require Sanctum token authentication (except authentication endpoints).

**Base URL:** `http://localhost:7460/api/v1`

## Authentication

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register a new user |
| POST | `/auth/login` | Log in and get a token |

### Protected Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/logout` | Log out (revoke token) |
| GET | `/auth/me` | Get the authenticated user |

## Account

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/account/profile` | Get user profile |
| PATCH | `/account/profile` | Update profile (name) |
| DELETE | `/account/profile` | Delete the account |
| PUT | `/account/password` | Update password |

## Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Summary statistics (total apps, versions, changes) |

## Apps

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/apps` | List tracked apps (`?platform=ios\|android`) |
| POST | `/apps` | Track a previously discovered app. `platform+external_id` must exist in the DB (discover first via search/chart); otherwise 422 |
| GET | `/apps/search` | Search for apps in the stores (`?term=X&platform=ios&country_code=us`) |
| GET | `/apps/{platform}/{externalId}` | Get app details. The response includes `unavailable_countries: string[]` (derived from `app_metrics.is_available = false`) |
| GET | `/apps/{platform}/{externalId}/listing` | Get store listing (`?country_code=us&locale=en-US`). The `AppAvailableCountry` rule returns 422 if the app is not available in the selected country |
| GET | `/apps/{platform}/{externalId}/rankings` | App chart rankings for the selected day (`?date=YYYY-MM-DD`) |
| GET | `/apps/{platform}/{externalId}/sync-status` | Returns the `sync_statuses` record (status, current_step, progress, failed_items) |
| POST | `/apps/{platform}/{externalId}/sync` | Dispatches a refresh to the platform-specific on-demand queue |
| POST | `/apps/{platform}/{externalId}/track` | Track an app |
| DELETE | `/apps/{platform}/{externalId}/track` | Untrack an app |

**Route constraints:** `platform` must be `ios` or `android`, and `externalId` matches `[a-zA-Z0-9._]+`.

> Discovery via direct URL is disabled by default (`DISCOVER_{IOS,ANDROID}_ON_DIRECT_VISIT=false`). A `show`/`listing` call for a `platform+external_id` that does not exist in the DB returns 404 — users must first register it through search/chart.

## Competitors

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/apps/{platform}/{externalId}/competitors` | List an app's competitors |
| POST | `/apps/{platform}/{externalId}/competitors` | Add a competitor |
| DELETE | `/apps/{platform}/{externalId}/competitors/{id}` | Remove a competitor |
| GET | `/competitors` | List all competitor apps |

## Keywords

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/apps/{platform}/{externalId}/keywords` | Keyword density (`?locale=en-US&ngram=2`) — computed on demand from the current listing |
| GET | `/apps/{platform}/{externalId}/keywords/compare` | Compare keywords (`?app_ids=1,2,3&locale=en-US`) |

## Changes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/changes/apps` | Store listing changes for tracked apps (`?field=title&app_id=123&page=2`) |
| GET | `/changes/competitors` | Store listing changes for competitor apps (same query params) |

Both endpoints return a paginated envelope (`PaginatedChangeResponse`: `data`, `links`, `meta`) plus `meta_ext.has_scope_apps: boolean` so the UI can distinguish "no results" from "user has nothing tracked yet". Optional query params: `field`, `app_id` (integer, filter to a single app from the user's scope), `page` (integer).

## Rankings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/charts` | Chart rankings (`?platform=ios&collection=top_free&country_code=us&category_id=X`) |

## Explorer

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/explorer/screenshots` | Browse screenshots (`?platform=ios&category_id=X&search=term&per_page=12`) |
| GET | `/explorer/icons` | Browse icons (`?platform=android&search=term&per_page=12`) |

## Countries and Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/countries` | List active countries. The internal `zz` "Global" sentinel is filtered out of the response |
| GET | `/store-categories` | List store categories (`?platform=ios&type=app`) |

## Publishers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/publishers/search` | Search publishers (`?term=X&platform=ios&country_code=us`) |
| GET | `/publishers` | List publishers from tracked apps |
| GET | `/publishers/{platform}/{externalId}` | Publisher details. 404 for unknown publisher |
| GET | `/publishers/{platform}/{externalId}/store-apps` | Publisher's store apps. 404 for unknown publisher |
| POST | `/publishers/{platform}/{externalId}/import` | Import a publisher's apps. Each `external_ids[*]` must already exist in the DB; otherwise 422 |

**Publisher route constraints:** `externalId` matches `[a-zA-Z0-9._%+ -]+` (allows spaces and plus signs).

## Rate Limiting

| Scope | Limit |
|-------|-------|
| Authentication endpoints (public) | 5 requests per minute |
| All other endpoints (local) | 500 requests per minute |
| All other endpoints (production) | 60 requests per minute |

## Error Responses

All errors follow this format:

```json
{
  "message": "Error description"
}
```

Common HTTP status codes: `401` (unauthenticated), `403` (unauthorized), `404` (not found — e.g. unknown app/publisher), `422` (validation error — e.g. `AppAvailableCountry` failure or a `platform+external_id` that has not yet been discovered), `429` (rate limit exceeded).
