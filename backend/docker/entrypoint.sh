#!/bin/sh
set -e

cd /var/www/html

ROLE="${1:-app}"

CURRENT_COMPOSER_HASH="$(sha256sum composer.lock | awk '{print $1}')"

case "$ROLE" in
  app)
    INSTALLED_COMPOSER_HASH=""

    if [ -f vendor/.composer-lock-hash ]; then
      INSTALLED_COMPOSER_HASH="$(cat vendor/.composer-lock-hash)"
    fi

    if [ ! -f vendor/autoload.php ] || \
       [ "$CURRENT_COMPOSER_HASH" != "$INSTALLED_COMPOSER_HASH" ]; then

      echo "Composer dependencies belum tersedia atau berubah."
      echo "Menjalankan composer install..."

      COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-dev \
        --prefer-dist \
        --optimize-autoloader \
        --no-interaction

      echo "$CURRENT_COMPOSER_HASH" > vendor/.composer-lock-hash

      echo "Composer dependencies siap."
    else
      echo "Composer dependencies tidak berubah."
    fi

    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link || true

    exec php-fpm
    ;;

  scheduler)
    echo "Menunggu Composer dependencies..."

    until [ -f vendor/autoload.php ] \
      && [ -f vendor/.composer-lock-hash ] \
      && [ "$(cat vendor/.composer-lock-hash)" = "$CURRENT_COMPOSER_HASH" ]; do
      sleep 2
    done

    echo "Scheduler loop dimulai (schedule:run tiap 60 detik)."

    while true; do
      php artisan schedule:run --no-interaction --verbose 2>&1
      sleep 60
    done
    ;;

  *)
    exec "$@"
    ;;
esac
