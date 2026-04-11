# Change Detection

Monitor store listing changes over time for tracked and competitor apps.

![Change Detection](../../screenshots/change-detection.jpeg)

## Overview

AppStoreCat detects changes in app store listings on every sync cycle. When a field changes (title, description, screenshots, etc.), the old and new values are recorded, creating a timeline of how apps evolve their store presence.

## Tracked Fields

| Field | Description |
|-------|-------------|
| `title` | App title changed |
| `subtitle` | App subtitle changed (iOS) |
| `description` | App description changed |
| `whats_new` | Release notes changed |
| `screenshots` | Screenshot set changed |
| `locale_removed` | A supported locale was removed |

## How It Works

1. On each sync, the listing content is hashed into a `checksum`
2. If the checksum matches the previous sync, no change occurred — skip
3. If the checksum differs, each field is compared individually
4. For each changed field, a `StoreListingChange` record is created with:
   - The field name
   - Old value
   - New value
   - Detection timestamp
   - Language/locale

## Locale Change Detection

Beyond individual field changes, AppStoreCat also tracks locale-level changes:

- **New locale added:** When an app starts supporting a new language
- **Locale removed:** When an app drops support for a language

These are detected by comparing the `supported_locales` array between syncs.

## API

### Tracked App Changes

```
GET /api/v1/changes/apps?field=title
```

Returns changes for all tracked apps. Filterable by field type.

### Competitor Changes

```
GET /api/v1/changes/competitors?field=description
```

Returns changes for all competitor apps.

## Frontend

Navigate to **Changes > Apps** or **Changes > Competitors** to see the change timeline. Each change shows:
- App name and icon
- Field that changed
- Old and new values with diff highlighting
- Locale and timestamp

## Technical Details

- **Model:** `StoreListingChange`
- **Table:** `app_store_listing_changes`
- **Indexes:** `(app_id, detected_at)`, `(version_id)`
- **Sync step:** `AppSyncer::syncListing()` (field changes), `AppSyncer::detectLocaleChanges()` (locale changes)
- **Controller:** `ChangeMonitorController`
- **Detection method:** Checksum-based (SHA-256 of combined listing content)
