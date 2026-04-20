# App Rankings

See where a tracked app sits on a given day across every country, collection, and category chart.

## Overview

Where [Trending Charts](trending-charts.md) lets you browse a single chart (e.g. US iOS Top Free), the Rankings view flips the axis: pick an app and a date and see every chart position it holds that day around the world.

Positions come from the same `ChartSnapshot` + `ChartEntry` data collected by the daily chart sync; every country/collection/category enabled for sync appears here automatically.

## How It Works

1. Open the **Rankings** tab on the app detail page
2. Pick a **ranking type** (Any / Top Free / Top Paid / Top Grossing)
3. Use the **date picker** (with prev/next arrows) to move between days
4. In the pivot table, rows are countries and columns are `(collection, category)` pairs
5. Each cell shows the app's rank along with a change badge relative to the closest previous snapshot

## Change Indicators

| Status | Meaning |
|--------|---------|
| `new` | The app is appearing on this chart for the first time |
| `up` | Rank improved compared to the previous snapshot |
| `down` | Rank dropped compared to the previous snapshot |
| `same` | Rank unchanged |

Cells with no entry for that day show `N/A`.

## API

```
GET /api/v1/apps/{platform}/{externalId}/rankings?date=2026-04-17
```

The `date` query parameter is optional (defaults to today) and must use `Y-m-d` format. Response items include:

```
country_code, collection, category, rank, previous_rank, rank_change, status, snapshot_date
```

`previous_rank` is taken from the closest earlier snapshot for the same `(platform, collection, country_code, category_id)` tuple.

## UI

Implemented as a tab inside the app detail page (`web/src/pages/apps/Show.tsx`); the component lives in `web/src/components/tabs/RankingsTab.tsx`. Rows are sorted by the app's best rank in each country.

## Technical Details

- **Source data:** `trending_charts`, `trending_chart_entries`
- **Controller:** `AppRankingController@index`
- **Resource:** `AppRankingResource`
- **Dependency:** The daily chart sync must be active for the relevant countries
