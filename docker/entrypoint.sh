#!/bin/sh
set -e

cd /var/www/html

# Directorios ignorados por .dockerignore: recrearlos al arrancar
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs storage/app/public bootstrap/cache

# Permisos: php-fpm corre como www-data
chown -R www-data:www-data storage database bootstrap/cache || true

# La inicialización de la base (migrate/seed) sólo corre en el servicio app
# (CMD php-fpm); reverb y otros procesos no necesitan tocar la base.
if [ "$1" = "php-fpm" ]; then
    # SQLite: crear el archivo si el volumen está vacío
    if [ ! -f database/database.sqlite ]; then
        touch database/database.sqlite
        chown www-data:www-data database/database.sqlite
    fi

    # APP_KEY: si no viene en .env.docker, generar una por arranque (sólo demo;
    # para uso real fijar APP_KEY en .env.docker con `php artisan key:generate --show`)
    if [ -z "${APP_KEY:-}" ]; then
        APP_KEY=$(php artisan key:generate --show)
        export APP_KEY
    fi

    php artisan migrate --force

    # Demo opcional: sólo si la base está recién creada (0 usuarios)
    USER_COUNT=$(php -r "try { \$pdo = new PDO('sqlite:database/database.sqlite'); echo (string) \$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); } catch (Throwable \$e) { echo '0'; }")
    if [ "${SEED_DEMO:-false}" = "true" ] && [ "$USER_COUNT" = "0" ]; then
        php artisan db:seed --force
    fi
fi

exec "$@"
