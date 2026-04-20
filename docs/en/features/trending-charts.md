# Trending Charts

Track the top apps across the App Store and Google Play with daily chart snapshots.

![Trending Charts](../../screenshots/trending-charts.jpeg)

## Overview

AppStoreCat collects daily trending chart data for both platforms and stores historical rankings so you can see how apps move through the charts over time.

## How It Works

1. The Laravel scheduler dispatches a `SyncChartSnapshotJob` for every active country
2. Jobs run independently on the `charts-ios` and `charts-android` queues
3. Each job fetches one chart from the scraper (e.g. US iOS Top Free)
4. Rankings are stored as `ChartSnapshot` + `ChartEntry` records
5. Apps that appear in charts are automatically discovered in the system

## Chart Types

| Collection | Description |
|------------|-------------|
| `top_free` | Top free apps |
| `top_paid` | Top paid apps |
| `top_grossing` | Top grossing apps |

Charts can be filtered by:
- **Platform:** iOS or Android
- **Country:** any active country
- **Category:** store categories (Games, Business, etc.) or overall

## Chart Depth

- **iOS:** up to 200 apps per chart
- **Android:** up to 100 apps per chart

## API

```
GET /api/v1/charts?platform=ios&collection=top_free&country_code=US&category_id=6014
```

> The `country_code` query parameter maps to the `countries.code` FK. The `/countries` endpoint filters the internal `zz` Global sentinel from its responses.

Returns the latest chart snapshot along with the previous ranking data for position change indicators.

## UI

Go to **Discovery > Trending** to browse charts. The UI shows:
- Platform and collection switchers
- Country and category filters
- Rank position with change indicators (up/down/new)
- App info with quick access to detail pages

## Technical Details

- **Tables:** `trending_charts` (the `country_code` column references the `countries.code` FK), `trending_chart_entries`
- **Job:** `SyncChartSnapshotJob` (throttled: iOS 24/min, Android 37/min)
- **Queues:** `charts-ios`, `charts-android` (platform-separated)
- **Snapshot frequency:** daily (one snapshot per platform/collection/country/category per day)
- **Configuration:** `CHARTS_IOS_DAILY_SYNC_ENABLED`, `CHARTS_ANDROID_DAILY_SYNC_ENABLED`
