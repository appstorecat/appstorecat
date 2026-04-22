# Changes pages overhaul (App Changes + Competitor Changes)

## Goal

Replace the two near-duplicate list pages with a single grouped, filterable
feed that matches how users actually read store-listing changes:
"which app, which day, which version bump, which fields moved".

## Scope

- `web/src/pages/changes/AppChanges.tsx`
- `web/src/pages/changes/CompetitorChanges.tsx`
- `web/src/components/ChangeCard.tsx`
- New `web/src/components/changes/*` shared building blocks
- Backend: widen swagger response for `/changes/apps` and `/changes/competitors`
  so Orval stops needing the `PaginatedChanges` cast.
- Backend: add optional `app_id` query param to both endpoints.

Out of scope for this pass:
- Word-level text diff (P3 in the audit, defer)
- Infinite scroll / cursor pagination (defer, keep `per_page=50` + load more button)

## New IA

One shared page component `ChangesFeedPage` with a `mode: 'tracked' | 'competitors'`
prop. Two tiny wrapper files keep the routes:

```
/changes/apps          → <ChangesFeedPage mode="tracked" />
/changes/competitors   → <ChangesFeedPage mode="competitors" />
```

The page renders:

1. Header (title + subtitle, mode-aware copy)
2. Sticky filter bar:
   - Search (app name)
   - Platform switcher (All / iOS icon / Android icon) — reuse existing pattern
   - Field dropdown (unchanged)
   - App picker (new) — only populated from the currently-loaded result set's apps
     so it's cheap and relevant
   - Active filter chips row with "Clear all"
3. Body:
   - Date section headers (`Today`, `Yesterday`, `Last 7 days`, `Earlier`) —
     sticky within the scroll container
   - Under each section, **change groups** keyed by
     `(app_id, version_transition)`
   - Each group renders as a collapsible card:
     - Header: icon + app name (link to `/apps/{platform}/{external_id}?tab=changes`)
       + version transition badge (`v1.2.6 → v1.2.7` or `v1.2.7` if initial)
       + summary pills (`3 × Subtitle`, `2 × Description`, etc.)
       + detected-date relative label
     - Collapsed body: one line per field affected with locale count.
     - Expanded body: the existing per-locale before/after blocks, reused from
       the current `ChangeCard` layout (just extracted into a sub-component).
4. Empty state: three distinct copies
   - No tracked apps (mode-aware: "Track an app…" / "Add competitors…") with CTA link
   - Has tracked apps but no changes yet ("Watching — nothing changed")
   - Filters active with 0 results ("No changes match · Clear filters")
5. Pagination: a "Load more" button below the feed when the response has
   `meta.current_page < meta.last_page`.

## Grouping logic (client-side)

Backend returns one `ChangeResource` per `(app, version, locale, field)`
combination, sorted `detected_at DESC`. Client groups them in-memory:

```
row → bucket key = `${date(detected_at)}|${app.id}|${previous_version ?? ''}|${version ?? ''}`
```

Within a bucket, keep an ordered list of rows so we can expand them
grouped by field. The bucket carries:

- `app` (first row's app)
- `previousVersion`, `version`
- `detectedAt` (min of the bucket, used for relative label)
- `byField: Map<field, rows[]>` — used for the summary pills and the
  expanded detail

## Components (new)

- `web/src/components/changes/ChangesFeedPage.tsx` — orchestrator, URL-param
  filter state, grouping, pagination. Replaces both old page files.
- `web/src/components/changes/ChangeGroupCard.tsx` — collapsible grouped card.
- `web/src/components/changes/ChangeFieldRow.tsx` — inner row rendering the
  before/after block for a single `(field, locale)` pair (extracted from the
  current `ChangeCard` body so nothing visual is lost).
- `web/src/components/changes/useChangesFilters.ts` — shared URL-param hook
  (platform, field, search, app_id).

Keep `web/src/components/ChangeCard.tsx` temporarily for any other consumer
(the app detail's `ChangesTab` already has its own card so this is likely
already free) — audit and delete if orphan.

## Backend changes

1. **Swagger response widening.** Both endpoints currently declare:
   ```
   properties: { data: array, links: object, meta: object }
   ```
   …but the `meta` object isn't typed, so Orval gives back `Record<string, unknown>`
   and the page treats the whole response as the array. Upgrade both to:
   ```
   allOf: [
     { $ref: '#/components/schemas/PaginatedMeta' },
     { type: object, properties: { data: array<ChangeResource> } },
   ]
   ```
   where `PaginatedMeta` is already a shared schema (or add one). Regenerate
   docs + Orval; drop the `PaginatedChanges` cast on the frontend.

2. **New query param `app_id`.** Optional, integer. Filters both endpoints to
   a single app from the user's tracked (or competitor) set. Reuses the same
   auth scoping — if the user passes an `app_id` they don't own, it simply
   returns zero rows (no 403).

3. **Form request update.** Add `app_id` validation to
   `ChangeAppsRequest` and `ChangeCompetitorsRequest`:
   ```
   'app_id' => ['sometimes', 'integer', 'min:1'],
   ```

## URL params (shared)

```
?platform=ios|android
?field=title|subtitle|description|whats_new|screenshots|locale_added|locale_removed
?search=<string>
?app_id=<int>
?page=<int>  (reserved for the load-more button — or we just track it in state)
```

All parseable, deep-linkable, reset via "Clear filters".

## Empty state matrix

| Tracked apps? | Filters active? | Results? | Copy |
| ------------- | --------------- | -------- | ---- |
| No            | —               | —        | "Start tracking apps" + CTA → `/apps` (or `/competitors` in competitors mode) |
| Yes           | No              | 0        | "No changes detected yet — we're watching" |
| Yes           | Yes             | 0        | "No changes match these filters" + "Clear filters" button |

Need a tiny signal from the response: is this user tracking anything? Either:
- Include a `meta.has_tracked_apps` flag server-side (preferred), or
- Read it from the existing auth/user payload if it exposes counts.

If neither is trivial, fall back to "Yes → No → 0" copy only; the tracked-count
nuance can come in a later pass.

## Testing

- Manually seed two grouped changes in the local DB (SMS io already has 8 rows
  on one version) and verify:
  - Sections collapse to the right buckets
  - Toggling "Clear filters" restores the feed
  - Mode switch between tracked/competitors works without crashing
  - Empty state copies trigger correctly
- Unit test the grouping helper with synthetic rows.
- Smoke test via the existing API test token (no changes to auth flow).

## Delivery

Single-commit tree-wide refactor (tests + code). No migration. No deploy needed
beyond the usual tag bump — backend changes are additive (new optional param,
widened swagger).
