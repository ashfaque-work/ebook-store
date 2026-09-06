#!/bin/sh
set -e

# Caches are built at boot rather than at image build time because they bake
# in environment values, and the environment is not known until the container
# starts on the platform.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# storage:link is a no-op on object storage, and harmless otherwise.
php artisan storage:link --quiet || true

exec "$@"
