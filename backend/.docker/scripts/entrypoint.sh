#!/bin/sh
set -e

# Port from environment (required)
if [ -z "${BACKEND_PORT}" ]; then
    echo "ERROR: BACKEND_PORT environment variable is required" >&2
    exit 1
fi

# Replace port in nginx config
envsubst '${BACKEND_PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

# Create .env if it doesn't exist
if [ ! -f .env ]; then
    cp .env.example .env 2>/dev/null || touch .env
fi

# Auto-generate APP_KEY if not set in .env
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
    php artisan key:generate --force
    echo "APP_KEY generated."
fi

# Run migrations on startup
php artisan migrate --force 2>/dev/null || true

# Cache config for performance
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true

# Start supervisor (php-fpm + nginx)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
