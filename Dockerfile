# Imagen del app (php-fpm 8.4) + build de assets Vite en el mismo stage:
# el plugin wayfinder corre `php artisan wayfinder:generate` durante el
# build de Vite y necesita PHP y vendor disponibles.
FROM php:8.4-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_sqlite bcmath intl pcntl posix opcache

# Node para el build de assets (el plugin wayfinder llama `php artisan`
# durante el build de Vite, por eso PHP y Node comparten imagen)
RUN apk add --no-cache nodejs npm && node --version && npm --version

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# --- dependencias PHP (capa cacheable) ---
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

# --- dependencias Node (capa cacheable) ---
COPY package.json package-lock.json ./
RUN npm ci

# --- código fuente ---
COPY . .

# DB vacía para que artisan/wayfinder arranquen durante el build
RUN mkdir -p database storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && touch database/database.sqlite \
    && composer dump-autoload --optimize

# Assets: los VITE_REVERB_* se hornean acá (Echo los lee en build time).
# Los puertos tienen que coincidir con los expuestos en compose.yaml.
ARG VITE_APP_NAME="Lions Dashboard"
ARG VITE_REVERB_APP_KEY=lionsreverbkey
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=8089
ARG VITE_REVERB_SCHEME=http
RUN npm run build

# --- configuración runtime ---
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    SESSION_SECURE_COOKIE=false

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
