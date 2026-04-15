# AppStoreCat

> **Documentation:** [English](docs/en/) | [Türkçe](README-tr.md)

Open-source app intelligence toolkit for iOS and Android. Track store listings, monitor changes, analyze keywords, and discover trending apps.

![AppStoreCat Dashboard](screenshots/hero-dashboard.jpeg)

## Features

### Trending Charts
Daily snapshots of top free, top paid, and top grossing apps across both stores with historical ranking data.

![Trending Charts](screenshots/trending-charts.jpeg)

### App Discovery
Search for apps across App Store and Google Play, discover through trending charts, or import entire publisher catalogs.

![App Discovery](screenshots/app-discovery.jpeg)

### Store Listings
Multi-language store listing tracking with title, description, screenshots, and metadata for each supported locale.

![Store Listing](screenshots/store-listing.jpeg)

### Ratings & Reviews
Monitor rating trends and sync user reviews with filtering by country, rating, and date.

![Ratings & Reviews](screenshots/ratings-reviews.jpeg)

### Keyword Density
ASO-focused keyword analysis with n-gram extraction (1/2/3-word), stop word filtering for 50 languages, and cross-app comparison.

![Keyword Density](screenshots/keyword-density.jpeg)

### Change Detection
Automatic detection of store listing changes (title, description, screenshots, locales) with old/new value tracking.

![Change Detection](screenshots/change-detection.jpeg)

### Competitor Tracking
Define competitor relationships and monitor their store presence side by side.

![Competitor Tracking](screenshots/competitor-tracking.jpeg)

### Publisher Discovery
Search publishers, view their app catalogs, and bulk import all their apps.

![Publisher Discovery](screenshots/publisher-discovery.jpeg)

## Architecture

```
Frontend :7461 --> Backend API :7460 --> scraper-appstore :7462
                        |           --> scraper-gplay :7463
                        v
                    MySQL :7464
```

| Service | Tech | Description |
|---------|------|-------------|
| **backend** | Laravel 13, PHP 8.4 | API gateway, business logic, database |
| **frontend** | React 19, Vite, TypeScript | User interface |
| **scraper-appstore** | Fastify 5, Node.js | App Store data |
| **scraper-gplay** | FastAPI, Python | Google Play data |

## Quick Start

```bash
git clone https://github.com/ismailcaakir/appstorecat.git
cd appstorecat
make setup
make dev
```

Open http://localhost:7461 and create an account.

See the [Installation Guide](docs/en/getting-started/installation.md) for detailed setup instructions.

## Documentation

### Getting Started
- [Installation](docs/en/getting-started/installation.md)
- [Configuration](docs/en/getting-started/configuration.md)
- [Quick Start](docs/en/getting-started/quick-start.md)

### Architecture
- [Overview](docs/en/architecture/overview.md)
- [Data Model](docs/en/architecture/data-model.md)
- [Data Collection](docs/en/architecture/data-collection.md)
- [Queue System](docs/en/architecture/queue-system.md)
- [Connectors](docs/en/architecture/connectors.md)
- [Sync Pipeline](docs/en/architecture/sync-pipeline.md)

### Features
- [Trending Charts](docs/en/features/trending-charts.md)
- [App Discovery](docs/en/features/app-discovery.md)
- [Store Listings](docs/en/features/store-listings.md)
- [Ratings & Reviews](docs/en/features/ratings-reviews.md)
- [Keyword Density](docs/en/features/keyword-density.md)
- [Competitor Tracking](docs/en/features/competitor-tracking.md)
- [Change Detection](docs/en/features/change-detection.md)
- [Publisher Discovery](docs/en/features/publisher-discovery.md)
- [Media & Explorer](docs/en/features/media-proxy.md)

### Services
- [Backend](docs/en/services/backend.md)
- [Frontend](docs/en/services/frontend.md)
- [App Store Scraper](docs/en/services/scraper-appstore.md)
- [Google Play Scraper](docs/en/services/scraper-gplay.md)

### API
- [Endpoints](docs/en/api/endpoints.md)
- [Authentication](docs/en/api/authentication.md)
- [Scraper APIs](docs/en/api/scraper-apis.md)

### Deployment
- [Docker](docs/en/deployment/docker.md)
- [Production](docs/en/deployment/production.md)
- [Troubleshooting](docs/en/deployment/troubleshooting.md)

### Reference
- [Environment Variables](docs/en/reference/environment-variables.md)
- [Makefile Commands](docs/en/reference/makefile-commands.md)
- [App Store Countries](docs/en/reference/app-store-countries.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Security

See [SECURITY.md](SECURITY.md) for reporting vulnerabilities.

## License

[MIT](LICENSE)
