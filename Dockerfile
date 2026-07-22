# syntax=docker/dockerfile:1
#
# Real Bingo backend — production image for Render.com
# Apache + PHP 8.3, serving Laravel from /public.

# ─── Stage 1: build front-end assets (Vite + Tailwind) ──────────────────────
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json vite.config.js ./
RUN npm install --no-audit --no-fund
COPY resources ./resources
COPY public ./public
RUN npm run build

# ─── Stage 2: runtime ───────────────────────────────────────────────────────
FROM php:8.3-apache AS runtime

# PHP extensions. bcmath is REQUIRED (all wallet money math uses it);
# pdo_pgsql for Postgres; intl/gd/zip for Filament.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip ca-certificates \
        libpq-dev libicu-dev libzip-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql bcmath intl zip gd opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Production PHP settings
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'expose_php=Off'; \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=16M'; \
        echo 'post_max_size=16M'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Install PHP deps first so Docker caches this layer between code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction \
        --no-scripts --no-autoloader

# App source + the compiled front-end assets from stage 1
COPY . .
COPY --from=assets /app/public/build ./public/build

# Dummy key so `artisan` can boot during build; the real one comes from Render env.
ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache

COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Render injects $PORT; entrypoint rewrites Apache to listen on it.
ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["apache2-foreground"]
