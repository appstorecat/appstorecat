# Installation

## Requirements

- **Docker** (v20+) and **Docker Compose** (v2+)
- **Git**
- 4 GB+ RAM recommended (MySQL + Redis + 4 services)

## Clone the Repository

```bash
git clone https://github.com/appstorecat/appstorecat.git
cd appstorecat
```

## Setup

Run setup with a single command:

```bash
make setup
```

This command:

1. **Builds** all Docker containers (`server`, `web`, `scraper-ios`, `scraper-android`, `mysql`, `redis`)
2. **Installs** dependencies (Composer for the server, npm for web and the App Store scraper)
3. **Generates** the Laravel `APP_KEY`
4. **Runs** the database migrations

## Start the Services

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
| MySQL | localhost:7464 | Database (host-side; container is `:3306`) |
| Redis | localhost:7465 | Cache & queue (host-side; container is `:6379`) |

## Verify the Setup

Check that all services are running:

```bash
make ps
```

You should see 6 healthy containers: `appstorecat-server`, `appstorecat-web`, `appstorecat-scraper-ios`, `appstorecat-scraper-android`, `appstorecat-mysql`, `appstorecat-redis`.

Visit http://localhost:7461 to reach the frontend.

## Seed Store Categories

After setup, seed the store categories (App Store and Google Play categories):

```bash
make seed
```

## Stop the Services

```bash
make down
```

## Start Services Individually

If you only need specific services:

```bash
make dev-server    # Backend + MySQL + Redis
make dev-web   # Frontend only
make dev-ios   # App Store scraper only
make dev-android      # Google Play scraper only
```

## Troubleshooting

### Port Conflicts

Default ports are in the **7460–7465** range. If any port is in use, change it by editing the `.env` file at the project root.

### Database Connection Issues

If the backend cannot connect to MySQL, wait a few seconds for the health check to pass:

```bash
docker compose logs appstorecat-mysql
```

### Clean Install

To remove all containers and volumes and start from scratch:

```bash
make clean    # Remove containers and volumes
make setup    # Rebuild everything
```

For a full reset including local Docker images:

```bash
make nuke     # Remove everything, images included
make setup
```
