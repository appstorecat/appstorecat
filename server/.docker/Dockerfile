# AppStoreCat Backend — Development Image
# Lean PHP 8.4 Alpine with only required extensions

FROM php:8.4-fpm-alpine

# Runtime packages
RUN apk add --no-cache \
    supervisor \
    gettext \
    libpng \
    libzip \
    icu-libs \
    oniguruma \
    linux-headers \
    git \
    curl \
    unzip

# Build deps -> compile extensions -> cleanup
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    intl \
    gd \
    pcntl \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Config files
COPY .docker/conf/php/php.ini /usr/local/etc/php/conf.d/99-appstorecat.ini
COPY .docker/conf/supervisord/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
