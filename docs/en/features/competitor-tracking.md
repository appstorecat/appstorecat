# Competitor Tracking

Define competitor relationships between apps and monitor their store presence side by side.

![Competitor Tracking](../../screenshots/competitor-tracking.jpeg)

## Overview

AppStoreCat lets you identify competitors for the apps you track. Competitor apps are then synced and monitored alongside your own apps, so you can compare listings, keywords, ratings, and changes side by side.

## How It Works

1. Track an app
2. Add competitor apps from the app detail page
3. Competitors are synced on the same schedule as your tracked apps
4. Compare keywords, listings, and changes across all competitors

## Competitor Relationships

Each competitor link is user-specific: your competitor definitions are independent of other users'. The relationship is stored with a type (default: `direct`).

## API

### List Competitors

```
GET /api/v1/apps/{platform}/{externalId}/competitors
```

Returns all competitors defined for an app.

### Add a Competitor

```
POST /api/v1/apps/{platform}/{externalId}/competitors
Body: { "competitor_app_id": 123 }
```

### Remove a Competitor

```
DELETE /api/v1/apps/{platform}/{externalId}/competitors/{competitorId}
```

### All Competitor Apps

```
GET /api/v1/competitors
```

Returns every competitor app across all of your tracked apps.

## UI

The **Competitors** tab on the app detail page shows:
- A list of competitor apps with their ratings and categories
- An add-competitor button (search for an app to add)
- Quick navigation to the competitor detail pages

To see every competitor app across all of your tracked apps, go to the **Competitors** page in the sidebar.

## Technical Details

- **Model:** `AppCompetitor`
- **Table:** `app_competitors`
- **Unique constraint:** `(user_id, app_id, competitor_app_id)`
- **Controller:** `CompetitorController`
- **Relationship type:** `direct` (stored in the `relationship` column)
