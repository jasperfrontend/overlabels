# syntax=docker/dockerfile:1.7

# ---------- Stage 1: Vite asset build ----------
FROM node:22-alpine AS assets
WORKDIR /app

# npm ci depends only on package*.json, so keep it before anything that changes
# every commit (like APP_COMMIT_SHA). Previously the ENV for APP_COMMIT_SHA sat
# above this COPY/RUN pair, which invalidated the npm ci layer on every deploy
# and cost ~60-90s of needless install time per build.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Build-time args that Vite inlines into the client bundle. These can change
# per-deploy (APP_COMMIT_SHA in particular) so they sit downstream of npm ci.
ARG APP_COMMIT_SHA=dev
ARG VITE_APP_NAME=Overlabels
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV APP_COMMIT_SHA=${APP_COMMIT_SHA} \
    VITE_APP_NAME=${VITE_APP_NAME} \
    VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}

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

# resvg: SVG -> PNG renderer for help/reference OG images. Pinned so dev and
# prod produce byte-identical output (cache invalidation is template-version
# based, so a binary upgrade alone won't regenerate cached PNGs).
ARG RESVG_VERSION=0.47.0
RUN curl -fsSL "https://github.com/linebender/resvg/releases/download/v${RESVG_VERSION}/resvg-linux-x86_64.tar.gz" \
        -o /tmp/resvg.tar.gz \
    && tar -xzf /tmp/resvg.tar.gz -C /usr/local/bin \
    && rm /tmp/resvg.tar.gz \
    && chmod +x /usr/local/bin/resvg \
    && resvg --version

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

# Raise PHP upload limits to match Laravel's max:10240 (10MB) validation
# on /cloudinary/upload. The frankenphp base image inherits PHP's stock
# production defaults (upload_max_filesize=2M, post_max_size=8M), which
# would reject clipboard-pasted screenshots at the SAPI level before
# Laravel could surface a useful error.
COPY docker/php-uploads.ini $PHP_INI_DIR/conf.d/zz-uploads.ini

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
