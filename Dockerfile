# syntax=docker/dockerfile:1.7
#
# Una sola imagen para soporte-app, soporte-cola y soporte-tareas: cambia solo
# el comando de arranque (ver docker/entrypoint.sh).

# --- 1. Dependencias de PHP, sin las de desarrollo -------------------------
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress \
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# --- 2. Assets con Vite ------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
# Tailwind también lee las vistas de paginación de Laravel.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# --- 3. Imagen final: PHP-FPM + Nginx ---------------------------------------
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor su-exec curl \
        icu-libs libpng libjpeg-turbo libwebp freetype \
    && apk add --no-cache --virtual .compilar $PHPIZE_DEPS linux-headers \
        icu-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd intl opcache pcntl \
    && apk del .compilar

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-soporte.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-soporte.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# storage/ no viaja en la imagen (.dockerignore): se crea vacío y en producción
# storage/app se monta como volumen compartido por los tres contenedores.
RUN chmod +x /usr/local/bin/entrypoint \
    && sed -i 's/\r$//' /usr/local/bin/entrypoint \
    && mkdir -p storage/app/private storage/app/public storage/logs \
        storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && su-exec www-data php artisan package:discover --ansi

EXPOSE 80

ENTRYPOINT ["entrypoint"]
CMD ["web"]
