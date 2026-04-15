# Quick Start

This guide walks you through using AppStoreCat after installation.

## 1. Create an Account

Open http://localhost:7461 and register a new account. This creates a Sanctum API token for authentication.

## 2. Discover Apps

### Via Trending Charts

Navigate to **Discovery > Trending** to see top free, top paid, and top grossing apps for both iOS and Android. Apps from charts are automatically discovered and added to the database.

### Via Search

Navigate to **Discovery > Apps** and search for any app by name. Results come directly from the App Store and Google Play. Click on any app to view its details — this automatically discovers the app.

### Via Publishers

Navigate to **Discovery > Publishers** and search for a publisher. View their apps and import them all at once.

## 3. Track an App

When viewing an app's detail page, click the **Track** button. Tracked apps are synced more frequently (every 24 hours by default) and appear in your app list.

## 4. Explore App Data

Once an app is synced, you can explore:

- **Store Listing** — Title, description, screenshots, icon for each language
- **Versions** — Version history with release dates and what's new
- **Reviews** — User reviews with rating filters
- **Keywords** — Keyword density analysis with n-gram support
- **Competitors** — Add competitor apps for comparison
- **Changes** — Track store listing changes over time

## 5. Background Sync

AppStoreCat automatically syncs data in the background using queue workers:

- **Tracked apps** sync every 24 hours
- **Discovered apps** sync every 72 hours
- **Charts** sync daily
- **Reviews** sync with each app sync

Check `make logs-backend` to see sync activity.

## API Access

All features are also available via the REST API at `http://localhost:7460/api/v1/`. See the [API documentation](../api/endpoints.md) for the full endpoint reference.

Swagger UI is available at http://localhost:7460/api/documentation when `L5_SWAGGER_GENERATE_ALWAYS=true`.

## Next Steps

- [Configuration](./configuration.md) — Customize sync intervals, throttle rates, and discovery sources
- [Architecture Overview](../architecture/overview.md) — Understand how the system works
- [Feature Documentation](../features/) — Detailed guides for each feature
