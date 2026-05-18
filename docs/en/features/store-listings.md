# Store Listings

View and monitor app store listings in multiple languages.

![Store Listing](../../../screenshots/store-listing.jpeg)

## Overview

AppStoreCat syncs and stores the full store listing for each app, including localized versions. Listings are tracked per locale, so you can see how apps present themselves in different markets.

## Listing Data

Each store listing record includes:

| Field | Description |
|-------|-------------|
| **Title** | App name in this locale |
| **Subtitle** | Short tagline (iOS only) |
| **Promotional Text** | `promotional_text` — iOS only; always `null` on Android |
| **Description** | Full app description |
| **What's New** | Release notes |
| **Screenshots** | Array of screenshot URLs |
| **Icon** | App icon URL (`icon_url`) |
| **Video** | Preview video URL |
| **Price** | App price in local currency |
| **Currency** | Currency code |

## Multi-Locale Support

Listings are unique per `(app_id, locale)`. When an app supports multiple locales, each locale has its own listing record. The app's `supported_locales` field lists all available locale codes.

## API

```
GET /api/v1/apps/{platform}/{externalId}/listing?country_code=US&locale=en-US
```

Returns the store listing for a specific country and locale. `country_code` is validated by the `AppAvailableCountry` rule — if the app is not published in that country, the response is `422`. If the app is not reachable in any storefront, the `unavailable_countries` array in the `AppDetailResource` response lists the missing countries.

## Change Tracking

Every time a listing is synced, its content is checksummed. When the checksum changes, each field is compared individually and changes are recorded in the `app_store_listing_changes` table. See [Change Detection](./change-detection.md) for details.

## UI

The **Listing** tab on the app detail page shows:
- Title, subtitle, and description
- Screenshot gallery
- Language picker to switch between locales
- Version info and release notes

## Technical Details

- **Model:** `StoreListing`
- **Table:** `app_store_listings` (the `locale` column holds a BCP-47 code; `promotional_text` is used for iOS)
- **Unique constraint:** `(app_id, locale)`
- **Sync step:** `AppSyncer::syncListing()` (the pipeline's `listings` phase)
- **Change detection:** checksum-based comparison on every sync (PHP-side; the `app_store_listings_checksum_index` was dropped because no SQL reader uses it)
- **Validation:** `AppAvailableCountry` rule for `country_code`; 422 if the app is not in that country
- **Storage:** `app_store_listings` runs with ROW_FORMAT=COMPRESSED (KEY_BLOCK_SIZE=8); InnoDB page-level zlib compression captures roughly 30-50% of the TEXT payload (description, screenshots JSON, whats_new)

## Field Notes

- **`subtitle`** is stored as `TEXT` (was `VARCHAR(255)`). Google Play's "short description" field is mapped onto `subtitle` and can exceed 255 characters in some locales; older syncs were failing with `Data too long for column 'subtitle' (1406)`. iOS subtitles remain short in practice.
- **`promotional_text`** is iOS-only and always `null` for Android listings.
