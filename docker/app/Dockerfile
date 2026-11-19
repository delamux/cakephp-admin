# syntax=docker/dockerfile:1
# CakePHP Admin — PHP 8.5 FPM + nginx. Targets: dev (bind mount), prod (self-contained), web (nginx + static).

# ---------- PHP base: extensions + common php.ini ----------
FROM php:8.5-fpm AS base
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
# PostgreSQL, intl (CakePHP), gd (Dompdf images, photo re-encoding), exif (photo orientation), zip
# (composer), opcache, pcntl (queue worker: on SIGTERM it finishes the current job before exiting)
RUN install-php-extensions pdo_pgsql intl mbstring gd exif zip opcache pcntl
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/app/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf
WORKDIR /app

# ---------- dev: composer + git, code via bind mount ----------
FROM base AS dev
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/app/php-dev.ini /usr/local/etc/php/conf.d/zz-app-dev.ini
ENV COMPOSER_ALLOW_SUPERUSER=1

# ---------- vendor: production dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------- assets: compiled Tailwind + Basecoat (Node only at build time) ----------
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources/css resources/css
COPY templates templates
COPY src src
COPY --from=vendor /app/vendor/delamux/cakephp-basecoat-theme/templates vendor/delamux/cakephp-basecoat-theme/templates
COPY --from=vendor /app/vendor/delamux/cakephp-basecoat-theme/src vendor/delamux/cakephp-basecoat-theme/src
RUN npm run build:css \
    && mkdir -p webroot/js/basecoat webroot/js/vendor webroot/font/geist \
    && cp node_modules/basecoat-css/dist/js/all.min.js node_modules/basecoat-css/dist/js/chart.min.js webroot/js/basecoat/ \
    && cp node_modules/chart.js/dist/chart.umd.js node_modules/htmx.org/dist/htmx.min.js webroot/js/vendor/ \
    && cp node_modules/@fontsource-variable/geist/files/geist-latin-wght-normal.woff2 \
          node_modules/@fontsource-variable/geist/files/geist-latin-ext-wght-normal.woff2 webroot/font/geist/

# ---------- prod: self-contained PHP-FPM image ----------
FROM base AS prod
COPY docker/app/php-prod.ini /usr/local/etc/php/conf.d/zz-app-prod.ini
COPY --from=vendor /app /app
COPY --from=assets /build/webroot/css/app.css /app/webroot/css/app.css
COPY --from=assets /build/webroot/js/basecoat /app/webroot/js/basecoat
COPY --from=assets /build/webroot/js/vendor /app/webroot/js/vendor
COPY --from=assets /build/webroot/font/geist /app/webroot/font/geist
ENV DEBUG=false \
    THEME_COMPILED_ASSETS=true \
    ASSET_TIMESTAMP=force \
    LOG_TO_STDERR=true \
    HTTPS_ENFORCE=true \
    CSP_REPORT_ONLY=false
# Plugin assets into webroot (served by nginx), writable tmp/logs, no root at runtime
RUN SECURITY_SALT=build-time-only bin/cake plugin assets copy \
    && mkdir -p tmp logs storage/uploads \
    && chown -R www-data:www-data tmp logs storage
USER www-data

# ---------- web: nginx with the static files of the prod image ----------
FROM nginx:1.29-alpine AS web
# PHP-FPM service name (Coolify: ${SERVICE_NAME_APP})
ENV PHP_FPM_HOST=app
COPY docker/nginx/00-upstream.conf.template /etc/nginx/templates/00-upstream.conf.template
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/app-locations.conf /etc/nginx/app-locations.conf
COPY docker/nginx/https.conf /etc/nginx/https.conf.template
COPY docker/nginx/10-enable-https.sh /docker-entrypoint.d/10-enable-https.sh
RUN chmod 755 /docker-entrypoint.d/10-enable-https.sh
COPY --from=prod /app/webroot /app/webroot
