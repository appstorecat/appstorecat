# App Store Countries

AppStoreCat supports fetching data from multiple countries across both iOS and Android stores. Country support is managed through the `countries` table with per-platform activation.

## Country Configuration

Each country has independent activation for iOS and Android:

```
countries table:
├── code (ISO 3166-1 alpha-2, e.g., "us", "tr", "de")
├── name
├── emoji (flag)
├── is_active_ios (boolean)
├── is_active_android (boolean)
├── priority (display order)
├── ios_languages (JSON array of supported locale codes)
├── ios_cross_localizable (JSON array of cross-localizable locales)
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
2. Falls back to the app's `origin_country` if the US locale fails
3. Uses the country's `ios_languages` or `android_languages` for locale-specific operations

## Activating Countries

Countries are activated per platform via the `is_active_ios` and `is_active_android` flags. Only active countries appear in the API response and are used for:

- Store search
- Chart sync
- Listing fetch
- Review fetch

## Language Mappings

Each country has a JSON array of supported languages per platform:

- **ios_languages**: Locale codes supported on the App Store for this country (e.g., `["en-US", "es-MX"]`)
- **android_languages**: Locale codes supported on Google Play for this country (e.g., `["en-US", "es-419"]`)
- **ios_cross_localizable**: iOS locales that can be used across countries (cross-localization)

## Notes

- iOS and Android use different locale code formats in some cases
- Android reviews are global (not country-specific), so `country_code` is nullable for Android reviews
- Chart data is always country-specific for both platforms
- The App Store has significantly more country/locale combinations than Google Play
