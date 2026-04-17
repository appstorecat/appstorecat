# App Rankings

See where a tracked app sits in the store charts across every country, collection, and category for any given day.

## Overview

While [Trending Charts](trending-charts.md) let you browse a single chart (e.g. Top Free iOS in the US), the Rankings view flips the axis: pick an app, pick a date, and see every chart position that app holds worldwide on that day.

Positions are sourced from the same `ChartSnapshot` + `ChartEntry` data collected by the daily chart sync, so any country/collection/category enabled for sync will appear here automatically.

## How It Works

1. On the app detail page, open the **Rankings** tab
2. Choose a **rank type** (Any / Top Free / Top Paid / Top Grossing)
3. Use the **date picker** (with prev/next arrows) to jump between days
4. The pivot table shows countries as rows and `(collection, category)` tuples as columns
5. Each cell displays the app's rank with a change badge relative to the nearest earlier snapshot

## Change Indicators

| Status | Meaning |
|--------|---------|
| `new` | First time the app appears in this chart |
| `up` | Rank improved vs the previous snapshot |
| `down` | Rank dropped vs the previous snapshot |
| `same` | Rank unchanged |

Cells with no entry for that day show `N/A`.

## API

```
GET /api/v1/apps/{platform}/{externalId}/rankings?date=2026-04-17
```

The `date` query param is optional (defaults to today) and must be `Y-m-d`. Response items include:

```
country, collection, category, rank, previous_rank, rank_change, status, snapshot_date
```

`previous_rank` is pulled from the most recent earlier snapshot for the same `(platform, collection, country, category_id)` tuple.

## Frontend

Implemented as a tab inside the app detail page (`web/src/pages/apps/Show.tsx`), component at `web/src/components/tabs/RankingsTab.tsx`. Rows are sorted by the app's best rank per country.

## Technical Details

- **Source data:** `trending_charts`, `trending_chart_entries`
- **Controller:** `AppRankingController@index`
- **Resource:** `AppRankingResource`
- **Depends on:** Daily chart sync being enabled for the relevant countries
