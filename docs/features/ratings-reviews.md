# Ratings & Reviews

Monitor app ratings and sync user reviews from both stores.

![Ratings & Reviews](../../screenshots/ratings-reviews.jpeg)

## Overview

AppStoreCat tracks two types of review data: aggregate metrics (rating, rating count, breakdown) and individual user reviews. Both are synced during the regular app sync cycle.

## Rating Metrics

Daily snapshots of app ratings are stored in the `app_metrics` table:

| Metric | Description |
|--------|-------------|
| **Rating** | Average rating (decimal, e.g., 4.56) |
| **Rating Count** | Total number of ratings |
| **Rating Breakdown** | Per-star distribution `{1: 100, 2: 50, 3: 200, 4: 500, 5: 1200}` |
| **Rating Delta** | Change in rating count since the previous day |

## User Reviews

Individual reviews are synced from both stores:

| Field | Description |
|-------|-------------|
| **Author** | Reviewer name |
| **Title** | Review title (iOS only) |
| **Body** | Review text |
| **Rating** | 1-5 stars |
| **Review Date** | When the review was posted |
| **App Version** | App version at time of review |
| **Country** | Country code (iOS only; Android reviews are global) |

## API

### Review List

```
GET /api/v1/apps/{platform}/{externalId}/reviews?country_code=US&rating=5&sort=latest&per_page=25
```

Filters: `country_code`, `rating` (1-5), `sort` (latest, oldest, highest, lowest).

### Review Summary

```
GET /api/v1/apps/{platform}/{externalId}/reviews/summary
```

Returns aggregate stats and rating distribution.

## Frontend

The **Reviews** tab on the app detail page shows:
- Rating summary with star distribution chart
- Filterable review list
- Sort options (latest, oldest, highest, lowest)
- Country filter (iOS)

## Technical Details

- **Models:** `Review`, `AppMetric`
- **Tables:** `app_reviews`, `app_metrics`
- **Unique constraints:** Reviews: `(app_id, external_id)`, Metrics: `(app_id, date)`
- **Sync step:** `AppSyncer::syncMetrics()`, `AppSyncer::syncReviews()`
- **Pagination:** Up to 200 reviews per page from scraper
- **Config:** `SYNC_{PLATFORM}_REVIEWS_ENABLED`
