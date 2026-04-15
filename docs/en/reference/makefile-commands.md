# Makefile Commands

All commands are run from the project root directory.

## Full Stack

| Command | Description |
|---------|-------------|
| `make setup` | First-time setup: build, install deps, generate key, migrate |
| `make dev` | Start all services in background |
| `make down` | Stop all services |
| `make restart` | Stop and start all services |
| `make build` | Build/rebuild all Docker containers |
| `make ps` | Show running container status |
| `make logs` | Follow logs for all services |

## Individual Services

| Command | Description |
|---------|-------------|
| `make dev-backend` | Start backend + MySQL + Redis |
| `make dev-frontend` | Start frontend only |
| `make dev-appstore` | Start App Store scraper only |
| `make dev-gplay` | Start Google Play scraper only |

## Backend (Laravel)

| Command | Description |
|---------|-------------|
| `make install` | Install all dependencies (Composer + npm) |
| `make key` | Generate Laravel APP_KEY |
| `make migrate` | Run database migrations |
| `make seed` | Run database seeders |
| `make fresh` | Fresh migration with seeding |
| `make artisan cmd="..."` | Run any artisan command (e.g., `make artisan cmd="make:model Foo"`) |
| `make tinker` | Open Laravel Tinker REPL |
| `make pint` | Run PHP code style fixer (Laravel Pint) |

## Tests

| Command | Description |
|---------|-------------|
| `make test` | Run all tests (backend + both scrapers) |
| `make test-backend` | Run PHPUnit tests only |
| `make test-appstore` | Run App Store scraper tests (vitest) |
| `make test-gplay` | Run Google Play scraper tests (pytest) |

## Logs

| Command | Description |
|---------|-------------|
| `make logs` | Follow all service logs |
| `make logs-backend` | Follow backend logs only |
| `make logs-frontend` | Follow frontend logs only |
| `make logs-appstore` | Follow App Store scraper logs |
| `make logs-gplay` | Follow Google Play scraper logs |

## Cleanup

| Command | Description |
|---------|-------------|
| `make clean` | Stop containers, remove volumes and orphans |
| `make nuke` | Full cleanup: containers, volumes, and local images |

## Production

| Command | Description |
|---------|-------------|
| `make version` | Display current version from VERSION file |
| `make build-prod` | Build and push multi-platform images to Docker Hub |
| `make release v=X.Y.Z` | Full release: bump version, build, push, git tag |
