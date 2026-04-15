# Installation

## Prerequisites

- **Docker** (v20+) and **Docker Compose** (v2+)
- **Git**
- 4 GB+ RAM recommended (MySQL + Redis + 4 services)

## Clone the Repository

```bash
git clone https://github.com/ismailcaakir/appstorecat.git
cd appstorecat
```

## Setup

Run the one-command setup:

```bash
make setup
```

This will:

1. **Build** all Docker containers (`backend`, `frontend`, `scraper-appstore`, `scraper-gplay`, `mysql`, `redis`)
2. **Install** dependencies (Composer for backend, npm for frontend and App Store scraper)
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

You should see 6 healthy containers: `appstorecat-backend`, `appstorecat-frontend`, `appstorecat-scraper-appstore`, `appstorecat-scraper-gplay`, `appstorecat-mysql`, `appstorecat-redis`.

Visit http://localhost:7461 to access the frontend.

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
make dev-backend    # Backend + MySQL + Redis
make dev-frontend   # Frontend only
make dev-appstore   # App Store scraper only
make dev-gplay      # Google Play scraper only
```

## Troubleshooting

### Port Conflicts

Default ports are 7460-7464. If any port is in use, edit the `.env` file in the project root to change them.

### Database Connection Issues

If the backend can't connect to MySQL, wait a few seconds for the health check to pass:

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
