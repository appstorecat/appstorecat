# Database Tables — Improvement Proposals

Date: 20 Apr 2026
Branch context: `feat/sync-pipeline-rewrite`

## Overview

This document reviews every table owned by the Laravel server and lists
schema-level improvements — missing indexes, tighter column types, absent
foreign keys, partitioning candidates, and a few structural changes that
follow from the sync pipeline bug report.

Nothing in this document has been applied. Each proposal is a candidate
migration that should be reviewed, scheduled, and implemented independently.
Every entry is tagged with a **Priority** (High / Medium / Low), an
**Impact** (breaking / non-breaking) and an **Effort** (S / M / L), and a
consolidated matrix is provided at the end.

The proposals use Laravel Blueprint syntax. Raw SQL is only used where
Blueprint cannot express the change (e.g. `FULLTEXT` indexes, partitioning).

---

## 1. Critical Issues

These map directly to bugs surfaced in
[`bugs/report_20apr.md`](../bugs/report_20apr.md) and deserve a separate
section because they are blocking correct behaviour of the sync pipeline.

### 1.1 `app_metrics.price` cannot express "unknown"

**Current:**

```php
$table->decimal('price', 10, 2)->default(0);
$table->char('currency', 3)->nullable();
```

**Problem:** Bug 5 shows that the scraper does not currently populate
`price` / `currency` on the metrics payload. The column default of `0`
conflates "free" with "unknown" — once the scraper is fixed we still cannot
tell whether a historical row was genuinely free or simply not captured.

**Proposed:**

```php
Schema::table('app_metrics', function (Blueprint $table) {
    $table->decimal('price', 10, 2)->nullable()->default(null)->change();
});
```

Then update the connector to write `null` when the scraper omits the field,
and treat `0.00` as "confirmed free". Application code that currently relies
on `price === 0` must be audited.

**Priority:** High
**Impact:** Breaking (existing `0` rows become ambiguous unless backfilled)
**Effort:** M (requires backfill plan for historical rows)

### 1.2 No data-quality signal on partial metric rows

**Current:** `app_metrics` has no column that indicates whether the row is
complete. `rating_breakdown` can silently be `null` (Bug 6) while the row
still claims success.

**Problem:** Consumers cannot distinguish "country has no breakdown" from
"breakdown was dropped mid-pipeline". This also makes reconciliation harder
because we cannot cheaply find incomplete rows.

**Proposed:**

```php
Schema::table('app_metrics', function (Blueprint $table) {
    $table->unsignedTinyInteger('completeness')
        ->default(0)
        ->comment('Bitmask: 1=rating, 2=breakdown, 4=price, 8=availability');
    $table->index(['app_id', 'completeness']);
});
```

A bitmask keeps the column cheap and lets the pipeline mark which fields
were actually captured. Reconciliation can then target rows with
`completeness < expected_mask`.

**Priority:** High
**Impact:** Non-breaking (additive column)
**Effort:** M

### 1.3 `sync_statuses` single progress counter

**Current:**

```php
$table->unsignedInteger('progress_done')->default(0);
$table->unsignedInteger('progress_total')->default(0);
```

**Problem:** Bug 7 — the sync pipeline has multiple phases (identity,
listings, metrics, finalize, reconciling), but progress is tracked with a
single `(done, total)` pair that is overwritten when the phase changes. The
UI cannot show accurate per-phase progress and operators cannot tell which
phase is stuck.

**Proposed:** Per-phase counters.

```php
Schema::table('sync_statuses', function (Blueprint $table) {
    $table->unsignedInteger('listings_done')->default(0);
    $table->unsignedInteger('listings_total')->default(0);
    $table->unsignedInteger('metrics_done')->default(0);
    $table->unsignedInteger('metrics_total')->default(0);
    // Keep progress_done/progress_total as aggregated view or drop them.
});
```

Alternative: a single JSON `phase_progress` column keyed by phase. Discrete
columns are recommended because they are indexable and easier to aggregate
in SQL.

**Priority:** Medium
**Impact:** Non-breaking (additive columns); optionally breaking if the old
counters are dropped.
**Effort:** M

### 1.4 `apps.is_available` is global but availability is country-specific

**Current:** `apps.is_available` is a single boolean on the app row, while
`app_metrics.is_available` is recorded per `(app_id, country_code, date)`.

**Problem:** Bug 8 — the two columns disagree. An app can be available in
the US but delisted in Russia; the global flag cannot represent this. The
metrics row is authoritative but the app-level flag is what most UI paths
currently read.

**Proposed (Phase 1, recommended):** redefine `apps.is_available` as a
computed cache — "available in at least one active country". Update it from
the reconciliation step instead of the identity step.

```php
Schema::table('apps', function (Blueprint $table) {
    $table->boolean('is_available')
        ->default(true)
        ->comment('Cached aggregate: true if available in >= 1 active country. '
            . 'Authoritative data lives in app_metrics.is_available.')
        ->change();
});
```

**Proposed (Phase 2, longer term):** drop the column entirely once all
reads go through a view or resource that aggregates
`app_metrics.is_available`.

```php
Schema::table('apps', function (Blueprint $table) {
    $table->dropColumn('is_available');
});
```

A migration path is required: audit every usage of `apps.is_available`,
introduce an `AvailabilityResolver` service, then drop the column.

**Priority:** High
**Impact:** Breaking (eventually), non-breaking during Phase 1
**Effort:** L

---

## 2. Schema improvements

Per-table proposals. Each entry follows the same format.

### 2.1 `apps`

#### Index on `last_synced_at`

**Current:** No index. The tracked-sync scheduler scans the table ordered
by `last_synced_at` every 20 minutes.

**Problem:** Full table scans grow linearly with app count.

**Proposed:**

```php
Schema::table('apps', function (Blueprint $table) {
    $table->index('last_synced_at');
});
```

**Priority:** High
**Impact:** Non-breaking
**Effort:** S

#### Index on `discovered_from`

**Current:** No index on the discovery source enum.

**Problem:** Dashboards that break down app counts by discovery source
issue `GROUP BY discovered_from` queries that scan the entire table.

**Proposed:**

```php
Schema::table('apps', function (Blueprint $table) {
    $table->index('discovered_from');
});
```

**Priority:** Low
**Impact:** Non-breaking
**Effort:** S

#### Index on `is_available`

**Current:** No index. Tracked sync filters on `is_available = true`.

**Proposed:**

```php
Schema::table('apps', function (Blueprint $table) {
    $table->index(['platform', 'is_available', 'last_synced_at']);
});
```

A composite index covers the common "next apps to sync for platform X"
query.

**Priority:** Medium
**Impact:** Non-breaking
**Effort:** S

#### Foreign key on `origin_country`

**Current:** `origin_country` is `char(2) default 'us'` with no FK.

**Problem:** Nothing prevents invalid or unknown country codes. The
`countries` table is the reference source of truth.

**Proposed:**

```php
Schema::table('apps', function (Blueprint $table) {
    $table->foreign('origin_country')
        ->references('code')->on('countries')
        ->onUpdate('cascade')->onDelete('restrict');
});
```

Requires a backfill pass that maps any legacy `US`/`TR` uppercase values to
lowercase before the FK can be added.

**Priority:** Medium
**Impact:** Breaking (invalid rows rejected)
**Effort:** M

#### Case consistency for country codes

**Current:** Codes are stored lowercase (`us`, `tr`) but a few call sites
still pass uppercase. `trending_charts.country` enforces
`references('code')` already, which would reject mismatches.

**Proposed:** Add a CHECK constraint (MySQL 8.4 supports it) or normalise
on the model mutator:

```php
// app/Models/App.php
public function setOriginCountryAttribute(?string $value): void
{
    $this->attributes['origin_country'] = $value ? strtolower($value) : null;
}
```

**Priority:** Low
**Impact:** Non-breaking
**Effort:** S

#### Separate `bundle_id` / `package_name`

**Current:** `external_id` is the single store identifier — a numeric
string on iOS and a reverse-DNS package name on Android. The same column
is used for both.

**Problem:** Some queries (e.g. deep links, external APIs) need the
bundle ID even for iOS apps, and the numeric iTunes ID stored in
`external_id` is not enough. Today we go back to the scraper for it.

**Proposed:** Add an optional `bundle_id` column populated during identity
sync.

```php
Schema::table('apps', function (Blueprint $table) {
    $table->string('bundle_id', 191)->nullable()->after('external_id');
    $table->index(['platform', 'bundle_id']);
});
```

For Android, `external_id` is already the bundle ID; leave `bundle_id`
nullable and mirror when needed, or skip populating it.

**Priority:** Low
**Impact:** Non-breaking
**Effort:** S

### 2.2 `app_metrics`

#### Tighten `country_code` to `CHAR(2)`

**Current:** `country_code VARCHAR(10)` — historical artefact.

**Problem:** Wastes key space on a unique constraint
`(app_id, country_code, date)` and invites inconsistent values. Every
actual value is a 2-letter ISO code.

**Proposed:**

```php
Schema::table('app_metrics', function (Blueprint $table) {
    $table->char('country_code', 2)->change();
    $table->foreign('country_code')
        ->references('code')->on('countries')
        ->onUpdate('cascade')->onDelete('restrict');
});
```

**Priority:** High
**Impact:** Breaking (requires backfill of any legacy non-2-letter rows)
**Effort:** M

#### Partitioning strategy (long-term)

**Current:** `app_metrics` grows roughly at `apps × countries × days`,
which becomes the largest table in the system.

**Proposed:** Range-partition by `date` on a yearly cadence once the table
crosses ~50M rows. Example (raw SQL, Blueprint cannot express partitions):

```sql
ALTER TABLE app_metrics
PARTITION BY RANGE (YEAR(date)) (
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION pmax  VALUES LESS THAN MAXVALUE
);
```

Partitioning must precede any retention job (see §4).

**Priority:** Low (watch-item)
**Impact:** Non-breaking if planned before it becomes urgent
**Effort:** L

### 2.3 `app_store_listings`

#### Composite index for latest-listing lookups

**Current:** Index on `version_id` only.

**Problem:** The common query "give me the most recent listing for app X
in language Y" currently filters by `(app_id, language)` and orders by
`fetched_at`, which falls back to a filesort.

**Proposed:**

```php
Schema::table('app_store_listings', function (Blueprint $table) {
    $table->index(['app_id', 'language', 'fetched_at']);
});
```

**Priority:** High
**Impact:** Non-breaking
**Effort:** S

#### Index on `checksum`

**Current:** No index. Change detection reads checksum row-by-row during
the sync pipeline.

**Proposed:**

```php
Schema::table('app_store_listings', function (Blueprint $table) {
    $table->index('checksum');
});
```

**Priority:** Medium
**Impact:** Non-breaking
**Effort:** S

#### FULLTEXT index on title/description (search support)

**Current:** No text search index.

**Problem:** Planned app-search endpoints will otherwise require either
`LIKE '%foo%'` scans or an external search engine.

**Proposed (raw SQL, Blueprint's `fullText` requires Laravel 9+ and MySQL):**

```php
Schema::table('app_store_listings', function (Blueprint $table) {
    $table->fullText(['title', 'description']);
});
```

**Priority:** Medium
**Impact:** Non-breaking
**Effort:** M (requires checking MySQL config: `ngram` parser for non-Latin
languages)

### 2.4 `app_store_listing_changes`

#### Index on `field_changed`

**Current:** Indexes on `(app_id, detected_at)` and `version_id`.

**Problem:** The UI filters the changelog by field type (title vs.
description vs. screenshots); those queries scan per-app ranges.

**Proposed:**

```php
Schema::table('app_store_listing_changes', function (Blueprint $table) {
    $table->index(['app_id', 'field_changed', 'detected_at']);
});
```

**Priority:** Medium
**Impact:** Non-breaking
**Effort:** S

#### Partitioning candidate

**Current:** Fast-growing append-only table.

**Proposed:** Range-partition by `YEAR(detected_at)` once the table passes
a few tens of millions of rows; same approach as §2.2.

**Priority:** Low (watch-item)
**Impact:** Non-breaking
**Effort:** L

### 2.5 `app_versions`

#### Index on `release_date`

**Current:** Only the unique `(app_id, version)` index.

**Problem:** "Apps with a new release this week" queries scan the table.

**Proposed:**

```php
Schema::table('app_versions', function (Blueprint $table) {
    $table->index('release_date');
    $table->index(['app_id', 'release_date']);
});
```

**Priority:** Medium
**Impact:** Non-breaking
**Effort:** S

### 2.6 `publishers`

#### Missing metadata

**Current:** `id, name, external_id, platform, url`.

**Problem:** Publisher profiles lack website, support email, and origin
country — all fields the scrapers already return for iOS.

**Proposed:**

```php
Schema::table('publishers', function (Blueprint $table) {
    $table->string('website')->nullable()->after('url');
    $table->string('support_email')->nullable()->after('website');
    $table->char('country', 2)->nullable()->after('support_email');

    $table->foreign('country')
        ->references('code')->on('countries')
        ->nullOnDelete();
});
```

**Priority:** Low
**Impact:** Non-breaking
**Effort:** S

#### Platform column naming

**Current:** `platform` tinyint, same convention as `apps.platform`.

**Note:** Consistent with `apps` — no change needed, but see §4 for the
platform-enum discussion.

### 2.7 `store_categories`

#### Composite index

**Current:** Unique key expected on `(platform, external_id)`; no index on
`(platform, parent_id)` for tree walks.

**Proposed:**

```php
Schema::table('store_categories', function (Blueprint $table) {
    $table->index(['platform', 'parent_id']);
});
```

**Priority:** Low
**Impact:** Non-breaking
**Effort:** S

### 2.8 `trending_chart_entries`

#### Covering index

**Current:** Indexes on `(trending_chart_id, rank)` and
`(app_id, trending_chart_id)`.

**Problem:** Read-heavy — the leaderboard endpoints need `price`,
`currency` too, which are currently fetched from the row body.

**Proposed:** For MySQL 8.4 with InnoDB, add a secondary that includes the
frequently-read columns (MySQL treats PK as the clustering key, so an
index that starts with the filter columns is already efficient). Adding a
plain index on `(app_id)` alone would help cross-chart lookups:

```php
Schema::table('trending_chart_entries', function (Blueprint $table) {
    $table->index('app_id');
});
```

**Priority:** Low
**Impact:** Non-breaking
**Effort:** S

### 2.9 `sync_statuses`

See §1.3 for the per-phase progress proposal.

#### Index on `job_id`

**Current:** `job_id` is a char(36) column with no index.

**Problem:** Worker callbacks look up by `job_id` to close out a run.

**Proposed:**

```php
Schema::table('sync_statuses', function (Blueprint $table) {
    $table->index('job_id');
});
```

**Priority:** Medium
**Impact:** Non-breaking
**Effort:** S

### 2.10 `user_apps`

#### Add `updated_at` and activity columns

**Current:** Only `created_at` — a deliberate choice when the table was
just a pivot. It is no longer just a pivot: the UI now tracks pinning and
last-opened state.

**Proposed:**

```php
Schema::table('user_apps', function (Blueprint $table) {
    $table->timestamp('updated_at')->nullable()->after('created_at');
    $table->timestamp('pinned_at')->nullable()->after('updated_at');
    $table->timestamp('last_opened_at')->nullable()->after('pinned_at');

    $table->index(['user_id', 'pinned_at']);
    $table->index(['user_id', 'last_opened_at']);
});
```

**Priority:** Medium
**Impact:** Non-breaking
**Effort:** S

---

## 3. Cross-cutting concerns

### 3.1 `platform` as tinyint enum

Every scraper-facing table uses `platform` tinyint (`1` iOS, `2` Android)
and serialises to a slug in JSON. This is fine today but:

- Any new platform (e.g. Huawei AppGallery) requires a migration and a
  constant update in three places.
- The `tinyint` values are opaque in SQL exploration.

**Options:**

- Leave as-is and document the mapping in one place
  (`app/Support/Platform.php`).
- Promote to a `platforms` reference table with a FK.

No recommendation yet — the current setup is low-friction; revisit when a
third platform is on the roadmap.

### 3.2 Soft deletes

No table currently uses `SoftDeletes`. For user-owned rows
(`user_apps`, `app_competitors`) a soft delete would help undo
accidental destructive actions in the UI. For scraper-owned rows
(`apps`, `app_versions`, `app_metrics`) hard deletion is correct — the
records can always be re-scraped.

**Priority:** Low
**Impact:** Non-breaking
**Effort:** S (per table)

### 3.3 Locale / language field length

`app_store_listings.language` is `varchar(10)` while some in-code
constants use `char(5)`. The BCP-47 tags we actually emit
(`en-US`, `zh-Hant`) fit in 10 but rows never exceed 7 characters.

**Proposal:** Standardise on `varchar(10)` everywhere and document the
expectation in `architecture/data-model.md`. No schema change, just an
audit.

**Priority:** Low
**Impact:** Non-breaking
**Effort:** S

### 3.4 Retention and archival

The three fast-growing tables are:

1. `app_metrics` — daily granularity per country.
2. `app_store_listing_changes` — append-only changelog.
3. `trending_chart_entries` — daily snapshot per chart.

**Proposal:** define a retention policy per table (e.g. keep `app_metrics`
at daily granularity for 90 days, then roll up to weekly for 1 year, then
monthly). Implement as a nightly artisan command that:

1. Aggregates old rows into a sibling table (`app_metrics_weekly`,
   `app_metrics_monthly`).
2. Deletes the source rows inside a transaction.
3. Emits a log line with counts for observability.

Partitioning (§2.2) makes step 2 cheap; without it, large `DELETE`
statements cause long InnoDB undo log pressure.

**Priority:** Low (watch-item)
**Impact:** Non-breaking
**Effort:** L

---

## 4. Priority Matrix

| # | Proposal | Priority | Impact | Effort | Status |
|---|----------|----------|--------|--------|--------|
| 1.1 | `app_metrics.price` nullable (unknown vs free) | High | Breaking | M | Pending |
| 1.2 | `app_metrics.completeness` bitmask | High | Non-breaking | M | Pending |
| 1.3 | Per-phase progress on `sync_statuses` | Medium | Non-breaking | M | Pending |
| 1.4 | Redefine/drop `apps.is_available` | High | Breaking (Phase 2) | L | Pending |
| 2.1a | Index `apps.last_synced_at` | High | Non-breaking | S | ✅ Applied |
| 2.1b | Index `apps.discovered_from` | Low | Non-breaking | S | ✅ Applied |
| 2.1c | Composite `apps(platform, is_available, last_synced_at)` | Medium | Non-breaking | S | ✅ Applied |
| 2.1d | FK `apps.origin_country_code` → `countries.code` | Medium | Breaking | M | ✅ Applied |
| 2.1e | Country-code lowercase normalisation | Low | Non-breaking | S | Pending |
| 2.1f | Add `apps.bundle_id` | Low | Non-breaking | S | ✅ Applied |
| 2.2a | `app_metrics.country_code` → `CHAR(2)` + FK | High | Breaking | M | ✅ Applied |
| 2.2b | `app_metrics` partitioning | Low | Non-breaking | L | Pending |
| 2.3a | Composite `app_store_listings(app_id, locale, fetched_at)` | High | Non-breaking | S | ✅ Applied |
| 2.3b | Index `app_store_listings.checksum` | Medium | Non-breaking | S | ✅ Applied |
| 2.3c | FULLTEXT on `title`, `description` | Medium | Non-breaking | M | Pending |
| 2.4a | Index `app_store_listing_changes(app_id, field_changed, detected_at)` | Medium | Non-breaking | S | ✅ Applied |
| 2.4b | `app_store_listing_changes` partitioning | Low | Non-breaking | L | Pending |
| 2.5 | Index `app_versions.release_date` | Medium | Non-breaking | S | ✅ Applied |
| 2.6 | `publishers` website/email/country | Low | Non-breaking | S | Pending |
| 2.7 | Index `store_categories(platform, parent_id)` | Low | Non-breaking | S | ✅ Applied |
| 2.8 | Index `trending_chart_entries.app_id` | Low | Non-breaking | S | ✅ Applied |
| 2.9 | Index `sync_statuses.job_id` | Medium | Non-breaking | S | ✅ Applied |
| 2.10 | `user_apps` `updated_at`, `pinned_at`, `last_opened_at` | Medium | Non-breaking | S | Pending |
| 3.1 | `platform` enum discussion | — | — | — | Pending |
| 3.2 | Soft deletes on user-owned rows | Low | Non-breaking | S | Pending |
| 3.3 | Locale field length audit | Low | Non-breaking | S | Pending |
| 3.4 | Retention and archival policy | Low | Non-breaking | L | Pending |

### Applied in this iteration

On top of the items marked ✅ above, the following naming-consistency
changes shipped in the same migration pass:

- `apps.origin_country` → `apps.origin_country_code` (`CHAR(2)`, FK on
  `countries.code`).
- `apps.display_icon` → `apps.icon_url`.
- `app_store_listings.language` → `app_store_listings.locale`.
- `app_store_listing_changes.language` → `app_store_listing_changes.locale`.
- `trending_charts.country` → `trending_charts.country_code`.
- `AppMetric::GLOBAL_COUNTRY` switched from the 6-character string
  `'GLOBAL'` to the ISO 3166 user-assigned code `'zz'`, seeded as a
  `Country` row named "Global" so the new FK on
  `app_metrics.country_code` remains satisfied.

**Remaining first batch (design-work required):** 1.1, 1.2, 1.3, 2.2a
(now applied) —  the data-quality / progress-tracking items still need
dedicated design passes and backfill strategies.

**Remaining structural batch:** 1.4, 2.1e, 3.4 — larger changes that
need an application-level audit before they are safe to apply.
