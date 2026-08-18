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

# ---------------------------------------------------------------------------
# TEMPORARY: one-shot import of the remaining Cloudinary-hosted images onto the
# R2 `images` disk. Deploys here are push-to-GitHub, so a one-off prod command
# has to ride along with a deploy rather than being run by hand.
#
# Runs after migrations because it needs the renamed `image_uploads` table.
# Idempotent: it selects rows whose URL still points at Cloudinary, so once
# every image has moved this is three cheap LIKE queries and an early exit.
#
# `|| echo` because a failure here must never abort a deploy - the command
# exits non-zero if ANY single image failed, and the rows it could not move
# simply keep their Cloudinary URLs and get retried on the next boot. The
# outer `timeout` bounds a hung Cloudinary fetch: without it, 11 images at a
# 30s HTTP timeout each could stall a container start for five minutes.
#
# REMOVE THIS BLOCK once prod reports "No Cloudinary URLs left in the
# database", along with the command, ENTRYPOINT_RUN_IMAGE_MIGRATION in
# config/deploy.yml, and the migration section of
# docs/deploy/image-storage.md.
# ---------------------------------------------------------------------------
if [ "${ENTRYPOINT_RUN_IMAGE_MIGRATION:-0}" = "1" ]; then
    timeout 420 php artisan images:migrate-from-cloudinary \
        || echo "images:migrate-from-cloudinary did not complete (continuing - unmoved rows keep their Cloudinary URLs and retry next boot)"
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

# Pre-render help/reference OG images on the web role only. ~135 PNGs in a
# few seconds; subsequent boots skip work for entries whose context hash is
# already on disk.
if [ "${ENTRYPOINT_RUN_OG_GENERATE:-0}" = "1" ]; then
    php artisan og:generate || echo "og:generate failed (continuing - layout falls back to /ogimage.png)"
fi

exec "$@"
