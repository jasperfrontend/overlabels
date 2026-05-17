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

# Pre-render help/reference OG images on the web role only. ~135 PNGs in a
# few seconds; subsequent boots skip work for entries whose context hash is
# already on disk.
if [ "${ENTRYPOINT_RUN_OG_GENERATE:-0}" = "1" ]; then
    php artisan og:generate || echo "og:generate failed (continuing - layout falls back to /ogimage.png)"
fi

exec "$@"
