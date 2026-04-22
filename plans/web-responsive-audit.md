# Web — Full Responsive Audit & Refactor Plan

**Scope:** make the entire `web/` frontend fully responsive from 375px (iPhone SE) up to 1920px+ desktops.

**Stack:** React 19, Vite 8, Tailwind CSS v4, shadcn/ui, React Router 7, @tanstack/react-query, recharts, base-ui.

**Breakpoints (Tailwind defaults, no custom):**
- `sm:` 640px
- `md:` 768px
- `lg:` 1024px
- `xl:` 1280px
- `2xl:` 1536px

---

## 1. Current State Overview

### What already works

- **Sidebar navigation** (`components/ui/sidebar.tsx`) — shadcn's sidebar primitive already detects mobile via `useIsMobile` hook and swaps to a `Sheet` drawer. `AppLayout` top bar has a `SidebarTrigger` (hamburger). **No work needed on nav scaffolding**; individual nav items already compress gracefully.
- **Auth pages** (`Login.tsx`, `Register.tsx`) — centered `max-w-sm` card, works fine on all sizes.
- **Landing.tsx** — recently fixed hero CTA responsive (commit `086c876`); footer and feature sections likely still need audit but out of the urgent-mobile-pain path.
- **Dialogs** (`ui/dialog.tsx`) — `DialogContent` has `w-full max-w-[calc(100%-2rem)] sm:max-w-sm`. Good baseline but inner content often assumes desktop width.
- **Icons grid** (`pages/explorer/Icons.tsx`) — already uses `grid-cols-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10`. Near-perfect.

### What is broken

Almost every other page has desktop-first assumptions:

- Multi-control filter bars (`Search input` + `PlatformSwitcher` + `CountrySelect` + `Select`) wrap haphazardly on mobile, overflow horizontally, or collapse under each other with no grouping.
- `apps/Show.tsx` header packs icon + title + 8 interactive controls (Track, More, CountrySelect, LanguageSelect, VersionSelect) into three horizontal flex children. On <640px some selects collide with the Track button.
- Tab bars are horizontally scrollable but have no visual cue (no shadow, no arrow).
- Several pages use `flex-1 min-w-[240px]` on the search field — forces ≥240px width, blows out the flex row below ~600px.
- `ChangesTab` uses `lg:grid-cols-[minmax(0,1fr)_260px]` — sidebar filters stack under timeline below lg, but filter UI still renders a `max-w-none` rounded panel that takes full width — fine, but lacks mobile-first offer to collapse.
- `KeywordsTab` compare columns grow dynamically to 5+ wide. Combined with sortable header, mobile is essentially unusable; the table just scrolls horizontally without a mobile-friendly layout.
- `RankingsTab` uses `min-w-[220px]` country column + variable `min-w-[180px]` category columns — `overflow-x-auto` saves it but there's no sticky first column, so mobile scroll loses context.
- `RatingsTab`, `ChangesTab` have sidebars (`lg:grid-cols-2`) that work ok, but the primary rating summary uses `md:flex-row` which is fine, only breaks below `md`.
- Landing.tsx beyond the hero is 973 lines; sections 2–6 not yet audited but expected to have desktop-first multi-column layouts.
- `ChangeCard` uses `md:grid-cols-2` for Before/After, so on mobile they stack vertically but take full width with whitespace-pre-line — long diffs can still expand without wrap (the `whitespace-pre-line` is fine but `line-clamp-3` at mobile might hide critical context — minor).
- `ExplorerScreenshots` row carousel uses `height: 400px` hard pixel — on mobile the iOS screenshot portrait is ~185px wide, fine; but the row is not touch-swipe optimized and Safari iOS lacks momentum scroll on `overflow-x-auto` without `-webkit-overflow-scrolling: touch` (Tailwind handles via utility? Need check).
- `Mcp.tsx` config page uses `pre` with `overflow-x-auto whitespace-pre-wrap` — OK but the long command lines produce vertical `pre` taller than viewport on mobile with no collapsed fallback.

### Qualitative score (before this audit)

| Area | Mobile-friendliness |
|---|---|
| Navigation shell | 🟢 Good |
| Auth | 🟢 Good |
| Landing hero | 🟢 Good (post-fix) |
| Landing (features/pricing/footer) | 🟡 Unknown, expected moderate issues |
| List pages (Apps, Competitors, Changes, Publishers, Discovery) | 🟡 Mostly works; filter bars messy |
| `apps/Show.tsx` header + tabs | 🔴 Broken at <sm |
| `publishers/Show.tsx` | 🟡 Header wraps, CTAs OK |
| StoreListingTab | 🟢 Works |
| ChangesTab | 🟡 Sidebar works, inner layout OK |
| KeywordsTab | 🔴 Table unusable at <md |
| RankingsTab | 🟡 Horizontal scroll, no sticky col |
| RatingsTab | 🟢 Decent |
| CompetitorsTab | 🟢 Cards stack fine |
| VersionsTab | 🟢 Fine |
| Explorer (Icons, Screenshots) | 🟢 Good |
| Settings + subpages | 🟢 Centered max-w-2xl, fine |
| MCP setup page | 🟡 `pre` blocks too tall on mobile |

---

## 2. Recurring Patterns to Refactor Once, Apply Everywhere

### Pattern A: Filter Bar

**Recurs in:** `apps/Index.tsx`, `competitors/Index.tsx`, `discovery/Apps.tsx`, `discovery/Publishers.tsx`, `discovery/Trending.tsx`, `publishers/Index.tsx`, `changes/AppChanges.tsx`, `changes/CompetitorChanges.tsx`, `explorer/Screenshots.tsx`, `explorer/Icons.tsx`.

**Current shape:**
```jsx
<div className="flex flex-wrap items-center gap-3">
  <div className="relative flex-1 min-w-[240px]"> {/* Search */}
  <PlatformSwitcher />
  <CountrySelect className="w-[180px]" />
  <Select /> {/* Category */}
</div>
```

**Problem:** `min-w-[240px]` on search + fixed `w-[180px]` + inline tab switcher = total width ≥ ~700px. Below that, items wrap into 3 rows at uneven heights.

**Fix:** extract a `<FilterBar>` shared component:
- Mobile (<sm): search full-width on its own row, then a second row with horizontally-scrollable chip row of `PlatformSwitcher` + selects
- sm+: wrap normally with consistent 12px gap
- md+: flex nowrap

Alternative (lighter): on each page, change container to `flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-3`, drop `min-w-[240px]` from search, make `CountrySelect` fully responsive (`w-full sm:w-[180px]`).

### Pattern B: PlatformTab segmented control

**Used inline in:** `apps/Index.tsx`, `competitors/Index.tsx`, `changes/AppChanges.tsx`, `changes/CompetitorChanges.tsx`, `discovery/Trending.tsx` (top_free/top_paid/top_grossing variant), `apps/Show.tsx` (tab bar variant), `components/PlatformSwitcher.tsx`.

**Problem:** on narrow viewports the pill row can still overflow especially when icons + labels are shown (App Store + Google Play + All = 220–260px).

**Fix:** add `overflow-x-auto -mx-4 px-4 snap-x snap-mandatory` wrapper when there are 3+ items. For `apps/Show.tsx` tab bar (7 items: Store Listing / Competitors / Keyword Density / Rankings / Ratings / Changes / Versions), this is the dominant pain — already has `-mx-4 overflow-x-auto px-4` but no fade edges, no active scroll into view. Improve by auto-scrolling active tab into view and adding edge-fade mask.

### Pattern C: Show-page Header (`apps/Show.tsx`)

**Problem:** three-column flex (icon / info / controls) squeezes at <640px. The right column (Track + Dropdown + CountrySelect + LanguageSelect + VersionSelect) has 5 widgets, unusable on mobile. CountrySelect default is w-[160px], Language w-[150px], Version w-[120px] = 430px alone.

**Fix:**
- Mobile: stack vertically. Row 1 = icon + title + menu (kebab). Row 2 = meta (rating, version, size). Row 3 = Track button full-width. Row 4 = Country/Language/Version on a horizontal-scroll chip row with `snap-x`.
- sm: partially stack (Track + menu inline with header, selects below)
- lg: current layout
- Use `flex-col md:flex-row` + conditional visibility

### Pattern D: Data tables with horizontal scroll

**Used in:** `Trending.tsx` (already has `hidden md:table-cell` for category column — good), `RankingsTab.tsx`, `KeywordsTab.tsx`.

**Problem:** all rely on `overflow-x-auto` but no sticky first column and no visual scroll hint.

**Fix:**
- Sticky first column on mobile (position: sticky left-0 bg-background z-10) for RankingsTab country column and KeywordsTab keyword column.
- Add right-edge shadow gradient to hint "more content →" on mobile (pure CSS with scroll-linked filter or a JS observer).
- Consider card fallback under `md:` for KeywordsTab (when compare apps selected, render one compact card per keyword with competitor deltas as small list).

### Pattern E: App list cards (`AppCard`, inline duplicates)

`AppCard.tsx` is shared but many pages reimplement a similar card inline (`Discovery/Apps`, `Discovery/Publishers`, `publishers/Show.tsx`, `competitors/Index.tsx`). All have `flex items-center gap-4 rounded-xl border p-4` with an action button in the corner.

**Problem:** on very narrow viewports the name + tracking button compete for the same row. `AppCard` uses `ml-auto` which puts the Button on the same row as the name — at 375px with long app names the button can push out.

**Fix:** in `AppCard.tsx`:
- Name row: just name + platform icon. On >=sm still include track button.
- Below sm: track button moves to its own row (full-width or right-aligned).

Alternatively: consolidate duplicated inline cards into a single `AppCard` component with proper props. This is a cleanup win independent of responsive.

### Pattern F: Dialog widths

`DialogContent` has `w-full max-w-[calc(100%-2rem)] sm:max-w-sm`. That's 96px margin each side on a 375px screen = 351px dialog. Fine.

But child content often uses `max-w-[420px]` (e.g., `CompetitorsTab` addDialog) which ≥ dialog content max → fine but tight. Worth standardizing.

### Pattern G: `flex items-center justify-between` with long content

Many page headers use `<div className="flex items-center justify-between">` with a title on the left and an action button on the right (e.g., `publishers/Show.tsx`, `competitors/Index.tsx` group header). When the title truncates, fine. When the button has a long label ("Track All (24)") it survives; when title is long, `truncate` saves it. OK.

### Pattern H: Global `overflow-x-hidden` safety net

`AppLayout.tsx` line 280: `<main className="flex min-w-0 flex-1 flex-col overflow-x-hidden">`. Already in place — catches accidental overflows. Good to leave.

---

## 3. Page-by-Page Inventory & Severity

Severity: 🔴 critical (feature unusable / layout broken) · 🟡 moderate (looks bad, works) · 🟢 polish

### Layouts

#### `layouts/AppLayout.tsx`

- Sidebar collapses to Sheet drawer on mobile ✅
- Header has hamburger, separator, breadcrumbs — **breadcrumbs may overflow** on deep paths like `/publishers/:id/:app` with long names. 🟡
- `sticky top-0 z-40 h-14` — fine.
- **Action:** wrap breadcrumb container in `overflow-hidden min-w-0` and let breadcrumbs use CSS overflow-x: auto or ellipsis middle items.

### Pages — Discovery

#### `pages/discovery/Trending.tsx` (249 lines)

- 🟡 Filter bar: 4 controls (PlatformSwitcher + CountrySelect + 3-tab pill + Category Select) with `flex-wrap gap-3`. On 375px wraps to 3 rows.
- 🟡 Table: has `hidden md:table-cell` for CATEGORY column (good pattern to replicate). Other columns OK.
- 🟢 Update date/Clock badge wraps fine.
- **Fixes:** filter bar chip-row; table already decent.

#### `pages/discovery/Apps.tsx` (177 lines)

- 🟡 Filter bar: search `flex-1` + PlatformSwitcher + CountrySelect. Same pattern.
- 🟢 Results grid `md:grid-cols-2` — mobile stacks to 1 col fine.
- 🟡 Inline app card (lines 104–167): similar to AppCard, shares track-button-in-same-row issue.
- **Fix:** filter bar + consolidate inline card with AppCard.

#### `pages/discovery/Publishers.tsx` (94 lines)

- 🟡 Filter bar — same.
- 🟢 Result grid OK.

### Pages — Tracking

#### `pages/apps/Index.tsx` (145 lines)

- 🟡 Filter bar — search with `min-w-[240px]` + 3-tab platform pill.
- 🟢 Grid `md:grid-cols-2`.
- Uses `AppCard` directly — the cascading track-button overflow applies.

#### `pages/competitors/Index.tsx` (197 lines)

- 🟡 Filter bar.
- 🟡 `ParentGroup` section has `ml-5 ... pl-5` for nested competitor cards — eats 40px indent on mobile, limits card width.
- **Fix:** reduce indent on mobile (`ml-2 pl-2 md:ml-5 md:pl-5`).

#### `pages/changes/AppChanges.tsx` + `pages/changes/CompetitorChanges.tsx` (200/200 lines, near-identical)

- 🟡 Filter bar: search + field Select + 3-tab platform pill. ~3 rows on mobile.
- 🟢 `ChangeCard` list; `md:grid-cols-2` for Before/After. OK.
- 🟡 Long diffs use `line-clamp-3` — might hide too much on mobile. Could adjust to `line-clamp-5` with clear "Show more".

#### `pages/publishers/Index.tsx` (178 lines)

- 🟡 Filter bar.
- 🟢 Cards grid OK.

### Pages — Detail (Show)

#### `pages/apps/Show.tsx` (450 lines) 🔴 **HIGHEST PRIORITY**

- 🔴 Header (lines 212–339):
  - Icon 64→96px (good responsive)
  - Middle column: truncating name + platform icon + meta row (wraps OK)
  - Right column: Track button + Dropdown + CountrySelect (160px) + LanguageSelect (150px) + VersionSelect (120px) = 430px+ — breaks hard at ≤640px
- 🟡 Tab bar (lines 350–375): already has `-mx-4 overflow-x-auto px-4` — works but no fade edges
- Inner tab content varies (see tab files)

**Fix plan for header:** 
```
375–639px:     [icon] [title]         [kebab menu]
               [     meta row        ]
               [Track button full-w  ]
               [Country|Lang|Version] ← horizontal scroll chip row

640–1023px:    [icon] [title+meta]   [Track] [kebab]
               [Country Lang Version on 2nd row]

1024px+:       current layout
```

#### `pages/publishers/Show.tsx` (272 lines)

- 🟡 Publisher header (143–158): "name + Store Page button" justify-between. Button shrinks at small viewports OK, but nothing breaks.
- 🟢 App cards grid OK.
- 🟡 Section header with `Track All (N)` button — wrap on narrow.

### Pages — Tabs (rendered inside apps/Show)

#### `components/tabs/StoreListingTab.tsx` (165 lines)

- 🟢 Listing header (icon + title + subtitle) fine.
- 🟢 Screenshots horizontal scroll with `h-[280px]` — fine, mobile swipes work.
- 🟢 Description/whats_new cards OK.

#### `components/tabs/CompetitorsTab.tsx` (319 lines)

- 🟢 Empty state + Add button OK.
- 🟢 Grid `md:grid-cols-2`, mobile stacks.
- 🟡 Add Competitor Dialog (176): `max-w-[420px]` — mobile narrows fine.

#### `components/tabs/KeywordsTab.tsx` (560 lines) 🔴 **CRITICAL**

- 🔴 Table with dynamic compare columns (up to 5 extra). At mobile width, 3-col table already tight; 7-col impossible.
- 🔴 Toolbar (`n-gram tabs`) + compare chip row + search input — lots of horizontal controls.
- 🔴 Sort headers can't be tapped if column width < 60px.

**Fix:**
- Mobile card fallback (<md): render each keyword as a card with keyword name, primary density, and compare apps as a small list inside the card.
- Keep table for ≥md.
- `KeywordTable`'s `max-h-[600px]` scroll area + sticky thead → add `sticky left-0` to first `td` for horizontal scroll on small tablet widths.

#### `components/tabs/RankingsTab.tsx` (313 lines)

- 🟡 Filter bar wraps OK (`flex-wrap`).
- 🔴 Table (lines 203–257): country col `min-w-[220px]` + N category cols each `min-w-[180px]` = easily 700–1000px. Horizontal scroll works but country column is not sticky.
- **Fix:** sticky first column (`position: sticky; left: 0; background: var(--background)`). Add right-edge fade hint.

#### `components/tabs/RatingsTab.tsx` (586 lines)

- 🟢 Summary cards `md:grid-cols-2`. OK.
- 🟢 Current Rating card internally `md:flex-row md:items-center` — stars on top, breakdown below on mobile. OK.
- 🟡 Rating Trend Card `grid-cols-2` — 2 cells even on mobile, fine but values can be cramped with long numbers.
- 🟢 History chart `ResponsiveContainer` + `h-56`. Good.
- 🟡 Country Breakdown `lg:grid-cols-2` — on tablet stacks, fine.

#### `components/tabs/ChangesTab.tsx` (657 lines)

- 🟢 Grid `lg:grid-cols-[1fr_260px]` — below lg stacks to single column. Sidebar filters become full-width; tolerable but feels cramped.
- 🟡 `VersionCard` release notes + locale select: the `flex items-start justify-between gap-6` can squeeze on mobile — Select is `w-[160px]` fixed.
- 🟢 DiffModal uses standard Dialog, `sm:max-w-2xl`, `max-h-[85vh]` — responsive.

#### `components/tabs/VersionsTab.tsx` (52 lines) 🟢 Fine

### Pages — Settings

#### `pages/Settings.tsx` (227 lines) 🟢 OK

- Centered `max-w-2xl`. Form fields stack naturally. Sub-links grid `sm:grid-cols-2` — fine.

#### `pages/settings/ApiTokens.tsx` (213 lines)

- 🟡 Create Token form: `flex items-end gap-3` with flex-1 input + button. On <400px button wraps but label wraps too. Minor.
- 🟢 Token list rows — OK.
- 🟡 Dialog (Token Created): code block with `break-all` — good.

#### `pages/settings/Mcp.tsx` (220 lines)

- 🟢 Centered `max-w-2xl`.
- 🟡 Code blocks (`pre` with long claude command): `overflow-x-auto whitespace-pre-wrap` — on mobile wraps, usable but vertical.
- 🟢 Forms OK.

### Pages — Auth

#### `pages/auth/Login.tsx` (124 lines) 🟢 Fine
#### `pages/auth/Register.tsx` (162 lines) 🟢 Fine

### Pages — Explorer

#### `pages/explorer/Screenshots.tsx` (269 lines)

- 🟡 Filter bar: fixed `w-[200px]` search. Small — could be full-width on mobile.
- 🟢 Row carousel `overflow-x-auto` with `height: 400px` — iOS Safari momentum scroll may lag; add `overscroll-behavior-x: contain` and explicit `-webkit-overflow-scrolling: touch`.
- 🟢 `LazyScreenshot` aspect-ratio fallback: fine.

#### `pages/explorer/Icons.tsx` (255 lines) 🟢 Grid is exemplary (already responsive). Hover popover edge-detection already handles mobile (though on touch there's no hover — UX consideration: hide popover on touch). 🟡

### Pages — Landing

#### `pages/Landing.tsx` (973 lines)

Hero recently fixed ✅. Remaining sections not individually audited in this plan but expected to need work:
- Feature grids (likely `lg:grid-cols-3` without mobile baseline)
- Pricing section
- FAQ (likely OK)
- Footer (columns need `sm:grid-cols-2 md:grid-cols-4` baseline)
- Screenshot/demo frames (`BrowserFrame`) — fixed dimensions?

**Action:** add to P1 and audit after finishing auth'd app pages.

### Shared components

#### `components/AppCard.tsx` (124 lines)

- 🔴 At 375px with long app name, `ml-auto` track button can be pushed. Publisher line `truncate` limits to 30 chars. OK for publisher, not for name.
- 🟡 Meta row (category + rating + paid badge) uses `flex items-center gap-2` — wraps at narrow.
- **Fix:** `flex-col sm:flex-row` for name/button; or move button to second row always.

#### `components/ChangeCard.tsx` (198 lines)

- 🟢 Header `flex items-start justify-between gap-3` — OK.
- 🟢 Before/After `md:grid-cols-2` stacks on mobile.

#### `components/PlatformSwitcher.tsx` 🟢 Small widget, fine.

#### `components/CountrySelect.tsx`

- 🟡 Popover content `w-[240px]` — OK but on 375px screen that's 64% of width. Acceptable.
- 🟢 Uses `cmdk` command palette, scrollable.

#### `components/LanguageSelect.tsx` — similar to CountrySelect.

#### `components/SyncingOverlay.tsx` — Centered `max-w-2xl py-12` — fine.

#### `components/Breadcrumbs.tsx` — **needs parent container to have overflow guard**; the `BreadcrumbList` itself doesn't limit.

#### `components/NavUser.tsx` — handled by shadcn sidebar internally. 🟢

---

## 4. Priority Ranking

### P0 — Blockers (feature unusable on mobile)

1. **`apps/Show.tsx` header** (mobile users can't use CountrySelect/LanguageSelect/VersionSelect). Top of the list.
2. **`KeywordsTab` table** on narrow widths when competitors are added — mobile users can't read the data.
3. **`RankingsTab` table** country column not sticky — user loses country context when horizontally scrolling.

### P1 — Degraded (works, looks broken)

4. **Filter bar pattern** across all 10 list/discovery/changes pages — extract shared component OR fix inline.
5. **`apps/Show.tsx` tab bar** — add auto-scroll-to-active + edge fade.
6. **`AppCard.tsx` mobile name+button row** — stack below sm.
7. **`changes/AppChanges.tsx` + `competitors/Index.tsx` nested section indent** — reduce on mobile.
8. **Landing.tsx sections 2-6** — full audit of non-hero sections.
9. **`KeywordsTab` toolbar (ngram + compare chips + search)** — better mobile stacking.

### P2 — Polish

10. **Horizontal scroll fade hints** on all overflow-x-auto tab bars and tables.
11. **`Breadcrumbs` overflow** on deep paths.
12. **`explorer/Screenshots` momentum scroll** polish for iOS Safari.
13. **`Mcp.tsx` code blocks** — add "Copy, full view" with expand dialog on mobile.
14. **`explorer/Icons` hover popover** — hide on touch devices.
15. **Standardize touch-target sizes** — audit buttons < 32px tall (`h-7 text-xs`) and bump to `h-9 sm:h-7`.
16. **Dialog inner form layouts** — `CompetitorsTab` dialog `max-w-[420px]` vs outer `sm:max-w-sm` (384px) — minor mismatch.

---

## 5. Shared Utilities to Introduce

These will be used across many fixes. Build them first.

### 5.1 `<FilterBar>` component

```tsx
// components/FilterBar.tsx
<FilterBar>
  <FilterBar.Search value={...} onChange={...} placeholder="..." />
  <FilterBar.Controls>
    <PlatformSwitcher ... />
    <CountrySelect ... />
    <Select>...</Select>
  </FilterBar.Controls>
</FilterBar>
```

Behavior:
- Mobile (<sm): `Search` full-width row, `Controls` on horizontal scroll row (snap-x, gap-2, `-mx-4 px-4`).
- sm+: inline `flex flex-wrap items-center gap-3`.

Replaces ~10 inline filter bars.

### 5.2 `<ScrollableRow>` helper

```tsx
<ScrollableRow className="...">
  {tabs}
</ScrollableRow>
```

- Wraps `-mx-4 px-4 overflow-x-auto snap-x`
- Adds left/right fade overlays using `mask-image` (Tailwind v4 supports CSS mask utilities)
- On mount, scrolls `[data-active=true]` child into view

### 5.3 Sticky-first-column table utility

A CSS helper in `index.css`:
```css
@utility table-sticky-first {
  & thead th:first-child,
  & tbody td:first-child {
    position: sticky;
    left: 0;
    background-color: hsl(var(--background));
    z-index: 1;
  }
}
```

Apply to RankingsTab and KeywordsTab.

### 5.4 Edge fade utility

```css
@utility scroll-fade-x {
  mask-image: linear-gradient(to right, transparent, black 24px, black calc(100% - 24px), transparent);
}
```

---

## 6. Execution Sequence

### Phase 1 — Shared utilities (1 PR)
- [ ] Add `components/FilterBar.tsx`
- [ ] Add `components/ScrollableRow.tsx`
- [ ] Add `scroll-fade-x` and `table-sticky-first` utilities to `index.css`
- [ ] Add any needed `useIsMobile`-like hook uses already satisfied (`hooks/use-mobile.ts` exists)

### Phase 2 — P0 blockers (1 PR)
- [ ] Refactor `apps/Show.tsx` header — responsive 3-level layout
- [ ] Apply `table-sticky-first` to `RankingsTab` and `KeywordsTab`
- [ ] `KeywordsTab` mobile card fallback (<md)

### Phase 3 — P1 filter bar migration (1 PR)
- [ ] Replace inline filter bars with `<FilterBar>` in all 10 list/discovery/changes pages
- [ ] `apps/Show.tsx` tab bar: swap to `<ScrollableRow>` with active-into-view

### Phase 4 — P1 cards and nested layouts (1 PR)
- [ ] `AppCard.tsx` — name + button stack below sm
- [ ] Consolidate inline app cards (Discovery/Apps, publishers/Show, etc.) to use `AppCard`
- [ ] `competitors/Index.tsx` ParentGroup indent — reduce on mobile

### Phase 5 — Landing audit (1 PR)
- [ ] Audit Landing.tsx sections 2–6
- [ ] Apply responsive fixes per same principles

### Phase 6 — P2 polish (1 PR)
- [ ] Breadcrumbs overflow guard
- [ ] Screenshots momentum scroll polish
- [ ] MCP page code-block expand dialog
- [ ] Icons hover popover on touch
- [ ] Touch-target audit

---

## 7. Verification Checklist (per phase)

For each phase, verify at these viewports in DevTools:
- **375 × 667** iPhone SE
- **390 × 844** iPhone 15 Pro
- **414 × 896** iPhone 11 Pro Max
- **768 × 1024** iPad portrait
- **1024 × 768** iPad landscape
- **1280 × 800** laptop
- **1920 × 1080** desktop

**Must pass for every changed page:**
1. No horizontal scroll on the document body
2. All interactive elements tappable (≥40×40px touch target, or clustered with enough spacing)
3. All text legible (≥14px body, ≥12px captions)
4. No content cut off by viewport edges
5. Overflow-x regions have visible scroll affordance
6. Landscape orientation doesn't break anything critical

Use real devices when possible for iOS Safari momentum scroll and keyboard quirks.

---

## 8. Out of scope

- Accessibility audit (separate concern; responsive first).
- Dark mode color contrast (separate concern; already has dark tokens).
- Performance / bundle size.
- i18n / RTL support.
- `scraper-ios` / `scraper-android` / server UI (none).

---

## 9. Risks & Trade-offs

- **Shared `<FilterBar>` extraction** risks touching many files in one PR. Mitigate: migrate page-by-page in separate commits within Phase 3, each commit green.
- **`apps/Show.tsx` header refactor** is high-visibility — ship behind visual QA.
- **Sticky columns with themed backgrounds** in RankingsTab/KeywordsTab may have color bleed if parent has different bg — test in both light and dark.
- **KeywordsTab card fallback** is a large structural shift — could be split into its own PR if Phase 2 gets too large.
