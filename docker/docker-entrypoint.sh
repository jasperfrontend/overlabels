#!/usr/bin/env bash
set -euo pipefail

# Build a fresh package/route/view/event/config cache at boot.
# Doing this here (not at image-build time) means the cache reflects whatever
# env vars Kamal injects for this specific deployment.
#
# Skipped for non-PHP roles (queue, scheduler, reverb) by passing
# ENTRYPOINT_SKIP_CACHE=1 in the role's env.
if [ "${ENTRYPOINT_SKIP_CACHE:-0}" != "1" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Flush the Redis application cache once per deploy (web role only).
# A code change can alter the SHAPE of a cached value - e.g. the tag picker
# cache stored Eloquent objects that Laravel 13's serializable_classes=false
# could no longer rebuild - leaving stale entries that poison the new code
# until their TTL lapses. Clearing here makes deploys self-correcting.
# Safe because sessions live in Postgres (SESSION_DRIVER=database, no logout)
# and Redis is already non-persistent (--save "" + allkeys-lru), so the app
# already tolerates a cold cache; the cost is a lazy per-user re-fetch.
if [ "${ENTRYPOINT_RUN_CACHE_CLEAR:-0}" = "1" ]; then
    php artisan cache:clear || echo "cache:clear failed (continuing - stale entries self-heal on TTL)"
fi

# Migrations run only on the role flagged with ENTRYPOINT_RUN_MIGRATIONS=1.
# Set this on the `web` role only so it happens once per deploy.
if [ "${ENTRYPOINT_RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force --no-interaction
fi

# storage:link creates public/storage -> storage/app/public so files written
# via the public disk (e.g. ElevenLabs TTS mp3 cache) are reachable over HTTP.
# Web role only; --force is safe because the entire public/ tree is rebuilt
# from the image on each container start.
if [ "${ENTRYPOINT_RUN_STORAGE_LINK:-0}" = "1" ]; then
    php artisan storage:link --force || echo "storage:link failed (continuing - public-disk URLs will 404)"
fi

# Build the help/reference search index. Composer's post-autoload-dump hook
# would normally do this, but the Dockerfile runs `composer install
# --no-scripts` so the hook never fires inside the image. Running here keeps
# /help-reference-index.json fresh per deploy. Web role only.
if [ "${ENTRYPOINT_RUN_HELP_INDEX:-0}" = "1" ]; then
    php artisan help:build-index || echo "help:build-index failed (continuing - search will stay on Loading...)"
fi

# Pre-render help/reference and update-post OG images, web role only, in the
# BACKGROUND, so the server starts without waiting for it.
#
# It used to run here in the foreground: ~225 PNGs, ~23 s, every boot (public/og
# is not a volume, so a fresh container has none of them). That kept every web
# boot within a few seconds of Kamal's healthcheck window, and on 2026-11-28 one
# went over: the deploy failed AFTER the migrations above had run, leaving the
# old code serving a migrated database.
#
# Nothing waits on it. A page whose card is not rendered yet renders it on that
# request (OgImageService::renderOrCached()), so the only cost of the warm-up
# still running is a slower first scrape in the seconds after a deploy. The
# subshell outlives the exec below and logs to the same stdout.
if [ "${ENTRYPOINT_RUN_OG_GENERATE:-0}" = "1" ]; then
    ( php artisan og:generate || echo "og:generate failed (continuing - pages render their own card on request)" ) &
fi

exec "$@"
