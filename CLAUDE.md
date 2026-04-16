# CLAUDE.md

## Project

AppStoreCat — Open source app intelligence toolkit. Monorepo with 4 services: Laravel API server, React web frontend, and two independent scraper microservices (App Store + Google Play).

## Architecture

```
Web :7461 → Server (Laravel API) :7460 → scraper-ios :7462
                                        → scraper-android :7463
                   ↓
                MySQL :7464
```

- **Server** acts as API gateway — all store data flows through scraper microservices
- **Web** communicates only with Server API
- **Scrapers** are stateless — no database, no cache, just scrape and return
- **Database** is owned exclusively by Server (Laravel)

## Stack

| Service | Tech | Port |
|---------|------|------|
| **server/** | Laravel 13, PHP 8.4, MySQL 8.4, Sanctum, L5-Swagger | 7460 |
| **web/** | React 19, Vite 8, TypeScript, shadcn/ui, Tailwind CSS v4 | 7461 |
| **scraper-ios/** | Node.js, Fastify 5, TypeScript, app-store-scraper | 7462 |
| **scraper-android/** | Python, FastAPI, gplay-scraper | 7463 |
| **mysql** | MySQL 8.4 | 7464 |

## Monorepo Structure

```
appstorecat/
├── server/            # Laravel API (gateway + DB owner)
├── web/               # React SPA
├── scraper-ios/       # App Store scraper microservice
├── scraper-android/   # Google Play scraper microservice
├── .arc/              # Architecture rules (all services)
├── docker-compose.yml # Root orchestrator
├── Makefile           # Dev commands — single entry point
└── docs/              # Full documentation
```

## Commands

**RULE: Always use `make` commands. Never run bare `php artisan`, `composer`, or `docker compose exec` directly.**

### Essentials

```bash
make setup              # First time: build + install + migrate
make dev                # Start all services
make down               # Stop all
make restart            # Restart all
make ps                 # Service status
make logs               # Follow all logs
```

### Server (Laravel)

```bash
make artisan migrate                    # Any artisan command — direct pass-through
make artisan migrate:fresh --seed       # With flags
make artisan make:model Foo -mfc        # Generate model + migration + factory + controller
make artisan route:list                 # List routes
make artisan queue:restart              # Restart workers
make artisan tinker                     # Interactive shell

make composer require foo/bar           # Any composer command — direct pass-through
make composer update                    # Update dependencies

make tinker             # Shortcut for artisan tinker
make shell              # Open shell in server container
make migrate            # Run migrations
make seed               # Run seeders
make fresh              # migrate:fresh --seed
make cache-clear        # Clear all caches
make route-list         # Show routes
make queue-restart      # Restart queue workers
make schedule           # Run scheduler once
```

### Web

```bash
make npm install axios                  # Any npm command — direct pass-through
make npm run build                      # Build web
```

### API Docs Pipeline

```bash
make swagger            # Generate OpenAPI docs (L5-Swagger)
make api-generate       # Generate TypeScript client (Orval)
make api                # Both: swagger + api-generate
```

### Code Quality

```bash
make lint               # All linters (pint + eslint)
make pint               # PHP code style
make lint-web           # ESLint
make test               # All tests (server + scrapers)
make test-server        # Pest (PHPUnit)
make test-android       # pytest
make test-ios           # vitest
```

### Database

```bash
make mysql              # Open MySQL CLI
make redis-cli          # Open Redis CLI
```

### Production

```bash
make version            # Show current version
make build-prod         # Build + push multi-platform images
make release v=0.0.3    # Version bump + build + push + git tag
```

## Architecture Rules

**CRITICAL:** Read `.arc/README.md` for complete architecture documentation.

The `.arc/` directory contains rules organized in 7 categories:

1. **01-core/** — Architecture patterns, Controllers, Models
2. **02-api/** — Form Requests, Resources, Routes, Swagger
3. **03-ui/** — React components, Design system
4. **04-services/** — Services, Connectors
5. **05-infrastructure/** — Config, Jobs, Middleware, Docker
6. **06-conventions/** — Coding standards, Testing, Git, Frontend
7. **07-scrapers/** — Scraper microservice rules

## Queue Architecture

All queues are **platform-separated** (iOS and Android run independently):

| Queue | Purpose |
|-------|---------|
| `default` | General jobs |
| `discover` | App discovery |
| `sync-discovery-ios` | Sync untracked iOS apps |
| `sync-discovery-android` | Sync untracked Android apps |
| `sync-tracked-ios` | Sync tracked iOS apps |
| `sync-tracked-android` | Sync tracked Android apps |
| `charts-ios` | iOS trending chart snapshots |
| `charts-android` | Android trending chart snapshots |

**Rule:** Every new scraper-related job MUST be platform-separated. iOS and Android scrapers have independent rate limits and should never block each other. Use `{queue}-ios` and `{queue}-android` naming convention.

## Quality Checks

Run before committing or when explicitly asked:

```bash
make lint               # All linters
make test               # All tests
make test-server        # Pest only
make test-android       # pytest only
make test-ios           # vitest only
```

## Makefile-First Rule

If a command is run more than twice via `docker compose exec`, it MUST be added to the Makefile as a named target. The Makefile is the single source of truth for all project commands. This keeps workflows discoverable and consistent.
