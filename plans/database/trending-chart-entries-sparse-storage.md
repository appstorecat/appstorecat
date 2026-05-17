# Trending Chart Entries — Sparse Storage Refactor

**Status:** Proposed — not started

**Scope:** convert `trending_chart_entries` from a naive daily-snapshot model to a sparse rank-change model with `valid_from`/`valid_to` interval columns. Preserve full historical granularity. No data loss, no rollup, no aggregation.

**Target tables:** `trending_chart_entries` (currently 43.7M rows / 7.7 GB / 69% of total DB size).

---

## 1. Problem Statement

### Current schema

```sql
CREATE TABLE `trending_chart_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trending_chart_id` bigint unsigned NOT NULL,  -- FK to trending_charts (which holds snapshot_date)
  `rank` smallint unsigned NOT NULL,
  `app_id` bigint unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trending_chart_entries_trending_chart_id_rank_index` (`trending_chart_id`,`rank`),
  KEY `trending_chart_entries_app_id_trending_chart_id_index` (`app_id`,`trending_chart_id`),
  KEY `trending_chart_entries_app_id_index` (`app_id`),
  CONSTRAINT `trending_chart_entries_app_id_foreign` FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trending_chart_entries_trending_chart_id_foreign` FOREIGN KEY (`trending_chart_id`) REFERENCES `trending_charts` (`id`) ON DELETE CASCADE
);
```

`trending_charts` carries the `(platform, collection, country_code, category_id, snapshot_date)` tuple; every entry is one row per day per rank.

### Growth profile

Measured on the prod dump dated 2026-05-15:

| Metric | Value |
|---|---|
| Total rows | 43,681,656 |
| Total size | 7.7 GB (2.8 GB data + 4.9 GB index) |
| Days covered | 25 |
| Daily growth | ~1.75M rows / ~310 MB |
| Projected at 90 days | ~30 GB |
| Projected at 365 days | ~120 GB |
| Distinct snapshot dates × charts | 25 × ~19,900 = 497,717 chart snapshots |

### Why it grows so fast

Most apps' ranks are stable from day to day. A rank-1 app that holds the spot for 30 days produces **30 identical rows**. Top-200 lists are not volatile — empirically the same `(chart, app, rank)` triple repeats across consecutive days for the vast majority of entries.

Naive insertion does not exploit this redundancy. Every day's scrape blindly inserts a fresh full snapshot.

### Index cost

`index_length` (4.9 GB) is **larger than data_length** (2.8 GB). Three indexes exist; one is redundant:
- `(trending_chart_id, rank)` — used for "today's top N" queries.
- `(app_id, trending_chart_id)` — used for "this app's chart history" queries.
- `(app_id)` — **redundant**; the `(app_id, trending_chart_id)` compound already covers app-only lookups via leftmost-prefix rule.

Dropping the redundant index alone reclaims ~700 MB. Not the main goal of this plan, but worth doing.

### What we are NOT solving here

This plan deliberately scopes only to schema-level sparseness. **No retention**, **no rollup**, **no top-N reduction**, **no cross-table aggregation**. The user has stated all historical granularity must be preserved.

Related items belong in separate plans:
- `app_store_listings` content-addressable refactor → separate plan
- Failed jobs retention → separate plan
- `sync_statuses.failed_items` cleanup → separate plan

---

## 2. Target Schema

### Sparse interval model

```sql
CREATE TABLE `trending_chart_entries_sparse` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `platform` tinyint unsigned NOT NULL COMMENT 'iOS=1, Android=2 (matches Platform enum)',
  `collection` varchar(32) NOT NULL COMMENT 'top_free | top_paid | top_grossing',
  `country_code` char(2) NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL COMMENT 'FK to store_categories; NULL = overall chart',
  `app_id` bigint unsigned NOT NULL,
  `rank` smallint unsigned NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) DEFAULT NULL,
  `valid_from` date NOT NULL COMMENT 'First snapshot_date this rank was observed (inclusive)',
  `valid_to` date DEFAULT NULL COMMENT 'Last snapshot_date this rank was observed (inclusive); NULL = currently still at this rank',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tces_chart_open_rank_idx` (`platform`,`collection`,`country_code`,`category_id`,`valid_to`,`rank`),
  KEY `tces_chart_date_rank_idx` (`platform`,`collection`,`country_code`,`category_id`,`valid_from`,`valid_to`,`rank`),
  KEY `tces_app_idx` (`app_id`,`valid_from`),
  CONSTRAINT `tces_app_id_foreign` FOREIGN KEY (`app_id`) REFERENCES `apps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tces_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `store_categories` (`id`) ON DELETE SET NULL
);
```

### Key design decisions

**1. Embedded chart identity, no `trending_chart_id` FK**

The old schema had every entry pointing to a `trending_charts` row holding `(platform, collection, country_code, category_id, snapshot_date)`. With sparse entries spanning many dates, that row no longer makes sense per-entry. We embed the chart identity tuple `(platform, collection, country_code, category_id)` directly. `trending_charts` becomes irrelevant for entry storage — could be kept for "which charts have ever been observed" registry, or dropped entirely.

**2. `valid_from` / `valid_to` semantics**

- `valid_from` = first day this `(chart, app, rank)` was observed
- `valid_to` = last day this `(chart, app, rank)` was observed
- `valid_to IS NULL` = "as of the most recent scrape, still in chart at this rank"
- Both inclusive. A one-day stint has `valid_from = valid_to`.

**3. Gaps mean "out of chart"**

If an app exits a chart and re-enters later, two rows exist with a date gap. The gap is the out-of-chart period. There is no explicit "removed" row — absence of an interval covering that date is the answer.

**4. Rank changes close + open**

When an app's rank changes within a chart, the previous row's `valid_to` is updated to yesterday, and a new row is opened with `valid_from = today`, `valid_to = NULL`.

**5. Price/currency tracking**

Price/currency are also part of the interval state. Strictly speaking, if rank stays the same but price changes, that should be a new interval. **Open decision:** treat price changes as interval-breaking or only rank? Most usage cares about rank, not price tier. **Recommendation:** rank-only interval breaks; store the most recently observed price/currency on the existing row (overwrite on each scrape that re-observes the same rank). Loses historical price granularity in exchange for ~10× more compaction. If price history is needed, it should live in `app_metrics`, not here.

**6. Indexes**

Three indexes, each justified by a query pattern:

| Index | Query it serves |
|---|---|
| `(platform, collection, country, category, valid_to, rank)` | "Current top N in chart X" — filters `valid_to IS NULL` then sorts by rank |
| `(platform, collection, country, category, valid_from, valid_to, rank)` | "Top N in chart X on date D" — range scan |
| `(app_id, valid_from)` | "This app's chart history" — across all charts |

The two compound indexes overlap. If write throughput suffers, drop the first and rely on the second with `valid_to IS NULL` as a filter predicate. Decision deferred to load testing.

---

## 3. Query Patterns

### "Today's top 10 US iOS top_free"

```sql
SELECT rank, app_id FROM trending_chart_entries_sparse
WHERE platform = 1 AND collection = 'top_free' AND country_code = 'US' AND category_id IS NULL
  AND valid_to IS NULL
  AND rank <= 10
ORDER BY rank;
```

Same cardinality as today's "where snapshot_date = today" query. No regression.

### "Top 10 US iOS top_free on 2026-05-05"

```sql
SELECT rank, app_id FROM trending_chart_entries_sparse
WHERE platform = 1 AND collection = 'top_free' AND country_code = 'US' AND category_id IS NULL
  AND '2026-05-05' BETWEEN valid_from AND COALESCE(valid_to, CURDATE())
  AND rank <= 10
ORDER BY rank;
```

Range scan on the date-bracket. Slightly heavier than today's exact-date lookup, but still index-served (10s of rows scanned, not millions).

### "ChatGPT's rank history in US iOS top_free over the last 30 days"

```sql
SELECT valid_from, COALESCE(valid_to, CURDATE()) AS valid_to, rank
FROM trending_chart_entries_sparse
WHERE platform = 1 AND collection = 'top_free' AND country_code = 'US' AND category_id IS NULL
  AND app_id = ?
  AND COALESCE(valid_to, CURDATE()) >= CURDATE() - INTERVAL 30 DAY
ORDER BY valid_from;
```

Returns ~2–10 rows instead of ~30 in the naive model. UI renders each row as a horizontal segment in a time-series chart.

### "On 2026-05-08, was ChatGPT in the chart? At what rank?"

Same as the range-scan query above with `app_id = ChatGPT` added. Returns 0 rows if it was out of chart that day, 1 row with the rank otherwise.

### Insert mantığı (yeni scraper davranışı)

Pseudocode for each daily scrape of a chart:

```
incoming = scraper.fetch(chart_key)   // list of (app_id, rank, price, currency)
today    = snapshot_date

DB.transaction:
  open_rows = SELECT * FROM tces WHERE chart_key = chart_key AND valid_to IS NULL

  for each row in open_rows:
    if row.app_id not in incoming:
      # exited chart
      UPDATE tces SET valid_to = today - 1 WHERE id = row.id
    elif incoming[row.app_id].rank != row.rank:
      # rank changed
      UPDATE tces SET valid_to = today - 1 WHERE id = row.id
      INSERT INTO tces (..., valid_from = today, valid_to = NULL)
    else:
      # rank unchanged → optionally bump price/currency on existing row
      UPDATE tces SET price = ?, currency = ?, updated_at = NOW() WHERE id = row.id

  for each entry in incoming:
    if entry.app_id not in open_rows:
      # new entry (newly in chart, or re-entering)
      INSERT INTO tces (..., valid_from = today, valid_to = NULL)
```

Idempotency: re-running the same scrape on the same day should be a no-op. The `valid_to = today - 1` step would be wrong if today's data already exists — guard with `valid_from < today` on the close UPDATE.

---

## 4. Migration Strategy

### Algorithm: gaps-and-islands

Standard SQL pattern for collapsing consecutive-equal-value rows into intervals.

**Input:** naive rows joined with `trending_charts` to get `(platform, collection, country, category, snapshot_date, app_id, rank, price, currency)`.

**Output:** sparse rows with `(chart_key, app_id, rank, valid_from, valid_to)`.

**Logic:**

For each `(chart_key, app_id)` group, sort by `snapshot_date`. Walk row by row:
- If `rank` differs from previous row → start a new interval
- If `snapshot_date` is not previous + 1 day → start a new interval (gap = out-of-chart period)
- Otherwise → extend current interval's `valid_to` to current date

### Implementation options

**Option A — Pure SQL (window functions)**

Single statement using `LAG` to detect group boundaries, `SUM() OVER` to assign group ids, `GROUP BY` to collapse:

```sql
INSERT INTO trending_chart_entries_sparse (...)
WITH joined AS (
  SELECT
    tc.platform, tc.collection, tc.country_code, tc.category_id,
    tce.app_id, tce.rank, tce.price, tce.currency,
    tc.snapshot_date AS d
  FROM trending_chart_entries tce
  JOIN trending_charts tc ON tc.id = tce.trending_chart_id
),
flagged AS (
  SELECT *,
    CASE
      WHEN LAG(rank) OVER w IS NULL THEN 1
      WHEN LAG(rank) OVER w != rank THEN 1
      WHEN DATEDIFF(d, LAG(d) OVER w) > 1 THEN 1
      ELSE 0
    END AS is_new_group
  FROM joined
  WINDOW w AS (PARTITION BY platform, collection, country_code, category_id, app_id ORDER BY d)
),
grouped AS (
  SELECT *,
    SUM(is_new_group) OVER (PARTITION BY platform, collection, country_code, category_id, app_id ORDER BY d) AS gid
  FROM flagged
)
SELECT
  platform, collection, country_code, category_id, app_id, rank,
  MAX(price), MAX(currency),  -- pick latest within interval; arbitrary if mixed
  MIN(d), MAX(d), NOW(), NOW()
FROM grouped
GROUP BY platform, collection, country_code, category_id, app_id, rank, gid;
```

**Risks:**
- 43.7M rows × 3-level window function may exceed `tmp_table_size` / `max_heap_table_size` → falls back to disk, ~hours of disk I/O
- Hard to monitor; one statement either succeeds or fails

**Option B — PHP/Artisan command, chart-by-chart**

```
foreach chart_key in trending_charts (~19,900 keys):
  rows = SELECT ... WHERE platform=? AND collection=? AND country=? AND category_id=? ORDER BY snapshot_date, app_id
  group in memory by app_id
  emit intervals
  INSERT into trending_chart_entries_sparse
```

**Pros:**
- Bounded memory per chart (~2200 entries × 25 days = 55K rows = trivial)
- Resumable: track `last_processed_chart_id` in a state table; on failure resume from there
- Progress logging
- Can run in parallel via queue (one job per chart_key partition)

**Cons:**
- Slower than a single SQL statement if it succeeds (~hours vs ~hour)
- More code

**Recommendation: Option B.** Reliability and observability win over raw speed for a one-shot migration.

### Verification before cutover

After migration into `trending_chart_entries_sparse`, sample-verify correctness before deleting the old table:

```sql
-- Pick 1000 random (chart_key, app_id, snapshot_date) tuples from the OLD table
-- For each, query the NEW table with the date-range predicate
-- Compare rank values — must match 100%
```

A small Artisan command (`appstorecat:trending:verify-sparse-migration`) runs this.

### Cutover sequence

1. Deploy schema migration: create `trending_chart_entries_sparse`. No code changes yet.
2. Run backfill command: `appstorecat:trending:migrate-to-sparse`. ~hours.
3. Run verification command. Must pass 100%.
4. Deploy dual-write: scraper writes to **both** old and new tables for N days (recommend 7).
5. Switch read path: all queries (charts API, app rankings, dashboards) now read from sparse.
6. Monitor for N days. If issues, queries can revert to old table.
7. Remove old-table writes from scraper.
8. After confidence (recommend 30 days), drop `trending_chart_entries` and `trending_charts` (if entirely unused).
9. Rename `trending_chart_entries_sparse` → `trending_chart_entries` (optional, cosmetic).

---

## 5. Code Touch Points

### Server (Laravel)

- **Migration:** new migration creating `trending_chart_entries_sparse`.
- **Model:** new `ChartEntrySparse` Eloquent model. Eventually replaces `ChartEntry`.
- **`app/Jobs/Chart/SyncChartSnapshotJob.php`:** rewrite insert block to use sparse semantics (close-open pattern).
- **`app/Connectors/ChartConnector` (or wherever fetched):** no change to fetch shape.
- **`app/Http/Controllers/Api/V1/ChartController.php`:** rewrite queries to use `valid_to IS NULL` for "today" and date-range for "on date X".
- **`app/Http/Controllers/Api/V1/App/AppRankingController.php`:** rewrite to read intervals.
- **`app/Console/Commands/Trending/MigrateToSparseCommand.php`:** new command, batch migrator (Option B above).
- **`app/Console/Commands/Trending/VerifySparseCommand.php`:** new command, sample verifier.
- **API Resources:** `ChartEntryResource`, `AppRankingResource` — adjust to expose interval semantics if needed (e.g., return `valid_from`/`valid_to` instead of `snapshot_date` for ranking history endpoint).

### Web (React)

- **`useGetCharts` consumer:** API response shape stays the same for "current top N" view.
- **`RankingsTab.tsx`:** rank history visualization changes from "one point per day" to "horizontal segments per interval". Recharts can render segments; minor refactor.
- **`get_app_rankings` MCP tool:** description tweaks if response shape changes.

### Scraper services

No changes. Scrapers return raw chart data; the diff/sparse logic lives in the Laravel job that consumes the response.

---

## 6. Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Migration query exhausts MySQL temp table space | Medium | High (migration fails) | Use Option B (batched), not single SQL |
| Verification reveals migration bugs | Medium | High (data wrong) | Random-sample 1000+ tuples; abort on any mismatch |
| Dual-write window catches a scraper bug | Low | Medium | Compare daily row counts between old and new during dual-write |
| Query regression on "today's top N" | Low | Medium | Index `(chart_key, valid_to, rank)` — partial index on `valid_to IS NULL` ideal but MySQL doesn't support; covering index is the alternative |
| Idempotency bug double-inserts on re-run | Medium | Medium | Guard with `valid_from < today` on close UPDATE; integration test for re-run case |
| Price/currency history loss | Certain | Low | Documented decision; if needed, recover from `app_metrics` |
| FK constraint slows close UPDATE during scrape | Low | Low | Verify with EXPLAIN; index on `(chart_key, valid_to)` should make it fast |
| Old table referenced from places we missed | Medium | Medium | `grep -r "trending_chart_entries\|ChartEntry::class" server/` before cutover |

---

## 7. Validation

### Pre-migration

- [ ] Verify no orphan `trending_chart_entries` rows where `trending_chart_id` is invalid
- [ ] Verify no duplicate `(trending_chart_id, app_id)` within a single chart (would indicate a bug in current insert)
- [ ] Verify no duplicate `(trending_chart_id, rank)` within a single chart

### Post-migration

- [ ] Row count sanity: `sparse_count <= naive_count` always
- [ ] Sample 1000 random (chart, app, date) tuples; ranks match between old and new
- [ ] Every "open" sparse row (`valid_to IS NULL`) maps to a row in the most recent snapshot of that chart in old table
- [ ] Disk size measurement: target ≥5× compaction

### Post-cutover

- [ ] API integration tests pass (`/charts`, `/apps/{platform}/{id}/rankings`)
- [ ] Dashboard rendering unchanged
- [ ] MCP tools (`get_charts`, `get_app_rankings`) return same data
- [ ] Daily scrape completes in same wall-clock time (±20%)
- [ ] No new errors in `failed_jobs`

---

## 8. Estimated Effort

| Phase | Effort | Calendar |
|---|---|---|
| Schema migration + sparse model | 0.5 day | Day 1 |
| Migrate command (Option B, batched) | 1 day | Day 1–2 |
| Verify command + sample testing | 0.5 day | Day 2 |
| Scraper job rewrite (insert mantığı) | 1 day | Day 3 |
| Query rewrites (controllers, resources, frontend) | 1 day | Day 4 |
| Backfill run on prod | 0.5 day (mostly waiting) | Day 5 |
| Dual-write deploy + monitoring | 7 days | Week 2 |
| Cutover + monitoring | 7 days | Week 3 |
| Old table drop + cleanup | 0.5 day | Week 4 |

**Total active engineering:** ~5 days. **Total calendar:** ~4 weeks including soak periods.

---

## 9. Expected Outcome

| Metric | Before | After (estimated) | Reduction |
|---|---|---|---|
| Row count | 43.7M | 5–10M | ~75–85% |
| Total table size | 7.7 GB | 1.0–1.5 GB | ~80–85% |
| Daily growth | 310 MB | 40–80 MB | ~75% |
| 90-day projection | 30 GB | 4–7 GB | ~80% |
| 365-day projection | 120 GB | 15–25 GB | ~80% |

Granularity: **100% preserved**. Every historical day's rank is recoverable.

---

## 10. Open Questions

1. **Price/currency intervals** — break interval on price change, or treat price as latest-observed only? Default in this plan: latest-observed (no interval break on price). Confirm with product.
2. **`trending_charts` table** — keep as registry of "charts ever observed" (drops to ~20K rows, trivial) or remove entirely? If removed, the new sparse table is fully self-describing via embedded chart identity. Recommendation: drop after cutover.
3. **Category breadth** — `category_id` is nullable for "overall" charts. Verify all current chart fetching paths set it correctly to avoid `(NULL, NULL)` collisions.
4. **Backfill on prod** — prod has 25 days of data now. Migration must run on a quiet window; ideally between daily chart fetches (00:30 UTC + a few hours). Coordinate with scheduler.
5. **Rollback plan** — during dual-write, switching reads back is trivial. After old-table drop, recovery is from backup. Confirm backup window covers the rollback period.

---

## 11. Out of Scope (Documented Here for Cross-Reference)

These are real problems found during the DB audit but addressed in separate plans:

- **Redundant index `(app_id)` on current `trending_chart_entries`** — drop yields ~700 MB. Quick win, can be done independently of this plan.
- **`app_store_listings` deduplication** — 2.8 GB table with ~40% content redundancy across locales/versions.
- **`failed_jobs` retention** — 66 MB accumulating, no flush schedule.
- **`sync_statuses.failed_items` cleanup** — completed syncs still carry the JSON.
- **"Shell" apps in `apps` table** — 348K rows that exist only because they appeared in a chart once.
