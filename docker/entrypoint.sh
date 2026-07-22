#!/bin/sh
set -e

PORT="${PORT:-10000}"

# Render assigns the port at runtime — point Apache at it.
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

# Laravel writes here at runtime.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs
chown -R www-data:www-data storage bootstrap/cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "==> Running migrations"
    php artisan migrate --force --no-interaction

    # Default settings (referral bonus). Uses firstOrCreate, so re-running on
    # every deploy never overwrites what the admin set in the dashboard.
    php artisan db:seed --class=SettingsSeeder --force --no-interaction

    # Render's FREE plan has no Shell tab, so the owner account is created from
    # env vars instead. The command is updateOrCreate — safe to repeat. Once the
    # account exists you can delete SUPERADMIN_PASSWORD from the environment.
    if [ -n "${SUPERADMIN_EMAIL:-}" ] && [ -n "${SUPERADMIN_PASSWORD:-}" ]; then
        echo "==> Ensuring superadmin ${SUPERADMIN_EMAIL}"
        php artisan bingo:create-superadmin \
            --name="${SUPERADMIN_NAME:-Owner}" \
            --email="${SUPERADMIN_EMAIL}" \
            --password="${SUPERADMIN_PASSWORD}"
    fi
fi

echo "==> Caching config, routes and views"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize || true

php artisan storage:link || true

echo "==> Starting Apache on port ${PORT}"
exec "$@"
