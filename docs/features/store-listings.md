# Store Listings

View and track app store listings across multiple languages.

![Store Listing](../../screenshots/store-listing.jpeg)

## Overview

AppStoreCat syncs and stores the complete store listing for each app, including localized versions. Listings are tracked per language, allowing you to monitor how apps present themselves in different markets.

## Listing Data

Each store listing record contains:

| Field | Description |
|-------|-------------|
| **Title** | App name in this language |
| **Subtitle** | Short tagline (iOS only) |
| **Description** | Full app description |
| **What's New** | Release notes |
| **Screenshots** | Array of screenshot URLs |
| **Icon** | App icon URL |
| **Video** | Preview video URL |
| **Price** | App price in local currency |
| **Currency** | Currency code |

## Multi-Language Support

Listings are unique per `(app_id, language)`. When an app supports multiple locales, each locale gets its own listing record. The app's `supported_locales` field lists all available locale codes.

## API

```
GET /api/v1/apps/{platform}/{externalId}/listing?country=us&language=en-US
```

Returns the store listing for a specific country and language.

## Change Tracking

Every time a listing is synced, its content is checksummed. When the checksum changes, each field is compared individually and changes are recorded in `app_store_listing_changes`. See [Change Detection](./change-detection.md) for details.

## Frontend

The **Listing** tab on the app detail page shows:
- Title, subtitle, and description
- Screenshots gallery
- Language selector for switching between locales
- Version information and release notes

## Technical Details

- **Model:** `StoreListing`
- **Table:** `app_store_listings`
- **Unique constraint:** `(app_id, language)`
- **Sync step:** `AppSyncer::syncListing()`
- **Change detection:** Checksum-based comparison on each sync
