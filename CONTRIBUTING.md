# Contributing to AppStoreCat

Thank you for your interest in contributing to AppStoreCat! This guide will help you get started.

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/your-username/appstorecat.git`
3. Run `make setup` to set up the development environment
4. Create a new branch: `git checkout -b feature/your-feature`

## Development Setup

See the [Installation guide](docs/getting-started/installation.md) for detailed setup instructions.

```bash
make setup    # Build containers, install deps, migrate
make dev      # Start all services
make test     # Run all tests
```

## Project Structure

AppStoreCat is a monorepo with 4 services:

| Service | Path | Tech |
|---------|------|------|
| Server API | `server/` | Laravel 13, PHP 8.4 |
| Web | `web/` | React 19, TypeScript, Vite |
| App Store Scraper | `scraper-ios/` | Fastify 5, Node.js |
| Google Play Scraper | `scraper-android/` | FastAPI, Python |

Architecture rules are documented in `.arc/` — please read `.arc/README.md` before making changes.

## Making Changes

### Code Style

- **PHP:** Run `make pint` before committing. Laravel Pint enforces the project style.
- **TypeScript/React:** Follow existing patterns in the web codebase.
- **Python:** Follow PEP 8 conventions.

### Tests

Run tests before submitting a PR:

```bash
make test             # All tests
make test-server      # PHPUnit (server)
make test-ios         # Vitest (App Store scraper)
make test-android     # pytest (Google Play scraper)
```

### Queue Jobs

When creating new scraper-related jobs, follow the platform-separation rule:

- Use separate queues for iOS and Android (`{queue}-ios`, `{queue}-android`)
- Apply Redis throttle with appropriate rates
- Implement retry with exponential backoff
- See [Queue System](docs/architecture/queue-system.md) for details

### Commits

Use descriptive commit messages with a type prefix:

```
add: keyword comparison API endpoint
fix: null guard in saveVersion
refactor: split config into per-platform keys
update: explorer page UI polish
remove: unused Dashboard component
config: enable trending discovery by default
```

## Pull Requests

1. Fill out the [PR template](.github/PULL_REQUEST_TEMPLATE.md)
2. Ensure all tests pass
3. Run `make pint` for PHP changes
4. Keep PRs focused — one feature or fix per PR
5. Reference related issues in the description

## Reporting Issues

- **Bugs:** Use the [bug report template](https://github.com/appstorecat/appstorecat/issues/new?template=bug_report.yml)
- **Features:** Use the [feature request template](https://github.com/appstorecat/appstorecat/issues/new?template=feature_request.yml)
- **Security:** See [SECURITY.md](SECURITY.md) for responsible disclosure

## Architecture Decisions

Before making significant architectural changes, please open an issue to discuss your approach. Key design principles:

- **Server as gateway:** All store data flows through the server
- **Stateless scrapers:** Scrapers have no database or state
- **Platform separation:** iOS and Android pipelines are independent
- **Organic data collection:** No bulk scraping or mass crawling

## License

By contributing to AppStoreCat, you agree that your contributions will be licensed under the [MIT License](LICENSE).
