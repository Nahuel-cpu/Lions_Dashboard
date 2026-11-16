#!/bin/sh
set -e

cd /var/www/html

# El FS es persistente entre restarts: limpiar el flag de readiness anterior
rm -f /tmp/ready

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

    # APP_KEY: si no viene en .env.docker, generarla una vez y guardarla en el
    # volumen de storage para que sobreviva a restarts (si cambia en cada
    # arranque, se invalidan sesiones y cookies cifradas).
    if [ -z "${APP_KEY:-}" ]; then
        KEY_FILE=storage/app/.app_key
        if [ -f "$KEY_FILE" ]; then
            APP_KEY=$(cat "$KEY_FILE")
        else
            APP_KEY=$(php artisan key:generate --show)
            echo "$APP_KEY" > "$KEY_FILE"
            chown www-data:www-data "$KEY_FILE"
        fi
        export APP_KEY
    fi

    php artisan migrate --force

    # Demo opcional: sólo si la base está recién creada (0 usuarios)
    USER_COUNT=$(php -r "try { \$pdo = new PDO('sqlite:database/database.sqlite'); echo (string) \$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); } catch (Throwable \$e) { echo '0'; }")
    if [ "${SEED_DEMO:-false}" = "true" ] && [ "$USER_COUNT" = "0" ]; then
        php artisan db:seed --force
    fi
fi

# Migración y seed terminaron: marcar readiness para el healthcheck de compose
touch /tmp/ready

exec "$@"
