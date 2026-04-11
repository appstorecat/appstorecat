# Architecture Rules (.arc/)

AI code assistants **MUST** read and follow ALL rules in this directory with full compliance.

## Project Structure

This is a **monorepo** with 4 services:

```
appstorecat/
├── backend/           # Laravel 13 API (gateway + DB owner)
├── frontend/          # React 19 SPA
├── scraper-appstore/  # App Store scraper (Fastify + TypeScript)
├── scraper-gplay/     # Google Play scraper (FastAPI + Python)
├── .arc/              # Architecture rules (this directory)
├── docker-compose.yml # Root orchestrator
└── Makefile           # Dev commands
```

## Directory Structure

### 01-core/ — Core Architecture
Foundation patterns that all backend code must follow.

- **architecture.md** — Service Layer, Connector, Job, Enum patterns
- **controllers.md** — API controllers, OpenAPI attributes, response patterns
- **models.md** — Enums, casts, scopes, relationships

### 02-api/ — API Development
Request/response handling for the REST API.

- **requests.md** — Form Request validation rules, Swagger schemas
- **resources.md** — API Resource patterns, BaseResource template
- **routes.md** — API route structure (Sanctum-protected)
- **swagger.md** — L5-Swagger conventions, schema chain, Orval generation

### 03-ui/ — UI Design System
React + shadcn/ui based design system and component conventions.

- **design-system.md** — CSS variables, color tokens, typography, dark mode, icons
- **components.md** — React components, shadcn/ui patterns, layouts

### 04-services/ — Services & Connectors
Business logic layer and data source connectors.

- **services.md** — Service structure, DI, transactions
- **connectors.md** — ConnectorInterface, HTTP-based connectors to scraper APIs

### 05-infrastructure/ — Infrastructure
Docker, configuration, jobs, middleware.

- **middleware.md** — API middleware, locale, logging
- **config.md** — Config file conventions, env-driven values
- **jobs.md** — BuildDnaJob pipeline, queue worker
- **docker.md** — Docker commands, service orchestration, Makefile-first rule

### 06-conventions/ — Coding Conventions
Standards, testing, git workflow, frontend, and tooling.

- **coding-standards.md** — PSR-12, type safety, PHP 8+ features
- **testing.md** — Pest structure, RefreshDatabase, helpers
- **git.md** — Commit format, branch workflow
- **frontend.md** — React 19, TypeScript, TanStack Query, page structure
- **tools.md** — Dev tooling, debugging

### 07-scrapers/ — Scraper Microservices
Rules for both scraper APIs.

- **scraper-apis.md** — Endpoint convention, schema unification, no-cache rule, testing

## Priority Reading Order

**Before starting any work:**
1. **01-core/architecture.md** — Understand monorepo and service patterns
2. **07-scrapers/scraper-apis.md** — Scraper conventions (if touching scrapers)

**When building backend endpoints:**
3. **01-core/controllers.md** — Controller conventions
4. **02-api/swagger.md** — OpenAPI documentation rules
5. **02-api/requests.md** — Input validation
6. **02-api/resources.md** — Response transformation

**For UI work:**
7. **03-ui/design-system.md** — Color tokens, theming
8. **03-ui/components.md** — Component patterns
9. **06-conventions/frontend.md** — React + TanStack Query

**For services/connectors:**
10. **04-services/connectors.md** — HTTP connector pattern (backend → scrapers)

## Compliance Requirements

- **MANDATORY**: All code MUST follow these rules
- **NO EXCEPTIONS**: Rules override personal preferences
- **MONOREPO**: Each service has its own Dockerfile and docker-compose.yml
- **TYPE SAFETY**: Type hints on all parameters and return types
- **ELOQUENT ONLY**: No raw DB queries (backend)
- **DOCKER**: All commands run inside Docker containers
- **SHADCN/UI**: Use existing UI components before creating custom ones
- **SWAGGER**: All backend API endpoints must have OpenAPI attributes
- **SCRAPERS**: Stateless, no cache, no database, unified schemas
- **LOCALES**: Always `xx_XX` format (e.g., `en_US`, `tr_TR`)

## Quick Reference

```bash
# Start all services
make dev

# Run backend commands
docker compose exec backend php artisan migrate
docker compose exec backend php ./vendor/bin/pint
docker compose exec backend php artisan test

# Run scraper tests
docker compose exec scraper-gplay pytest
docker compose exec scraper-appstore npm test

# All tests
make test
```

```
Service Ports:
  backend           → :7460
  frontend          → :7461
  scraper-appstore  → :7462
  scraper-gplay     → :7463
  mysql             → :7464
```

## Maintenance

When adding new patterns:
1. Document in appropriate category
2. Provide minimal code examples
3. Update this README if adding new files
4. Keep token count low (max 500 lines per file)
