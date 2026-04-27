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

# Pre-render help/reference OG images on the web role only. ~135 PNGs in a
# few seconds; subsequent boots skip work for entries whose context hash is
# already on disk.
if [ "${ENTRYPOINT_RUN_OG_GENERATE:-0}" = "1" ]; then
    php artisan og:generate || echo "og:generate failed (continuing - layout falls back to /ogimage.png)"
fi

exec "$@"
