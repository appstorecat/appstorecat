# Rate Limiting & Sync Capacity

## Scraper Rate Limits

| Platform | Safe Limit | Max Tested | Source |
|----------|-----------|------------|--------|
| iOS (App Store) | 10-15 req/min | 20 req/min | No official docs, community tested |
| Android (Google Play) | 10 req/min | 60 req/min (gplay-scraper built-in 1s delay) | Library default |

**Rule:** Stay at 10 req/min per platform. No rate limit headers returned — silent blocking on abuse.

---

## Single App Full Sync

### Requests per operation:

| Operation | iOS | Android | Notes |
|-----------|-----|---------|-------|
| Identity | 1 | 1 | Global, once |
| Metrics | 1 | 1 | Global, once |
| Listing (1 country) | 1 | 1 | Per country |
| Listing (49 countries) | 49 | 49 | All active countries |
| Reviews (1 country, 1 page) | 1 | 1 | ~50 iOS, ~100 Android per page |
| Reviews (1 country, all pages) | 10 | 1 | iOS max 10 pages (500 reviews), Android max ~100 |
| Reviews (49 countries, all pages) | 490 | 49 | Full review collection |

### Sync scenarios:

| Scenario | iOS Requests | Android Requests | Total | Duration @10req/min |
|----------|-------------|-----------------|-------|-------------------|
| **Default sync (US only)** | 4 | 4 | 8 | <1 min |
| **Listing all countries** | 51 | 51 | 102 | ~10 min |
| **Listing + Reviews (US only)** | 14 | 5 | 19 | ~2 min |
| **Listing all + Reviews US** | 61 | 55 | 116 | ~12 min |
| **Full sync (all countries + all reviews)** | 541 | 100 | 641 | ~64 min |

### Daily capacity (24 hours, 10 req/min per platform):

| Scenario | Max Apps/Day |
|----------|-------------|
| Default sync (US only) | 3,600 |
| Listing all countries (no reviews) | 144 |
| Listing all + Reviews US | 120 |
| Full sync (all countries + all reviews) | 22 |

---

## Daily Chart Sync

### Calculation:

```
Active countries: 49
Platforms: 2 (iOS, Android)
Collections: 3 (top_free, top_paid, top_grossing)
iOS categories: 25 + 1 (All) = 26
Android categories: 34 + 1 (All) = 35

iOS jobs:  49 × 3 × 26 = 3,822
Android jobs: 49 × 3 × 35 = 5,145
Total: 8,967 jobs/day
```

### Timing:

| Config | Value |
|--------|-------|
| iOS job interval | 3 seconds (20/min) — configurable via `CHARTS_IOS_JOB_INTERVAL` |
| Android job interval | 6 seconds (10/min) — configurable via `CHARTS_ANDROID_JOB_INTERVAL` |
| iOS duration | ~3.4 hours |
| Android duration | ~9.6 hours |
| Total (parallel) | ~9.6 hours (platforms run independently) |
| Schedule | Daily at 00:30 |
| Queue | charts-ios, charts-android (separate workers) |

### Chart entry data volume:

```
~8,967 snapshots/day × ~80 avg entries = ~717,000 rows/day
Monthly: ~21.5 million rows
Yearly: ~260 million rows
```

---

## Queue Architecture

All queues are platform-separated:

| Queue | Purpose | Rate |
|-------|---------|------|
| sync-discovery-ios | Untracked iOS app sync | 1 app/min (4 req) |
| sync-discovery-android | Untracked Android app sync | 1 app/min (4 req) |
| sync-tracked-ios | Tracked iOS app sync | 1 app/min (4 req) |
| sync-tracked-android | Tracked Android app sync | 1 app/min (4 req) |
| charts-ios | iOS chart snapshots | 10 job/min (6s delay) |
| charts-android | Android chart snapshots | 10 job/min (6s delay) |

### Total scraper load per platform per minute:

```
Sync discovery:  4 req (1 app)
Sync tracked:    4 req (1 app)
Charts:         10 req (10 jobs)
                ─────────
Total:          18 req/min peak (above safe limit!)
```

**⚠️ WARNING:** Combined load exceeds safe 10-15 req/min limit. Chart sync uses delay to spread, but peak moments may overlap with app sync.

### Mitigation:
- Chart jobs have 6s delay built-in (staggered)
- App sync runs 1 app/min (low frequency)
- Charts queue processes sequentially (single worker)
- Real overlap is unlikely: charts mostly idle between delayed jobs

---

## Review Sync Strategy (Planned)

### Current:
- Reviews fetched once during syncAll (1 page, US only)
- ~50 reviews iOS, ~100 reviews Android

### Planned incremental approach:
1. Check latest review date in DB for this app+country
2. Fetch page by page from scraper
3. Stop when review dates are older than DB's latest
4. Only new reviews saved → minimal requests

### Estimated savings:
- First sync: 10 pages (iOS) / 1 page (Android)
- Subsequent syncs: 1-2 pages (only new reviews)
- 80-90% reduction in review requests after initial fetch

---

## Scaling Notes

- Current safe capacity: ~144 apps/day full listing sync (all countries)
- Redis queue recommended for >500 tracked apps
- Horizontal scaling: multiple scraper instances with IP rotation
- Consider: proxy rotation for high-volume scraping
- Database: partition trending_chart_entries by month for >50M rows
