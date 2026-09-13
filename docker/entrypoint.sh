#!/bin/sh
set -e

# Boot has to finish before the platform's health check gives up, and when it
# does not the only evidence is "timed out waiting for health check" — which
# says nothing about which step was slow. Each step is timed and announced, so
# a repeat is diagnosable from the deploy log instead of by guesswork.
step() {
    echo "[boot] $1" >&2
    started=$(date +%s)
    shift
    "$@"
    echo "[boot] done in $(($(date +%s) - started))s" >&2
}

boot_started=$(date +%s)

# Hosting platforms tell the container which port to bind through $PORT, and
# route nothing to a service listening elsewhere. nginx.conf ships with 8080 so
# the image runs unchanged locally; this rewrites it when the platform asks for
# something else.
echo "[boot] binding nginx to port ${PORT:-8080}" >&2
sed -i "s/listen 8080;/listen ${PORT:-8080};/" /etc/nginx/nginx.conf

# Caches are built at boot rather than at image build time because they bake
# in environment values, and the environment is not known until the container
# starts on the platform.
step "caching config" php artisan config:cache
step "caching routes" php artisan route:cache
step "caching views" php artisan view:cache

# Render's pre-deploy command, the usual home for this, is a paid-plan feature,
# so on the free plan migrations have to run at boot or never run at all.
# `migrate` is idempotent and the free plan runs a single instance, so a restart
# after an idle sleep is a no-op. Never migrate:fresh here.
#
# Turn this off the moment there is more than one instance, and move it back to
# a pre-deploy command: two containers migrating at once is a broken schema.
#
# The database is the one step that reaches off the machine, so it is the one
# that can hang: a serverless Postgres waking from zero, or a network that is
# simply not there yet. A timeout means the container still boots and still
# answers the health check — a store that is up with a schema one migration
# behind is recoverable, a container that never starts is not, and the
# dashboard says so either way.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[boot] running migrations" >&2
    started=$(date +%s)

    # Falling back to an untimed run rather than trusting a `timeout` that may
    # not be there: skipping migrations silently is far worse than waiting.
    if command -v timeout >/dev/null 2>&1; then
        run_migrate() { timeout "${MIGRATION_TIMEOUT:-120}" php artisan migrate --force; }
    else
        run_migrate() { php artisan migrate --force; }
    fi

    if run_migrate; then
        echo "[boot] done in $(($(date +%s) - started))s" >&2
    else
        echo "[boot] MIGRATIONS DID NOT COMPLETE after $(($(date +%s) - started))s — booting anyway" >&2
        echo "[boot] check the database, then redeploy or run migrate by hand" >&2
    fi
fi

# storage:link is a no-op on object storage, and harmless otherwise.
php artisan storage:link --quiet || true

echo "[boot] ready in $(($(date +%s) - boot_started))s, starting web server" >&2

exec "$@"
