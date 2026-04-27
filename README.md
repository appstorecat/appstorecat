<p align="center">
  <img src="web/public/appstorecat-icon.svg" width="80" height="80" alt="AppStoreCat" />
</p>

<h1 align="center">AppStoreCat</h1>

<p align="center">
  Open-source, self-hosted App Store &amp; Google Play intelligence — with a 32-tool MCP server for Claude Code.
</p>

<p align="center">
  <a href="https://github.com/appstorecat/appstorecat/releases"><img src="https://img.shields.io/github/v/release/appstorecat/appstorecat?style=flat-square&color=10b981" alt="Release" /></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/appstorecat/appstorecat?style=flat-square&color=10b981" alt="MIT License" /></a>
  <a href="https://github.com/appstorecat/appstorecat/stargazers"><img src="https://img.shields.io/github/stars/appstorecat/appstorecat?style=flat-square&color=10b981" alt="Stars" /></a>
  <a href="https://www.npmjs.com/package/@appstorecat/mcp"><img src="https://img.shields.io/npm/v/@appstorecat/mcp?style=flat-square&color=cb3837&label=%40appstorecat%2Fmcp" alt="MCP npm" /></a>
  <a href="https://ghcr.io/appstorecat/server"><img src="https://img.shields.io/badge/docker-ghcr.io-blue?style=flat-square&logo=docker" alt="Docker" /></a>
</p>

<p align="center">
  <a href="https://appstore.cat"><b>Live Demo</b></a> ·
  <a href="https://appstorecat.github.io/appstorecat/">Docs</a> ·
  <a href="https://appstorecat.github.io/appstorecat/services/mcp">MCP Server</a> ·
  <a href="CHANGELOG.md">Changelog</a>
</p>

<p align="center">
  <img src="screenshots/hero-dashboard.jpeg" alt="AppStoreCat dashboard" width="100%" />
</p>

---

## Quick Start

Pick one — either way you're up in ~60 seconds.

**Option A · One-line install** (uses [`install.sh`](docs/en/getting-started/install-script.md))

```bash
curl -sSL https://appstore.cat/install.sh | sh
```

**Option B · Manual** (when you want to read every step)

```bash
git clone https://github.com/appstorecat/appstorecat.git
cd appstorecat
cp .env.development.example .env
make setup
make dev
```

Then open <http://localhost:7461>.

Requires Docker, git, make, curl. Tested on macOS &amp; Linux. Detailed walkthrough → [installation guide](docs/en/getting-started/installation.md).

## MCP Quick Start

Give Claude Code direct access to your tracked app data.

```bash
claude mcp add appstorecat \
  -e APPSTORECAT_API_URL=http://localhost:7460/api/v1 \
  -e APPSTORECAT_API_TOKEN=<your-token> \
  -- npx -y @appstorecat/mcp
```

Create the API token from the web UI: **Settings → API Tokens → Create**.

Then ask Claude:

> *What changed on ChatGPT's App Store listing this week?*
> *Top 3 trending free iOS apps in the US?*
> *Compare keyword density between Threads and Instagram.*

32 tools (28 read · 4 write — track/untrack apps and add/remove competitors), Swagger-strict, chain-first. Full reference → [docs/en/services/mcp.md](docs/en/services/mcp.md).

## What's inside

| | |
|---|---|
| **Discovery** | Search both stores, browse trending charts, import publisher catalogs · [docs](docs/en/features/app-discovery.md) |
| **Competitor tracking** | Side-by-side competitor monitoring · [docs](docs/en/features/competitor-tracking.md) |
| **Change detection** | Per-locale diffs of title, screenshots, version, price · [docs](docs/en/features/change-detection.md) |
| **Trending charts** | Daily snapshots of top free / paid / grossing · [docs](docs/en/features/trending-charts.md) |
| **App rankings** | Per-app pivot across countries × collections × categories · [docs](docs/en/features/app-rankings.md) |
| **Store listings** | Multi-locale listings + per-country availability · [docs](docs/en/features/store-listings.md) |
| **Ratings** | Per-country rating history with star breakdown · [docs](docs/en/features/ratings.md) |
| **Keyword density** | 1/2/3-gram, 50-language stop-words, 5-app comparison · [docs](docs/en/features/keyword-density.md) |
| **Publisher discovery** | Search, browse, bulk-import entire catalogs · [docs](docs/en/features/publisher-discovery.md) |
| **MCP server** | 32 tools (28 read + 4 write) for Claude Code, Cursor, Continue · [docs](docs/en/services/mcp.md) |

## Architecture

```
Web :7461 ──▶ Server API :7460 ──▶ scraper-ios :7462
                  │           ──▶ scraper-android :7463
                  ▼
              MySQL :7464  +  Redis :7465
```

| Service | Stack |
|---|---|
| `server/` | Laravel 13, PHP 8.4, Sanctum, L5-Swagger |
| `web/` | React 19, Vite, TypeScript, shadcn/ui, Tailwind v4 |
| `scraper-ios/` | Fastify 5, Node.js, `app-store-scraper` |
| `scraper-android/` | FastAPI, Python, `gplay-scraper` |
| `mcp/` | MCP SDK, TypeScript (npm: `@appstorecat/mcp`) |

Deep dive → [docs/en/architecture/overview.md](docs/en/architecture/overview.md).

## Documentation

- **Getting started** — [installation](docs/en/getting-started/installation.md) · [install script](docs/en/getting-started/install-script.md) · [configuration](docs/en/getting-started/configuration.md) · [quick start](docs/en/getting-started/quick-start.md)
- **Architecture** — [overview](docs/en/architecture/overview.md) · [data model](docs/en/architecture/data-model.md) · [data collection](docs/en/architecture/data-collection.md) · [queue system](docs/en/architecture/queue-system.md) · [connectors](docs/en/architecture/connectors.md) · [sync pipeline](docs/en/architecture/sync-pipeline.md)
- **Services** — [server](docs/en/services/server.md) · [web](docs/en/services/web.md) · [scraper-ios](docs/en/services/scraper-ios.md) · [scraper-android](docs/en/services/scraper-android.md) · [MCP](docs/en/services/mcp.md)
- **API** — [endpoints](docs/en/api/endpoints.md) · [authentication](docs/en/api/authentication.md) · [scraper APIs](docs/en/api/scraper-apis.md)
- **Deployment** — [Docker](docs/en/deployment/docker.md) · [production](docs/en/deployment/production.md) · [troubleshooting](docs/en/deployment/troubleshooting.md)
- **Reference** — [environment variables](docs/en/reference/environment-variables.md) · [Makefile commands](docs/en/reference/makefile-commands.md) · [App Store countries](docs/en/reference/app-store-countries.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Architecture rules for code contributions live in [`.arc/`](.arc/README.md).

## Security

See [SECURITY.md](SECURITY.md) for responsible disclosure.

## License

[MIT](LICENSE) — your code, your data, your servers.
