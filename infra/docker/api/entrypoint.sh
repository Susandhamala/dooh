#!/bin/sh
# Start-up for the api, queue and scheduler containers.
#   production: cache config, routes, events and views, so each request skips reading them
#               (an .env change then needs a restart, which recreating the container does anyway).
#   RUN_MIGRATIONS=true (set it on one container only, the api): migrate before starting.
set -e

if [ "$APP_ENV" = "production" ]; then
    export PHP_OPCACHE_VALIDATE=0
    php artisan optimize --no-interaction
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
