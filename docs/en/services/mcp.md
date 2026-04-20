# MCP Server

The AppStoreCat MCP (Model Context Protocol) server gives AI tools like Claude Code direct access to app intelligence data.

## Overview

| | |
|---|---|
| **Package** | `@appstorecat/mcp` on npm |
| **Transport** | stdio (spawned as a local process) |
| **Auth** | Sanctum bearer token via env var |
| **Tools** | Read-only tool set |

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

## Available Tools

### Discovery

| Tool | Description |
|------|-------------|
| `search_apps` | Search apps by keyword on the App Store or Google Play (`country_code` parameter) |
| `search_publishers` | Search publishers/developers (`country_code` parameter) |
| `get_charts` | Fetch trending/top charts (top_free, top_paid, top_grossing; `country_code` parameter) |

### Apps

| Tool | Description |
|------|-------------|
| `list_apps` | List all tracked apps |
| `get_app` | Detailed app info (metadata, ratings, version history, `unavailable_countries`) |
| `get_app_listing` | Fetch the store listing (description, screenshots, what's new; `country_code` + `locale`) |

### Competitors

| Tool | Description |
|------|-------------|
| `get_app_competitors` | Get competitors for a specific app |
| `list_all_competitors` | List all competitor relationships |

### Changes

| Tool | Description |
|------|-------------|
| `get_app_changes` | Recent store changes for tracked apps |
| `get_competitor_changes` | Recent changes for competitor apps |

### Explorer

| Tool | Description |
|------|-------------|
| `explore_screenshots` | Browse screenshots from tracked apps |
| `explore_icons` | Browse icons from tracked apps |

### Publishers

| Tool | Description |
|------|-------------|
| `list_publishers` | List known publishers with their app counts |
| `get_publisher` | Fetch publisher details |
| `get_publisher_apps` | Fetch all apps for a publisher |

### Meta

| Tool | Description |
|------|-------------|
| `list_countries` | List supported countries/regions (the internal `zz` sentinel is filtered) |
| `list_store_categories` | List all app store categories |
| `get_dashboard` | Dashboard summary (app count, recent changes) |

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
│       └── publishers.ts # search/list/get publishers
├── package.json
└── tsconfig.json
```

## Publishing

The MCP package is part of the release pipeline:

```bash
make release v=1.0.2
# → Builds Docker images
# → Updates mcp/package.json version
# → npm publish @appstorecat/mcp
# → git tag + push
```
