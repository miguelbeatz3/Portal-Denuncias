FROM composer:2 AS composer
FROM php:8.4-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype-dev libicu-dev libjpeg62-turbo-dev libpng-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl opcache pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/portal.ini /usr/local/etc/php/conf.d/portal.ini

COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN mkdir -p bootstrap/cache storage/app/private storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs \
    && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-dev --optimize --no-interaction \
    && chown -R www-data:www-data bootstrap/cache storage

CMD ["sh", "-ec", "su -s /bin/sh www-data -c 'php artisan migrate --force' && exec apache2-foreground"]
