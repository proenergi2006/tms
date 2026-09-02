#!/bin/sh
set -e

# Role dipilih lewat argumen container (docker-compose "command:"), bukan
# image terpisah — "app" dan "scheduler" pakai image yang sama supaya tidak
# ada drift antara kode yang jalan di dua tempat itu.
ROLE="${1:-app}"

case "$ROLE" in
  app)
    # migrate --force aman dijalankan berulang tiap container start —
    # Laravel skip migration yang sudah tercatat di tabel migrations, jadi
    # idempotent selama tidak ada 2 container "app" start bersamaan (setup
    # ini single-instance, bukan horizontally scaled).
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link || true

    exec php-fpm
    ;;
  scheduler)
    # Pengganti cron system di dalam container — pola resmi yang
    # direkomendasikan Laravel untuk Docker (bukan install cron daemon).
    # Enam scheduled command di routes/console.php (sync SYOP tiap jam,
    # legal-expiry, inspection, service-due, low-stock, component-due)
    # jalan lewat schedule:run ini.
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
