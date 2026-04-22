# Change Detection

Track store listing changes over time for both tracked and competitor apps.

![Change Detection](../../../screenshots/change-detection.jpeg)

## Overview

AppStoreCat detects changes in app store listings on every sync cycle. When a field changes (title, description, screenshots, etc.), the old and new values are recorded, producing a timeline of how apps evolve their store presence.

## Tracked Fields

| Field | Description |
|-------|-------------|
| `title` | App title changed |
| `subtitle` | App subtitle changed (iOS) |
| `description` | App description changed |
| `whats_new` | Release notes changed |
| `screenshots` | Screenshot set changed |
| `promotional_text` | Promotional text changed (iOS only; always null on Android) |
| `locale_added` | A new supported locale was added |
| `locale_removed` | A supported locale was removed |

## How It Works

1. On every sync, the listing content is hashed into a `checksum`
2. If the checksum matches the previous sync, nothing has changed — it is skipped
3. If the checksum differs, each field is compared individually
4. For each changed field, a `StoreListingChange` record is created with:
   - Field name (`field_changed`)
   - Old value
   - New value
   - Detection timestamp
   - Locale (`locale` column)

## Locale Change Detection

Beyond individual field changes, AppStoreCat also tracks locale-level changes:

- **`locale_added`:** when an app starts supporting a new language
- **`locale_removed`:** when an app drops support for a language

These are detected by comparing the `supported_locales` array between syncs.

## API

### Tracked App Changes

```
GET /api/v1/changes/apps?field=title&app_id=123&page=2
```

Returns changes for all tracked apps. Optional query params: `field` (filter by field type), `app_id` (restrict to a single tracked app the caller owns), `page` (page cursor).

### Competitor Changes

```
GET /api/v1/changes/competitors?field=description&app_id=123&page=2
```

Returns changes for all competitor apps. Same optional query params as `/changes/apps`.

### Response Shape

Both endpoints return a paginated envelope (`PaginatedChangeResponse`) rather than a bare array:

```json
{
  "data": [ /* ChangeResource[] */ ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta":  { "current_page": 1, "last_page": 4, "per_page": 50, "total": 183, "…": "…" },
  "meta_ext": { "has_scope_apps": true }
}
```

`meta_ext.has_scope_apps` lets the UI distinguish "no changes yet — keep watching" from "you have not tracked any apps yet".

## UI

Go to **Changes > Apps** or **Changes > Competitors** to see the change timeline. Each change shows:
- App name and icon
- Changed field
- Old and new values with diff highlighting
- Locale and timestamp

## Technical Details

- **Model:** `StoreListingChange`
- **Table:** `app_store_listing_changes` (the `locale` column holds a BCP-47 code)
- **Indexes:** `(app_id, detected_at)`, `(version_id)`, `(app_id, locale)`, `(field_changed)`
- **Sync step:** `AppSyncer::syncListing()` (field changes), `AppSyncer::detectLocaleChanges()` (locale changes)
- **Controller:** `ChangeMonitorController`
- **Detection method:** Checksum-based (SHA-256 of the combined listing content)
