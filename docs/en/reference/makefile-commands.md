# Makefile Commands

All commands are run from the project root.

## Full Stack

| Command | Description |
|---------|-------------|
| `make setup` | First setup: build, install dependencies, generate key, run migrations |
| `make dev` | Start all services in the background |
| `make down` | Stop all services |
| `make restart` | Stop and restart all services |
| `make build` | Build/rebuild all Docker containers |
| `make ps` | Show the status of running containers |
| `make logs` | Follow the logs of all services |

## Individual Services

| Command | Description |
|---------|-------------|
| `make dev-server` | Start backend + MySQL + Redis |
| `make dev-web` | Start only the web |
| `make dev-ios` | Start only the App Store scraper |
| `make dev-android` | Start only the Google Play scraper |

## Backend (Laravel)

| Command | Description |
|---------|-------------|
| `make install` | Install all dependencies (Composer + npm) |
| `make key` | Generate the Laravel APP_KEY |
| `make migrate` | Run database migrations |
| `make seed` | Run database seeders |
| `make fresh` | Fresh migration with seed |
| `make artisan ...` | Run any artisan command (e.g., `make artisan migrate:fresh --seed`) |
| `make composer ...` | Run any composer command (e.g., `make composer require foo/bar`) |
| `make cache-clear` | Clear all caches (`optimize:clear`) |
| `make route-list` | List routes |
| `make queue-restart` | Restart queue workers |
| `make schedule` | Run the scheduler once |
| `make tinker` | Open the Laravel Tinker REPL |
| `make shell` | Open a shell in the server container |

## Web

| Command | Description |
|---------|-------------|
| `make npm ...` | Run any npm command in the web container (e.g., `make npm install axios`) |

## API Documentation

| Command | Description |
|---------|-------------|
| `make swagger` | Generate OpenAPI documentation (L5-Swagger) |
| `make api-generate` | Generate the TypeScript API client (Orval) |
| `make api` | Run both: swagger + api-generate |

## Code Quality

| Command | Description |
|---------|-------------|
| `make lint` | Run all linters (pint + eslint) |
| `make pint` | Run the PHP code style fixer (Laravel Pint) |
| `make lint-web` | Run ESLint (web) |

## Logs

| Command | Description |
|---------|-------------|
| `make logs` | Follow the logs of all services |
| `make logs-server` | Follow only the server logs |
| `make logs-web` | Follow only the web logs |
| `make logs-ios` | Follow the App Store scraper logs |
| `make logs-android` | Follow the Google Play scraper logs |

## Cleanup

| Command | Description |
|---------|-------------|
| `make clean` | Stop containers, remove volumes and orphan containers |
| `make nuke` | Full cleanup: containers, volumes, and local images |

## Production

| Command | Description |
|---------|-------------|
| `make version` | Show the current version from the VERSION file |
| `make build-prod` | Build multi-platform images and push to Docker Hub |
| `make release v=X.Y.Z` | Full release: bump version, build, push, create git tag |
