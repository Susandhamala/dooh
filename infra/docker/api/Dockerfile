FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        ffmpeg libpq-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev libfcgi-bin unzip git \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql gd zip pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY infra/docker/api/php.ini /usr/local/etc/php/conf.d/zz-dooh.ini
COPY infra/docker/api/fpm.conf /usr/local/etc/php-fpm.d/zz-dooh.conf
COPY --chmod=755 infra/docker/api/entrypoint.sh /usr/local/bin/dooh-entrypoint

# Defaults for the lab; production overrides them in the environment.
ENV PHP_OPCACHE_VALIDATE=1 \
    PHP_FPM_MAX_CHILDREN=16

WORKDIR /var/www/api
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY api/ ./
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && rm -f .env \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

# FPM answers its ping page; the queue and scheduler containers override this check.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 >/dev/null || exit 1

ENTRYPOINT ["dooh-entrypoint"]
CMD ["php-fpm"]
