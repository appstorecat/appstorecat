# Ratings

Track app ratings as daily per-country snapshots.

![Ratings](../../screenshots/ratings-reviews.jpeg)

## Overview

On every sync cycle, AppStoreCat records aggregate rating metrics (average rating, rating count, star distribution) per country.

## Rating Metrics

Daily snapshots of app ratings are stored in the `app_metrics` table, with a separate record for each `(app_id, country_code, date)` tuple:

| Metric | Description |
|--------|-------------|
| **Rating** | Average rating (decimal, e.g. 4.56) |
| **Rating Count** | Total rating count |
| **Rating Breakdown** | Per-star distribution `{1: 100, 2: 50, 3: 200, 4: 500, 5: 1200}` (`rating_breakdown` JSON) |
| **Rating Delta** | Change in rating count compared to the previous day |

`app_metrics.country_code` is a `CHAR(2)` and references the `countries.code` FK. For Android metrics, the `zz` "Global" ISO sentinel is used because the store returns global data; the `/countries` endpoint filters that sentinel out of its responses.

`app_metrics.price` is nullable: `null` means unknown, `0` means confirmed free. The `is_available` flag on each record reflects whether the app is reachable in that country on that day; `apps.is_available` means "reachable in at least one store".

## UI

The **Overview** / **Metrics** view on the app detail page shows a rating summary, the star distribution chart, and the rating-count trend over time.

## Technical Details

- **Model:** `AppMetric`
- **Table:** `app_metrics`
- **Unique constraint:** `(app_id, country_code, date)`
- **Sync step:** `AppSyncer::syncMetrics()` (metrics phase)
