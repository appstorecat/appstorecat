# Backend Service

The Laravel API server is the central service in AppStoreCat. It acts as the API gateway, owns the database, manages background jobs, and orchestrates all communication with scraper microservices.

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 13, PHP 8.4 |
| Database | MySQL 8.4 |
| Auth | Laravel Sanctum (token-based) |
| API Docs | L5-Swagger (OpenAPI) |
| Queue | Redis (dev) / Database (prod) |
| Cache | Redis (dev) / File (prod) |
| Code Style | Laravel Pint |
| Tests | PHPUnit |

## Directory Structure

```
server/
├── app/
│   ├── Connectors/          # Store API integrations
│   │   ├── ConnectorInterface.php
│   │   ├── ConnectorResult.php
│   │   ├── ITunesLookupConnector.php
│   │   └── GooglePlayConnector.php
│   ├── Enums/               # Platform, DiscoverSource, etc.
│   ├── Http/
│   │   └── Controllers/Api/V1/
│   │       ├── Account/     # Auth, Profile, Security
│   │       └── App/         # App, Search, Competitor, Keyword, Review
│   ├── Jobs/
│   │   ├── Chart/           # Chart sync jobs
│   │   └── Sync/            # App sync jobs
│   ├── Models/              # Eloquent models (14 total)
│   └── Services/            # Business logic
│       ├── AppRegistrar.php
│       ├── AppSyncer.php
│       └── KeywordAnalyzer.php
├── config/
│   └── appstorecat.php        # Central configuration
├── database/
│   └── migrations/          # All table definitions
├── resources/
│   └── data/stopwords/      # 50-language stop word dictionaries
├── routes/
│   └── api.php              # All API routes
└── tests/                   # PHPUnit tests
```

## Key Responsibilities

### API Gateway
All web requests go through the server. The server authenticates users (Sanctum), validates requests (Form Requests), and returns formatted responses (API Resources).

### Database Owner
The server is the sole owner of the MySQL database. No other service accesses the database directly.

### Job Orchestration
The Laravel scheduler dispatches sync and chart jobs. Queue workers process them with platform-specific throttling via Redis.

### Connector Layer
Connectors abstract HTTP communication with scraper microservices, normalizing response formats across platforms.

## Running

```bash
make dev-server    # Start server + MySQL + Redis
make logs-server   # View server logs
make pint           # Run code style fixer
make test-server   # Run PHPUnit tests
```

## API Documentation

Swagger UI is available at `/api/documentation` when `L5_SWAGGER_GENERATE_ALWAYS=true`.

See [API Endpoints](../api/endpoints.md) for the full reference.
