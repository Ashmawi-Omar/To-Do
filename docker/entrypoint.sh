#!/bin/sh
set -e

cd /var/www/html

[ -f .env ] || cp .env.example .env

# vendor/ lives on a named volume (the Windows bind mount is far too slow for it),
# so reinstall whenever composer.lock changes.
LOCK_HASH="$(md5sum composer.lock | cut -d' ' -f1)"
if [ ! -f vendor/autoload.php ] || [ "$(cat vendor/.lock-hash 2>/dev/null)" != "$LOCK_HASH" ]; then
    composer install --no-interaction --prefer-dist
    echo "$LOCK_HASH" > vendor/.lock-hash
fi

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

# The SQLite file lives on a named volume so it survives `docker compose down`.
if [ -n "$DB_DATABASE" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
    chown -R www-data:www-data "$(dirname "$DB_DATABASE")"
fi

chown -R www-data:www-data storage bootstrap/cache

grep -qE '^APP_KEY=.+' .env || su-exec www-data php artisan key:generate --force
su-exec www-data php artisan migrate --force

exec "$@"
