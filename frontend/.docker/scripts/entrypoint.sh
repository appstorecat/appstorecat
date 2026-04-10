#!/bin/sh

export FRONTEND_PORT=${FRONTEND_PORT:-7461}

# Replace env vars in nginx config
envsubst '${FRONTEND_PORT} ${BACKEND_API_URL}' < /etc/nginx/conf.d/default.conf.template > /etc/nginx/conf.d/default.conf

exec nginx -g "daemon off;"
