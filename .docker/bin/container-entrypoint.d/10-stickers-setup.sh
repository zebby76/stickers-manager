#!/usr/bin/env bash
#
# Stickers Manager — one-time application setup, run by the base image's
# entrypoint (base.d/90-app.sh) on first container boot. Sourced, so it must not
# `exit` or rely on `set -e`; failures are logged but never abort the container.
#
APP_DIR="/app/src/stickers"
CONSOLE="${APP_DIR}/bin/console"

log "INFO" "+---- Stickers Manager setup -----"

if [ "${APP_SKIP_SETUP:-false}" = "true" ]; then
    log "INFO" "  APP_SKIP_SETUP=true → skipping application setup"
else
    # Orchestration (compose depends_on / k8s) is expected to start the DB first.
    log "INFO" "  Clearing the cache"
    php "${CONSOLE}" cache:clear --no-interaction || log "WARN" "  cache:clear failed"

    log "INFO" "  Running database migrations"
    php "${CONSOLE}" doctrine:migrations:migrate --no-interaction --allow-no-migration \
        || log "WARN" "  migrations failed (database not ready?)"
fi

true
