# MCP Server

AppStoreCat MCP (Model Context Protocol) server enables AI tools like Claude Code to access app intelligence data directly.

## Overview

| | |
|---|---|
| **Package** | `@appstorecat/mcp` on npm |
| **Transport** | stdio (spawned as local process) |
| **Auth** | Sanctum bearer token via env var |
| **Tools** | 19 read-only tools |

```
Claude Code (stdio) → MCP Server (local) → Laravel API (local or remote)
                                                  ↓
                                             auth:sanctum
```

The MCP server does **not** run in Docker. It runs as a local Node.js process that Claude Code spawns via stdio.

## Installation

### Option A: Claude Code CLI

```bash
claude mcp add appstorecat \
  -e APPSTORECAT_API_URL=https://server.appstore.cat/api/v1 \
  -e APPSTORECAT_API_TOKEN=your-token \
  -- npx -y @appstorecat/mcp
```

### Option B: Manual Config

Add to `.claude/settings.json` or project `.mcp.json`:

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
| `APPSTORECAT_API_TOKEN` | Yes | — | Sanctum API token (created in web UI) |
| `APPSTORECAT_API_URL` | No | `http://localhost:7460/api/v1` | API base URL |

## Available Tools

### Discovery

| Tool | Description |
|------|-------------|
| `search_apps` | Search apps by keyword on App Store or Google Play |
| `search_publishers` | Search for publishers/developers |
| `get_charts` | Get trending/top charts (top_free, top_paid, top_grossing) |

### Apps

| Tool | Description |
|------|-------------|
| `list_apps` | List all tracked apps |
| `get_app` | Get detailed app info (metadata, ratings, version history) |
| `get_app_listing` | Get store listing (description, screenshots, what's new) |

### Competitors

| Tool | Description |
|------|-------------|
| `get_app_competitors` | Get competitors for a specific app |
| `list_all_competitors` | List all competitor relationships |

### Reviews

| Tool | Description |
|------|-------------|
| `get_app_reviews` | Get user reviews (filterable by rating, sortable) |
| `get_review_summary` | Get review summary (rating distribution, averages) |

### Changes

| Tool | Description |
|------|-------------|
| `get_app_changes` | Recent store listing changes for tracked apps |
| `get_competitor_changes` | Recent changes for competitor apps |

### Explorer

| Tool | Description |
|------|-------------|
| `explore_screenshots` | Browse app screenshots from tracked apps |
| `explore_icons` | Browse app icons from tracked apps |

### Publishers

| Tool | Description |
|------|-------------|
| `list_publishers` | List known publishers with app counts |
| `get_publisher` | Get publisher details |
| `get_publisher_apps` | Get all apps by a publisher |

### Meta

| Tool | Description |
|------|-------------|
| `list_countries` | List supported countries/regions |
| `list_store_categories` | List all app store categories |
| `get_dashboard` | Dashboard overview (app count, recent changes/reviews) |

## Development

```bash
make mcp-install   # Install dependencies
make mcp-build     # Build TypeScript
make mcp-dev       # Run in dev mode (tsx watch)
```

### Project Structure

```
mcp/
├── src/
│   ├── index.ts          # Entry point — server init + stdio transport
│   ├── client.ts         # HTTP client (fetch + bearer auth)
│   ├── register.ts       # Registers all tool modules
│   └── tools/
│       ├── apps.ts       # search_apps, list_apps, get_app, get_app_listing
│       ├── charts.ts     # get_charts
│       ├── changes.ts    # get_app_changes, get_competitor_changes
│       ├── competitors.ts # get_app_competitors, list_all_competitors
│       ├── dashboard.ts  # get_dashboard
│       ├── explorer.ts   # explore_screenshots, explore_icons
│       ├── meta.ts       # list_countries, list_store_categories
│       ├── publishers.ts # search/list/get publishers
│       └── reviews.ts    # get_app_reviews, get_review_summary
├── package.json
└── tsconfig.json
```

## Publishing

MCP package is included in the release pipeline:

```bash
make release v=1.0.2
# → builds Docker images
# → bumps mcp/package.json version
# → npm publish @appstorecat/mcp
# → git tag + push
```
