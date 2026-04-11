# Frontend Service

The React single-page application provides the user interface for AppStoreCat.

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | React 19 |
| Build Tool | Vite 8 |
| Language | TypeScript |
| UI Library | shadcn/ui |
| CSS | Tailwind CSS v4 |
| API Client | Generated via Orval |

## Directory Structure

```
frontend/
├── src/
│   ├── pages/
│   │   ├── auth/            # Login, Register
│   │   ├── apps/            # App list, App detail
│   │   ├── changes/         # App changes, Competitor changes
│   │   ├── competitors/     # Competitor list
│   │   ├── discovery/       # App search, Publisher search, Trending
│   │   ├── explorer/        # Icons, Screenshots
│   │   ├── publishers/      # Publisher list, Publisher detail
│   │   └── Settings.tsx     # User settings
│   ├── components/          # Shared UI components
│   └── lib/                 # API client, utilities
├── orval.config.ts          # API client generation config
└── Dockerfile               # Development container
```

## Pages

| Route | Page | Description |
|-------|------|-------------|
| `/login` | Login | User authentication |
| `/register` | Register | Account creation |
| `/apps` | App List | Tracked apps with platform filter |
| `/apps/:platform/:id` | App Detail | Full app view with tabs |
| `/discovery/apps` | App Discovery | Search for apps |
| `/discovery/publishers` | Publisher Discovery | Search for publishers |
| `/discovery/trending` | Trending Charts | Browse trending charts |
| `/changes/apps` | App Changes | Store listing changes for tracked apps |
| `/changes/competitors` | Competitor Changes | Changes for competitor apps |
| `/competitors` | Competitors | All competitor apps |
| `/explorer/screenshots` | Screenshots | Browse screenshots across apps |
| `/explorer/icons` | Icons | Browse app icons across apps |
| `/publishers` | Publishers | Publisher list |
| `/publishers/:platform/:id` | Publisher Detail | Publisher info + app catalog |
| `/settings` | Settings | User profile and security |

## App Detail Tabs

The app detail page has multiple tabs:

- **Listing** — Store listing with language selector
- **Versions** — Version history
- **Reviews** — User reviews with filters
- **Keywords** — Keyword density analysis
- **Competitors** — Competitor management
- **Changes** — Store listing change history

## Running

```bash
make dev-frontend    # Start frontend
make logs-frontend   # View frontend logs
```

The frontend is available at http://localhost:7461 and proxies API requests to the backend.
