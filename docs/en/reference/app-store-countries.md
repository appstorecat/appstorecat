# App Store Countries

AppStoreCat supports pulling data from multiple countries in both the iOS and Android stores. Country support is managed via the `countries` table with per-platform activation.

## Country Configuration

Each country has independent activation for iOS and Android:

```
countries table:
├── code (ISO 3166-1 alpha-2, e.g., "us", "tr", "de")
├── name
├── emoji (flag)
├── is_active_ios (boolean)
├── is_active_android (boolean)
├── priority (display ordering)
├── ios_languages (JSON array of supported locale codes)
├── ios_cross_localizable (JSON array of cross-localizable locale codes)
└── android_languages (JSON array of supported locale codes)
```

## Using Countries

### API

```
GET /api/v1/countries
```

Returns the list of active countries. The response is filtered by the current context (iOS or Android).

### In Configuration

The default country for operations is set in `config/appstorecat.php`:

```php
'default_country' => 'us'
```

### In Sync Operations

When syncing an app, the server:

1. Tries the `us` locale first
2. Falls back to the app's `origin_country_code` if the US locale fails
3. Uses the country's `ios_languages` or `android_languages` values for locale-specific operations

For Android metrics, the ISO 3166 `zz` sentinel code is used as "Global" (Google Play returns non-country-specific global values there).

## Activating Countries

Countries are activated per platform via the `is_active_ios` and `is_active_android` flags. Only active countries appear in the API response and are used for:

- Store search
- Chart sync
- Listing fetch
- Metric aggregation (`app_metrics`)

## Language Mappings

Each country has a JSON array of supported languages per platform:

- **ios_languages**: locale codes supported in the App Store for this country (e.g., `["en-US", "es-MX"]`)
- **android_languages**: locale codes supported in Google Play for this country (e.g., `["en-US", "es-419"]`)
- **ios_cross_localizable**: iOS locales that can be used across countries (cross-localization)

In store listings, the locale is stored in the `app_store_listings.locale` column.

## Notes

- iOS and Android use different locale-code formats in some cases
- Android metrics use the `zz` "Global" sentinel code for non-country-specific values
- Chart data is always country-specific for both platforms (`trending_charts.country_code`)
- `app_metrics.country_code` is `CHAR(2)` and has an FK relationship to `countries.code`
- The App Store has many more country/locale combinations than Google Play
- `apps.is_available` means "reachable in at least one store"; per-country availability lives in `app_metrics.is_available`
