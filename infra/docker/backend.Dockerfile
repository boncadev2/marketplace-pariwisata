FROM php:8.4-cli-alpine

RUN apk add --no-cache $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install intl pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY backend ./
RUN composer dump-autoload --optimize --no-interaction

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
