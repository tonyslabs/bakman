# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.4-cli-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
        openssh-client tar gzip default-mysql-client unzip libzip-dev supervisor curl \
    && docker-php-ext-install pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/supervisord.conf /etc/supervisor/conf.d/backend-manager.conf
RUN chmod +x /entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
