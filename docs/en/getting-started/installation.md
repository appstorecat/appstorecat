# Installation

## Prerequisites

- **Docker** (v20+) and **Docker Compose** (v2+)
- **Git**
- 4 GB+ RAM recommended (MySQL + Redis + 4 services)

## Clone the Repository

```bash
git clone https://github.com/appstorecat/appstorecat.git
cd appstorecat
```

## Setup

Run the one-command setup:

```bash
make setup
```

This will:

1. **Build** all Docker containers (`server`, `web`, `scraper-ios`, `scraper-android`, `mysql`, `redis`)
2. **Install** dependencies (Composer for server, npm for web and App Store scraper)
3. **Generate** the Laravel `APP_KEY`
4. **Run** database migrations

## Start Services

```bash
make dev
```

All services will start in the background:

| Service | URL | Description |
|---------|-----|-------------|
| Backend API | http://localhost:7460 | Laravel API gateway |
| Frontend | http://localhost:7461 | React SPA |
| App Store Scraper | http://localhost:7462 | iOS data source |
| Google Play Scraper | http://localhost:7463 | Android data source |
| MySQL | localhost:7464 | Database |

## Verify Installation

Check that all services are running:

```bash
make ps
```

You should see 6 healthy containers: `appstorecat-server`, `appstorecat-web`, `appstorecat-scraper-ios`, `appstorecat-scraper-android`, `appstorecat-mysql`, `appstorecat-redis`.

Visit http://localhost:7461 to access the web app.

## Seed Store Categories

After setup, seed the store categories (App Store and Google Play categories):

```bash
make seed
```

## Stopping Services

```bash
make down
```

## Starting Individual Services

If you only need specific services:

```bash
make dev-server    # Backend + MySQL + Redis
make dev-web   # Frontend only
make dev-ios   # App Store scraper only
make dev-android      # Google Play scraper only
```

## Troubleshooting

### Port Conflicts

Default ports are 7460-7464. If any port is in use, edit the `.env` file in the project root to change them.

### Database Connection Issues

If the server can't connect to MySQL, wait a few seconds for the health check to pass:

```bash
docker compose logs appstorecat-mysql
```

### Clean Reinstall

To remove all containers, volumes, and start fresh:

```bash
make clean    # Remove containers and volumes
make setup    # Rebuild everything
```

For a full reset including local Docker images:

```bash
make nuke     # Remove everything including images
make setup
```
