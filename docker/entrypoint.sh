#!/bin/sh
set -e

# Caches are built at boot rather than at image build time because they bake
# in environment values, and the environment is not known until the container
# starts on the platform.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Render's pre-deploy command, the usual home for this, is a paid-plan feature,
# so on the free plan migrations have to run at boot or never run at all.
# `migrate` is idempotent and the free plan runs a single instance, so a restart
# after an idle sleep is a no-op. Never migrate:fresh here.
#
# Turn this off the moment there is more than one instance, and move it back to
# a pre-deploy command: two containers migrating at once is a broken schema.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

# storage:link is a no-op on object storage, and harmless otherwise.
php artisan storage:link --quiet || true

exec "$@"
