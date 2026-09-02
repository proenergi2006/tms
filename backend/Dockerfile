# syntax=docker/dockerfile:1

# Stage 1: install dependency PHP lewat image composer resmi (bukan install
# composer manual di image runtime) — lebih cepat & image akhir tidak perlu
# menyimpan composer.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs \
    --prefer-dist

# Stage 2: runtime PHP-FPM. Extension yang di-install mengikuti hasil `php -m`
# di environment dev (bcmath/gd/zip/exif/mbstring/pdo_mysql) — gd & zip
# dibutuhkan maatwebsite/excel (export .xlsx profitabilitas armada).
FROM php:8.2-fpm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        gd \
        zip \
        exif \
        mbstring \
        opcache \
    && apt-get purge -y --auto-remove \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

# package:discover (dijalankan otomatis lewat composer post-autoload-dump)
# tidak butuh koneksi DB — cuma introspeksi composer/installed.json, jadi
# aman dijalankan saat build tanpa .env/DB tersedia.
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,testing,views} storage/framework/cache/data storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER www-data

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["app"]
