# syntax=docker/dockerfile:1.7

# ---------- Stage 1: Vite asset build ----------
FROM node:22-alpine AS assets
WORKDIR /app

ARG APP_COMMIT_SHA=dev
ENV APP_COMMIT_SHA=${APP_COMMIT_SHA}

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY resources resources
COPY public public
COPY vite.config.mts tsconfig.json eslint.config.js components.json ./
COPY routes routes
COPY artisan ./

RUN npm run build

# ---------- Stage 2: Composer vendor ----------
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
COPY database database
COPY artisan ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# ---------- Stage 3: Runtime ----------
FROM dunglas/frankenphp:1-php8.4 AS runtime

# install-php-extensions handles compilation, deps, and config in one shot
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        pcntl \
        pdo_pgsql \
        redis \
        intl \
        gd \
        bcmath \
        exif \
        zip \
        opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy app source first (least likely to invalidate vendor/assets cache layers)
COPY . .

# Drop any locally-built artefacts
RUN rm -rf vendor public/build bootstrap/cache/*.php

# Bring in vendor + built Vite assets
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Storage + cache need to be writable by the FrankenPHP user (default www-data inside the image)
RUN mkdir -p storage/framework/{cache,sessions,views,testing} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Caddyfile lives at /etc/caddy/Caddyfile by default in this image
COPY docker/frankenphp.Caddyfile /etc/caddy/Caddyfile
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 80 443 443/udp

# FrankenPHP base image has a HEALTHCHECK that probes Caddy's admin endpoint
# on :2019 - only makes sense for the web role. Non-web roles (queue,
# scheduler, reverb) run php CLI processes with no Caddy admin listener and
# would be stuck in "unhealthy" forever. kamal-proxy does its own HTTP probe
# of the web role via /up, which is the check that actually matters.
HEALTHCHECK NONE

ENTRYPOINT ["docker-entrypoint"]

# Default CMD - the web role. Other Kamal roles override this with their own cmd.
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
