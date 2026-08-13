FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

FROM php:8.4-fpm-alpine

RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install bcmath pdo_pgsql

WORKDIR /var/www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN composer dump-autoload --optimize --no-interaction \
    && sed -i 's/\r$//' docker/php/entrypoint.sh \
    && chmod +x docker/php/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["/var/www/docker/php/entrypoint.sh"]
CMD ["php-fpm", "-F"]
