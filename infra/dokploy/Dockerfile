FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM node:22-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /app/vendor ./vendor
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4-apache-bookworm AS runtime

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libicu-dev \
        libzip-dev \
        openssl \
        unzip \
    && docker-php-ext-install -j"$(nproc)" intl opcache pcntl pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && a2enmod headers rewrite \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY infra/dokploy/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY infra/dokploy/entrypoint.sh /usr/local/bin/naxas-license-entrypoint
COPY infra/dokploy/healthcheck.sh /usr/local/bin/naxas-license-healthcheck

RUN chmod +x /usr/local/bin/naxas-license-entrypoint /usr/local/bin/naxas-license-healthcheck \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi \
    && php artisan view:cache

ENTRYPOINT ["naxas-license-entrypoint"]
CMD ["web"]
