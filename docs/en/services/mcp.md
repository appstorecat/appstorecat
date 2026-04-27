# MCP Server

The AppStoreCat MCP (Model Context Protocol) server gives AI tools like Claude Code direct access to app intelligence data.

## Overview

| | |
|---|---|
| **Package** | `@appstorecat/mcp` on npm |
| **Transport** | stdio (spawned as a local process) |
| **Auth** | Sanctum bearer token via env var |
| **Tools** | 32 tools (28 read-only + 4 write), Swagger-strict, chain-first |

```
Claude Code (stdio) → MCP Server (local) → Laravel API (local or remote)
                                                  ↓
                                             auth:sanctum
```

The MCP server does **not** run in Docker. It is a local Node.js process that Claude Code spawns over stdio.

## Installation

### Option A: Claude Code CLI

```bash
claude mcp add appstorecat \
  -e APPSTORECAT_API_URL=https://server.appstore.cat/api/v1 \
  -e APPSTORECAT_API_TOKEN=your-token \
  -- npx -y @appstorecat/mcp
```

### Option B: Manual Config

Add to `.claude/settings.json` or the project's `.mcp.json`:

```json
{
  "mcpServers": {
    "appstorecat": {
      "command": "npx",
      "args": ["-y", "@appstorecat/mcp"],
      "env": {
        "APPSTORECAT_API_URL": "https://server.appstore.cat/api/v1",
        "APPSTORECAT_API_TOKEN": "your-token-here"
      }
    }
  }
}
```

## Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APPSTORECAT_API_TOKEN` | Yes | — | Sanctum API token (created from the web UI) |
| `APPSTORECAT_API_URL` | No | `http://localhost:7460/api/v1` | API base URL |

## Design Principles

Every tool follows two rules enforced at the schema level:

- **Swagger-strict** — each tool's zod input mirrors the Swagger parameter list exactly. Nothing invented, nothing dropped. Integer fields reject strings, enums reject free-form text, dates enforce `YYYY-MM-DD`.
- **Chain-first** — responses are passed through as-is (`app_id`, `external_id`, `version_id`, `category_id`, `publisher.external_id` are never stripped). Each tool description ends with a "use with: {tool_a}, {tool_b}" hint so the caller can plan multi-step lookups.

## Read vs Write

The 32 tools split as **28 read-only + 4 write**. Read-only tools (everything below except the ones marked ✏️) carry `readOnlyHint: true`, so MCP clients render them with the safe-to-call indicator.

The 4 write tools — `track_app`, `untrack_app`, `add_competitor`, `remove_competitor` — carry `readOnlyHint: false`. Destructive operations (`untrack_app`, `remove_competitor`) additionally carry `destructiveHint: true`. Idempotent ones carry `idempotentHint: true`. Claude Code surfaces these hints to the user and asks for confirmation before invoking write tools (unless the user has explicitly allowlisted them).

A typical chained-write flow looks like:

```
search_store_apps(term: "Threads", platform: "ios")
  → external_id = "6446901002"

track_app(platform: "ios", external_id: "6446901002")
  → 204

get_app(platform: "ios", external_id: "6446901002")
  → returns internal id = 42

# Track the competitor first so it has a row
track_app(platform: "ios", external_id: "835599320")  # TikTok
get_app(platform: "ios", external_id: "835599320")
  → returns internal id = 43

add_competitor(platform: "ios", external_id: "6446901002",
               competitor_app_id: 43, relationship: "direct")
```

`competitor_app_id` is the **internal** numeric id from `get_app`, not the store-side `external_id`. The Laravel API enforces this so foreign keys stay valid.

## Available Tools

### Reference

| Tool | Description |
|------|-------------|
| `list_categories` | Store categories (App Store + Google Play). Feeds `category_id` into `get_charts`, `browse_screenshots`, `browse_icons`. |
| `list_countries` | Supported countries (ISO-2 codes). Feeds `country_code` into any location-aware tool. |

### Apps

| Tool | Description |
|------|-------------|
| `list_tracked_apps` | Apps tracked by the authenticated user. Returns internal `id` and `{platform, external_id}` for chaining. |
| `track_app` ✏️ | Add `{platform, external_id}` to the user's watchlist. Resolves and creates the app from the store if it doesn't exist yet. Triggers an automatic sync. |
| `untrack_app` ✏️ | Remove the app from the user's watchlist (and any competitor relationships involving it). Idempotent. |
| `search_store_apps` | Keyword search against a store (`term`, `platform`, `country_code`, `exclude_external_ids[]`). |
| `get_app` | Full app metadata: publisher, category, versions, rating, `unavailable_countries`, competitors (when tracked). |
| `get_app_listing` | Store listing for a `country_code` + `locale` (title, subtitle, description, screenshots, whats_new, `version_id`). |
| `get_app_sync_status` | Sync pipeline status for the app. |
| `get_app_rankings` | Rank positions across charts (filterable by `date`, `collection`). |

### Competitors

| Tool | Description |
|------|-------------|
| `list_app_competitors` | Competitors of a specific app. |
| `list_all_competitors` | All tracked competitor groups `{parent, competitors[]}`. |
| `add_competitor` ✏️ | Add a competitor to a tracked app. Requires the **internal** `competitor_app_id` (from `get_app`). Optional `relationship`: `direct`, `indirect`, `aspiration` (default `direct`). |
| `remove_competitor` ✏️ | Remove a competitor relationship. `competitor_id` is the relationship row id from `list_app_competitors`. Idempotent. |

### Changes

| Tool | Description |
|------|-------------|
| `list_app_changes` | Store listing changes for tracked apps (filter by `field`, `platform`, `search`, internal `app_id`; paginated). |
| `list_competitor_changes` | Same shape, scoped to competitor apps. |

### Charts

| Tool | Description |
|------|-------------|
| `get_charts` | Top charts (`top_free` / `top_paid` / `top_grossing`) for a `country_code`, optional `category_id`. |

### Ratings

| Tool | Description |
|------|-------------|
| `get_rating_summary` | Current rating + rating count + histogram for an app. |
| `get_rating_history` | Daily rating series (`days` 1–90, default 30). |
| `get_rating_country_breakdown` | Rating breakdown by country (iOS only). |

### Keywords

| Tool | Description |
|------|-------------|
| `get_app_keywords` | Keyword density for an app listing (`locale`, `ngram` 1–3, `sort`, `order`, `per_page`, `page`; optional `version_id`). |
| `compare_app_keywords` | Side-by-side keyword comparison across up to 5 apps (`app_ids[]` internal ids, `version_ids` map). |

### Publishers

| Tool | Description |
|------|-------------|
| `search_publishers` | Publisher search across stores (`term`, `platform`, `country_code`). |
| `list_user_publishers` | Publishers of the user's tracked apps. |
| `get_publisher` | Publisher detail with tracked apps owned by the caller. |
| `get_publisher_store_apps` | Full store catalog for a publisher (includes `is_tracked` flag per app). |

### Explorer

| Tool | Description |
|------|-------------|
| `browse_screenshots` | Paginated screenshot feed across tracked apps (filter by `platform`, `category_id`, `search`). |
| `browse_icons` | Paginated icon feed across tracked apps. |

### Dashboard

| Tool | Description |
|------|-------------|
| `get_dashboard` | Dashboard summary — app counts, recent changes. `recent_changes[].app_id` chains into `list_app_changes`. |

## Development

```bash
make mcp-install   # Install dependencies
make mcp-build     # Compile TypeScript
make mcp-dev       # Run in dev mode (tsx watch)
```

### Project Structure

```
mcp/
├── src/
│   ├── index.ts          # Entry point — server init + stdio transport
│   ├── client.ts         # HTTP client (fetch + bearer auth, array/object query serializers, path builder)
│   ├── register.ts       # Registers all tool modules
│   └── tools/
│       ├── _schemas.ts      # Shared zod primitives (Platform, ExternalId, DateStr, Ngram, …)
│       ├── apps.ts          # list_tracked_apps, search_store_apps, get_app, get_app_listing, get_app_sync_status, get_app_rankings
│       ├── changes.ts       # list_app_changes, list_competitor_changes
│       ├── charts.ts        # get_charts
│       ├── competitors.ts   # list_app_competitors, list_all_competitors
│       ├── dashboard.ts     # get_dashboard
│       ├── explorer.ts      # browse_screenshots, browse_icons
│       ├── keywords.ts      # get_app_keywords, compare_app_keywords
│       ├── publishers.ts    # search_publishers, list_user_publishers, get_publisher, get_publisher_store_apps
│       ├── ratings.ts       # get_rating_summary, get_rating_history, get_rating_country_breakdown
│       └── reference.ts     # list_categories, list_countries
├── package.json
└── tsconfig.json
```

## Publishing

The MCP package is part of the release pipeline:

```bash
make release v=1.2.0
# → Builds Docker images
# → Updates mcp/package.json version
# → npm publish @appstorecat/mcp
# → git tag + push
```
