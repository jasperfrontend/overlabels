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

exec "$@"
