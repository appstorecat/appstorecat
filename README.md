# AppStoreCat

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

See the [Installation Guide](docs/getting-started/installation.md) for detailed setup instructions.

## Documentation

### Getting Started
- [Installation](docs/getting-started/installation.md)
- [Configuration](docs/getting-started/configuration.md)
- [Quick Start](docs/getting-started/quick-start.md)

### Architecture
- [Overview](docs/architecture/overview.md)
- [Data Model](docs/architecture/data-model.md)
- [Data Collection](docs/architecture/data-collection.md)
- [Queue System](docs/architecture/queue-system.md)
- [Connectors](docs/architecture/connectors.md)
- [Sync Pipeline](docs/architecture/sync-pipeline.md)

### Features
- [Trending Charts](docs/features/trending-charts.md)
- [App Discovery](docs/features/app-discovery.md)
- [Store Listings](docs/features/store-listings.md)
- [Ratings & Reviews](docs/features/ratings-reviews.md)
- [Keyword Density](docs/features/keyword-density.md)
- [Competitor Tracking](docs/features/competitor-tracking.md)
- [Change Detection](docs/features/change-detection.md)
- [Publisher Discovery](docs/features/publisher-discovery.md)
- [Media & Explorer](docs/features/media-proxy.md)

### Services
- [Backend](docs/services/backend.md)
- [Frontend](docs/services/frontend.md)
- [App Store Scraper](docs/services/scraper-appstore.md)
- [Google Play Scraper](docs/services/scraper-gplay.md)

### API
- [Endpoints](docs/api/endpoints.md)
- [Authentication](docs/api/authentication.md)
- [Scraper APIs](docs/api/scraper-apis.md)

### Deployment
- [Docker](docs/deployment/docker.md)
- [Production](docs/deployment/production.md)
- [Troubleshooting](docs/deployment/troubleshooting.md)

### Reference
- [Environment Variables](docs/reference/environment-variables.md)
- [Makefile Commands](docs/reference/makefile-commands.md)
- [App Store Countries](docs/reference/app-store-countries.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Security

See [SECURITY.md](SECURITY.md) for reporting vulnerabilities.

## License

[MIT](LICENSE)
