# Trending Charts

Track top apps across App Store and Google Play with daily chart snapshots.

![Trending Charts](../../screenshots/trending-charts.jpeg)

## Overview

AppStoreCat collects daily trending chart data for both platforms, storing historical rankings that let you track how apps move through the charts over time.

## How It Works

1. The Laravel scheduler dispatches `SyncChartSnapshotJob` for each active country
2. Jobs run on `charts-ios` and `charts-android` queues independently
3. Each job fetches a chart (e.g., Top Free iOS US) from the scraper
4. Rankings are stored as `ChartSnapshot` + `ChartEntry` records
5. Apps found in charts are automatically discovered in the system

## Chart Types

| Collection | Description |
|------------|-------------|
| `top_free` | Most downloaded free apps |
| `top_paid` | Most downloaded paid apps |
| `top_grossing` | Highest revenue apps |

Charts can be filtered by:
- **Platform:** iOS or Android
- **Country:** Any active country
- **Category:** Store categories (Games, Business, etc.) or overall

## Chart Depth

- **iOS:** Up to 200 apps per chart
- **Android:** Up to 100 apps per chart

## API

```
GET /api/v1/charts?platform=ios&collection=top_free&country=us&category_id=6014
```

Returns the latest chart snapshot with entries, including previous rank data for position change indicators.

## Frontend

Navigate to **Discovery > Trending** to browse charts. The UI shows:
- Platform and collection switchers
- Country and category filters
- Rank position with change indicators (up/down/new)
- App details with quick access to detail pages

## Technical Details

- **Tables:** `trending_charts`, `trending_chart_entries`
- **Job:** `SyncChartSnapshotJob` (throttled: iOS 24/min, Android 37/min)
- **Queues:** `charts-ios`, `charts-android`
- **Snapshot frequency:** Daily (one snapshot per platform/collection/country/category per day)
- **Config:** `CHARTS_IOS_DAILY_SYNC_ENABLED`, `CHARTS_ANDROID_DAILY_SYNC_ENABLED`
