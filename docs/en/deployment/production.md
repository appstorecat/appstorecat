# Production Deployment

End-to-end guide for running AppStoreCat in production: reverse proxy with TLS, environment hardening, backups, log retention, firewall, and rollback. This guide assumes a single-host Docker Compose deployment, which is what the project is designed and tested for.

For multi-host or Kubernetes deployments, the published images at `ghcr.io/appstorecat/{server,web,scraper-ios,scraper-android}` work but you'll need to adapt the orchestration yourself.

## Architecture in production

```
            internet
               │
               ▼
     ┌─────────────────────┐
     │  Reverse proxy + TLS │   (Caddy / Nginx / Traefik)
     │  ports 80, 443       │
     └─────────┬────────────┘
               │
   ┌───────────┴────────────┐
   │                        │
   ▼                        ▼
appstorecat-web:7461   appstorecat-server:7460
                            │
                            ├─▶ appstorecat-mysql:3306         (internal — host port: 7464)
                            ├─▶ appstorecat-redis:6379         (internal — host port: 7465)
                            ├─▶ appstorecat-scraper-ios:7462    (internal only)
                            └─▶ appstorecat-scraper-android:7463 (internal only)
```

Only ports `7460` (API) and `7461` (web) need to be reachable from the proxy. The rest stay on the Docker network.

## Requirements

- Linux server, x86_64 or arm64
- Docker Engine 24+ with Compose v2 plugin
- 2 vCPU, 4 GB RAM minimum (sync queues + MySQL + Redis comfortably; tested at this size)
- 20 GB disk for the OS + Docker + 30 days of MySQL data on a few hundred tracked apps; grow with usage
- A domain (or two subdomains: one for API, one for web)
- Outbound internet access (the scrapers fetch from iTunes and Google Play)

## Step 1 — Pull the repo and prepare `.env`

```bash
ssh you@your-server
git clone --branch v1.2.0 https://github.com/appstorecat/appstorecat.git
cd appstorecat
cp .env.production.example .env
```

Edit `.env` with production values. Fill in **everything** — defaults from the example are not safe.

```env
APP_NAME=AppStoreCat
APP_ENV=production
APP_KEY=                        # see step 2
APP_DEBUG=false
APP_VERSION=1.2.0

# URLs (HTTPS — the proxy terminates TLS)
APP_URL=https://api.appstore.example
FRONTEND_URL=https://appstore.example

# Auth — required when frontend and backend are on different subdomains
SANCTUM_STATEFUL_DOMAINS=appstore.example,www.appstore.example
SESSION_DOMAIN=.appstore.example
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# Scrapers (internal Docker network)
APPSTORE_API_URL=http://appstorecat-scraper-ios:7462
GPLAY_API_URL=http://appstorecat-scraper-android:7463

# Database — generate strong passwords (see step 2)
DB_DATABASE=appstorecat
DB_USERNAME=appstorecat
DB_PASSWORD=                    # see step 2
MYSQL_ROOT_PASSWORD=            # see step 2

# Queue + cache — production uses database/file (no Redis on the host network)
QUEUE_CONNECTION=database
CACHE_STORE=file

# Logging — stderr is container-friendly; use warning in production
LOG_CHANNEL=stderr
LOG_LEVEL=warning

# Swagger off in production
L5_SWAGGER_GENERATE_ALWAYS=false

# Internal ports — 746x series across the stack
BACKEND_PORT=7460
FRONTEND_PORT=7461
APPSTORE_API_PORT=7462
GPLAY_API_PORT=7463
FORWARD_DB_PORT=7464
FORWARD_REDIS_PORT=7465
```

See [Environment Variables](../reference/environment-variables.md) for the full reference, especially the **Workers** section if you want to tune queue throughput.

## Step 2 — Generate secrets

```bash
# APP_KEY (32-byte base64) — Laravel will refuse to boot without it
docker run --rm -v "$(pwd)":/app -w /app php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

# Strong DB passwords (28 alphanumeric chars)
openssl rand -base64 28 | tr -d '+/=' | head -c 28
openssl rand -base64 28 | tr -d '+/=' | head -c 28
```

Paste the values into `.env`. Never commit `.env` to git — it's `.gitignore`d for a reason.

## Step 3 — Deploy

```bash
docker compose -f docker-compose.production.yml pull
docker compose -f docker-compose.production.yml up -d
```

Wait ~30 seconds, then run migrations:

```bash
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan migrate --force
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan db:seed --force
```

`--force` is required by Laravel in `production` env to confirm you mean it.

Verify everything is healthy:

```bash
docker compose -f docker-compose.production.yml ps
# All services should be "running" or "healthy"

curl -f http://localhost:7460/api/v1/countries
# Should return JSON; if not, check 'docker compose logs appstorecat-server'
```

## Step 4 — Reverse proxy + TLS

Pick one. All three terminate TLS, forward to the internal ports, and handle Let's Encrypt automatically.

### Caddy (simplest — automatic TLS)

`/etc/caddy/Caddyfile`:

```caddy
appstore.example {
    reverse_proxy localhost:7461
    encode gzip
}

api.appstore.example {
    reverse_proxy localhost:7460
    encode gzip

    # Sanctum needs the original Host header
    header_up Host {host}
    header_up X-Real-IP {remote_host}
    header_up X-Forwarded-For {remote_host}
    header_up X-Forwarded-Proto {scheme}
}
```

```bash
sudo systemctl reload caddy
```

Caddy fetches Let's Encrypt certs automatically on first request. Done.

### Nginx + certbot

`/etc/nginx/sites-available/appstorecat`:

```nginx
upstream appstorecat_web    { server 127.0.0.1:7461; }
upstream appstorecat_server { server 127.0.0.1:7460; }

server {
    listen 80;
    server_name appstore.example api.appstore.example;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name appstore.example;

    ssl_certificate     /etc/letsencrypt/live/appstore.example/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/appstore.example/privkey.pem;

    client_max_body_size 20M;

    location / {
        proxy_pass http://appstorecat_web;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

server {
    listen 443 ssl http2;
    server_name api.appstore.example;

    ssl_certificate     /etc/letsencrypt/live/appstore.example/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/appstore.example/privkey.pem;

    client_max_body_size 20M;

    location / {
        proxy_pass http://appstorecat_server;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/appstorecat /etc/nginx/sites-enabled/
sudo certbot --nginx -d appstore.example -d api.appstore.example
sudo nginx -t && sudo systemctl reload nginx
```

### Traefik (Docker labels)

If Traefik is already running on the same host with Docker provider enabled, add labels to `docker-compose.production.yml`:

```yaml
services:
  appstorecat-web:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.appstorecat-web.rule=Host(`appstore.example`)"
      - "traefik.http.routers.appstorecat-web.entrypoints=websecure"
      - "traefik.http.routers.appstorecat-web.tls.certresolver=letsencrypt"
      - "traefik.http.services.appstorecat-web.loadbalancer.server.port=7461"

  appstorecat-server:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.appstorecat-api.rule=Host(`api.appstore.example`)"
      - "traefik.http.routers.appstorecat-api.entrypoints=websecure"
      - "traefik.http.routers.appstorecat-api.tls.certresolver=letsencrypt"
      - "traefik.http.services.appstorecat-api.loadbalancer.server.port=7460"
```

Both containers must be on Traefik's network.

## Step 5 — Firewall

The scraper services (`7462`, `7463`), MySQL host-side port (`7464`), and Redis host-side port (`7465`) must **not** be publicly reachable. With `ufw`:

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

If you need to expose MySQL temporarily for a migration tool, tunnel through SSH instead of opening the port:

```bash
ssh -L 7464:localhost:7464 you@server
# then connect locally to 127.0.0.1:7464
```

## Step 6 — Backups

Daily MySQL dump rotated by date. Save as `/usr/local/bin/appstorecat-backup.sh`:

```bash
#!/bin/bash
set -euo pipefail

BACKUP_DIR=/var/backups/appstorecat
RETENTION_DAYS=14
TIMESTAMP=$(date -u +%Y-%m-%dT%H-%M-%SZ)
COMPOSE_DIR=/home/you/appstorecat

mkdir -p "$BACKUP_DIR"

cd "$COMPOSE_DIR"
docker compose -f docker-compose.production.yml exec -T appstorecat-mysql \
  sh -c 'exec mysqldump --single-transaction --quick --lock-tables=false \
         -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip -9 > "$BACKUP_DIR/appstorecat-$TIMESTAMP.sql.gz"

# Optional: ship to S3 / B2 / Backblaze
# aws s3 cp "$BACKUP_DIR/appstorecat-$TIMESTAMP.sql.gz" s3://my-bucket/appstorecat/

find "$BACKUP_DIR" -name 'appstorecat-*.sql.gz' -mtime +$RETENTION_DAYS -delete

echo "Backup complete: $BACKUP_DIR/appstorecat-$TIMESTAMP.sql.gz"
```

```bash
sudo chmod +x /usr/local/bin/appstorecat-backup.sh
sudo crontab -e
# Add:
0 3 * * * /usr/local/bin/appstorecat-backup.sh >> /var/log/appstorecat-backup.log 2>&1
```

**Restore from backup:**

```bash
gunzip -c /var/backups/appstorecat/appstorecat-2026-04-26T03-00-00Z.sql.gz \
  | docker compose -f docker-compose.production.yml exec -T appstorecat-mysql \
    sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

## Step 7 — Log rotation

The container logs to stderr, which Docker captures. Limit log file size in `docker-compose.production.yml` (or globally in `/etc/docker/daemon.json`):

```yaml
services:
  appstorecat-server:
    logging:
      driver: json-file
      options:
        max-size: "20m"
        max-file: "5"
```

Apply globally instead by editing `/etc/docker/daemon.json`:

```json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "20m",
    "max-file": "5"
  }
}
```

Then `sudo systemctl restart docker` (this restarts your containers — schedule a maintenance window).

## Upgrading

```bash
cd appstorecat

# 1. Note the current version (for rollback)
CURRENT=$(git describe --tags --abbrev=0)
echo "Current: $CURRENT"

# 2. Take a backup
sudo /usr/local/bin/appstorecat-backup.sh

# 3. Pull the new release
git fetch --tags
git checkout v1.3.0     # replace with the target version

# 4. Pull new images
docker compose -f docker-compose.production.yml pull

# 5. Apply
docker compose -f docker-compose.production.yml up -d

# 6. Migrate
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan migrate --force

# 7. Verify
curl -f https://api.appstore.example/api/v1/countries
```

### Rollback

If step 7 fails or the new version misbehaves:

```bash
# Revert containers to the previous tag
git checkout "$CURRENT"
docker compose -f docker-compose.production.yml pull
docker compose -f docker-compose.production.yml up -d

# If migrations need rolling back, restore from the backup taken in step 2
gunzip -c /var/backups/appstorecat/appstorecat-<timestamp>.sql.gz \
  | docker compose -f docker-compose.production.yml exec -T appstorecat-mysql \
    sh -c 'exec mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

Most upgrades are forward-compatible at the schema level — the rollback path is mainly for pre-flight safety, not routine use.

## Operations

### Queue workers

The production server image runs `supervisord` which keeps `php artisan queue:work` processes alive across all platform-separated queues. Adjust concurrency via `SUPERVISOR_QUEUE_NUMPROCS` in `.env` (default `2`).

```bash
# Restart workers (e.g. after a deploy)
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan queue:restart

# Inspect failed jobs
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan queue:failed

# Retry one
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan queue:retry <uuid>

# Retry all
docker compose -f docker-compose.production.yml exec appstorecat-server php artisan queue:retry all
```

### Scheduler

Laravel's scheduler runs inside the same container (also via supervisord). It dispatches the 20-minute tracked-app sync, daily chart snapshots, and `ReconcileFailedItemsJob`. No host-level cron needed.

To disable (e.g. when running scheduler externally):

```env
SCHEDULER_ENABLED=false
```

### Tailing logs

```bash
# All services
docker compose -f docker-compose.production.yml logs -f --tail=200

# One service
docker compose -f docker-compose.production.yml logs -f appstorecat-server
```

### Checking health

```bash
docker compose -f docker-compose.production.yml ps

curl -fsI https://api.appstore.example/api/v1/countries
curl -fsI https://appstore.example
```

## Security checklist

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` set (random, 32 bytes, base64-encoded)
- [ ] `DB_PASSWORD` and `MYSQL_ROOT_PASSWORD` are strong and distinct
- [ ] `SANCTUM_STATEFUL_DOMAINS` set to your actual frontend domain(s)
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] HTTPS in front of both web and API (no plain HTTP)
- [ ] Firewall allows only 22, 80, 443 inbound
- [ ] Scraper ports (`7462`, `7463`), MySQL host port (`7464`), Redis host port (`7465`) NOT exposed
- [ ] `L5_SWAGGER_GENERATE_ALWAYS=false`
- [ ] Daily backups running and tested (do a restore drill once)
- [ ] Docker log rotation configured (or you'll fill the disk)
- [ ] Server packages auto-update (e.g. `unattended-upgrades` on Debian/Ubuntu)
