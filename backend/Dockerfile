# syntax=docker/dockerfile:1

# ============================================================
# Stage 1 - Composer dependencies
# ============================================================

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs \
    --prefer-dist


# ============================================================
# Stage 2 - PHP runtime
# ============================================================

FROM php:8.2-fpm-alpine AS app

# Binary composer TIDAK ikut ter-copy dari stage "vendor" (beda filesystem
# stage) — harus diambil eksplisit dari image resmi composer, kalau tidak
# `composer dump-autoload` di bawah gagal ("composer: not found") dan
# vendor/autoload.php tidak pernah benar-benar dibuat.
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        libxml2 \
        unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        libxml2-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        gd \
        zip \
        exif \
        mbstring \
        opcache \
    && apk del .build-deps


COPY docker/php/opcache.ini \
    /usr/local/etc/php/conf.d/zz-opcache.ini


WORKDIR /var/www/html


COPY --from=vendor /app/vendor ./vendor

COPY . .


# package:discover (dijalankan otomatis lewat composer post-autoload-dump)
# tidak butuh koneksi DB — cuma introspeksi composer/installed.json, jadi
# aman dijalankan saat build tanpa .env/DB tersedia.
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache


COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

USER www-data

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]

CMD ["app"]
