# Competitor Tracking

Define competitor relationships between apps and monitor their store presence side by side.

![Competitor Tracking](../../screenshots/competitor-tracking.jpeg)

## Overview

AppStoreCat lets you define which apps are competitors to your tracked apps. Competitor apps are then synced and monitored alongside your own apps, enabling side-by-side comparison of listings, keywords, ratings, and changes.

## How It Works

1. Track an app
2. Add competitor apps from the app's detail page
3. Competitors are synced on the same schedule as your tracked apps
4. Compare keywords, listings, and changes across all competitors

## Competitor Relationships

Each competitor link is per-user: your competitor definitions are independent of other users. The relationship is stored with a type (default: `direct`).

## API

### List Competitors

```
GET /api/v1/apps/{platform}/{externalId}/competitors
```

Returns all competitors defined for an app.

### Add Competitor

```
POST /api/v1/apps/{platform}/{externalId}/competitors
Body: { "competitor_app_id": 123 }
```

### Remove Competitor

```
DELETE /api/v1/apps/{platform}/{externalId}/competitors/{competitorId}
```

### All Competitor Apps

```
GET /api/v1/competitors
```

Returns all competitor apps across all your tracked apps.

## Frontend

The **Competitors** tab on the app detail page shows:
- List of competitor apps with their ratings and categories
- Add competitor button (search for apps to add)
- Quick navigation to competitor detail pages

Navigate to **Competitors** in the sidebar to see all competitor apps across your tracked apps.

## Technical Details

- **Model:** `AppCompetitor`
- **Table:** `app_competitors`
- **Unique constraint:** `(user_id, app_id, competitor_app_id)`
- **Controller:** `CompetitorController`
- **Relationship type:** `direct` (stored as `relationship` column)
