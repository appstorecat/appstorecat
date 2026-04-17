# API Endpoints

All API endpoints are prefixed with `/api/v1` and require authentication via Sanctum token (except auth endpoints).

**Base URL:** `http://localhost:7460/api/v1`

## Authentication

### Public Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new user |
| POST | `/auth/login` | Login and receive token |

### Protected Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/logout` | Logout (revoke token) |
| GET | `/auth/me` | Get authenticated user |

## Account

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/account/profile` | Get user profile |
| PATCH | `/account/profile` | Update profile (name) |
| DELETE | `/account/profile` | Delete account |
| PUT | `/account/password` | Update password |

## Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Summary stats (total apps, reviews, versions, changes) |

## Apps

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/apps` | List tracked apps (`?platform=ios\|android`) |
| POST | `/apps` | Register and track a new app |
| GET | `/apps/search` | Search apps in stores (`?term=X&platform=ios&country=us`) |
| GET | `/apps/{platform}/{externalId}` | Get app details |
| GET | `/apps/{platform}/{externalId}/listing` | Get store listing (`?country=us&language=en-US`) |
| GET | `/apps/{platform}/{externalId}/rankings` | Chart rankings for the app on a given day (`?date=YYYY-MM-DD`) |
| POST | `/apps/{platform}/{externalId}/track` | Track an app |
| DELETE | `/apps/{platform}/{externalId}/track` | Untrack an app |

**Route constraints:** `platform` must be `ios` or `android`, `externalId` matches `[a-zA-Z0-9._]+`

## Competitors

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/apps/{platform}/{externalId}/competitors` | List competitors for an app |
| POST | `/apps/{platform}/{externalId}/competitors` | Add competitor |
| DELETE | `/apps/{platform}/{externalId}/competitors/{id}` | Remove competitor |
| GET | `/competitors` | List all competitor apps |

## Keywords

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/apps/{platform}/{externalId}/keywords` | Keyword density (`?language=en-US&ngram=2&version_id=X`) |
| GET | `/apps/{platform}/{externalId}/keywords/compare` | Compare keywords (`?app_ids=1,2,3&language=en`) |

## Reviews

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/apps/{platform}/{externalId}/reviews` | List reviews (`?country_code=US&rating=5&sort=latest&per_page=25`) |
| GET | `/apps/{platform}/{externalId}/reviews/summary` | Rating summary and distribution |

## Changes

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/changes/apps` | Store listing changes for tracked apps (`?field=title`) |
| GET | `/changes/competitors` | Store listing changes for competitor apps |

## Charts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/charts` | Chart rankings (`?platform=ios&collection=top_free&country=us&category_id=X`) |

## Explorer

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/explorer/screenshots` | Browse screenshots (`?platform=ios&category_id=X&search=term&per_page=12`) |
| GET | `/explorer/icons` | Browse icons (`?platform=android&search=term&per_page=12`) |

## Countries & Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/countries` | List active countries |
| GET | `/store-categories` | List store categories (`?platform=ios&type=app`) |

## Publishers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/publishers/search` | Search publishers (`?term=X&platform=ios&country=us`) |
| GET | `/publishers` | List publishers from tracked apps |
| GET | `/publishers/{platform}/{externalId}` | Publisher details |
| GET | `/publishers/{platform}/{externalId}/store-apps` | Publisher's store apps |
| POST | `/publishers/{platform}/{externalId}/import` | Import all publisher apps |

**Publisher route constraints:** `externalId` matches `[a-zA-Z0-9._%+ -]+` (allows spaces and plus signs)

## Rate Limiting

| Scope | Limit |
|-------|-------|
| Auth endpoints (public) | 5 requests/minute |
| All other endpoints (local) | 500 requests/minute |
| All other endpoints (production) | 60 requests/minute |

## Error Responses

All errors follow the format:

```json
{
  "message": "Error description"
}
```

Common HTTP status codes: `401` (unauthenticated), `403` (forbidden), `404` (not found), `422` (validation error), `429` (rate limited).
