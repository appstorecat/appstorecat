# @appstorecat/mcp

MCP (Model Context Protocol) server for [AppStoreCat](https://github.com/appstorecat/appstorecat) — open-source App Store & Google Play intelligence toolkit. Gives Claude Code, Cursor, and other MCP-compatible AI tools direct access to your tracked app data.

## Quick install with Claude Code

```bash
claude mcp add appstorecat \
  -e APPSTORECAT_API_URL=http://localhost:7460/api/v1 \
  -e APPSTORECAT_API_TOKEN=your-token \
  -- npx -y @appstorecat/mcp
```

Then restart Claude Code and ask:

> *What are the top 3 trending free iOS apps in the US right now?*
>
> *What changed on ChatGPT's App Store listing this week?*
>
> *Compare keyword density between the top 3 weather apps.*

## Manual config

Add to `.claude/settings.json` or your project's `.mcp.json`:

```json
{
  "mcpServers": {
    "appstorecat": {
      "command": "npx",
      "args": ["-y", "@appstorecat/mcp"],
      "env": {
        "APPSTORECAT_API_URL": "http://localhost:7460/api/v1",
        "APPSTORECAT_API_TOKEN": "your-token-here"
      }
    }
  }
}
```

## Prerequisites

You need a running AppStoreCat server. Two options:

- **Self-host (recommended):** [`curl -sSL https://appstore.cat/install.sh | sh`](https://github.com/appstorecat/appstorecat#quick-start) — 60-second Docker install
- **Hosted demo:** Sign up at [appstore.cat](https://appstore.cat) and use `APPSTORECAT_API_URL=https://server.appstore.cat/api/v1`

The API token is created from the web UI: **Settings → API Tokens → Create**.

## Environment variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APPSTORECAT_API_TOKEN` | Yes | — | Sanctum bearer token |
| `APPSTORECAT_API_URL` | No | `http://localhost:7460/api/v1` | API base URL |

## Tools (32)

28 read-only + 4 write. All tools are Swagger-strict (zod inputs mirror the OpenAPI parameters exactly) and chain-first (response IDs are preserved so the LLM can plan multi-step lookups). Write tools (`track_app`, `untrack_app`, `add_competitor`, `remove_competitor`) carry standard MCP write hints — Claude Code will ask for confirmation before invoking them.

| Category | Tools |
|---|---|
| **Apps** | `list_tracked_apps`, `search_store_apps`, `get_app`, `get_app_listing`, `get_app_sync_status`, `get_app_rankings`, `track_app` ✏️, `untrack_app` ✏️ |
| **Competitors** | `list_app_competitors`, `list_all_competitors`, `add_competitor` ✏️, `remove_competitor` ✏️ |
| **Changes** | `list_app_changes`, `list_competitor_changes` |
| **Charts** | `get_charts` |
| **Ratings** | `get_rating_summary`, `get_rating_history`, `get_rating_country_breakdown` |
| **Keywords** | `get_app_keywords`, `compare_app_keywords` |
| **Publishers** | `search_publishers`, `list_user_publishers`, `get_publisher`, `get_publisher_store_apps` |
| **Explorer** | `browse_screenshots`, `browse_icons` |
| **Dashboard** | `get_dashboard` |
| **Reference** | `list_categories`, `list_countries` |

Full tool reference with inputs/outputs: [docs/services/mcp.md](https://github.com/appstorecat/appstorecat/blob/master/docs/en/services/mcp.md).

## Architecture

```
Claude Code  ──stdio──▶  @appstorecat/mcp  ──HTTPS──▶  AppStoreCat Laravel API
                                                              │
                                                         auth: Sanctum
```

The MCP server is a stateless local Node.js process. It does **not** store data, cache responses, or run in Docker. It just translates MCP tool calls into authenticated HTTP requests to your AppStoreCat instance.

## License

MIT — see [LICENSE](https://github.com/appstorecat/appstorecat/blob/master/LICENSE).

## Links

- [Repository](https://github.com/appstorecat/appstorecat)
- [Documentation](https://github.com/appstorecat/appstorecat/tree/master/docs/en)
- [Live demo](https://appstore.cat)
- [Issues](https://github.com/appstorecat/appstorecat/issues)
