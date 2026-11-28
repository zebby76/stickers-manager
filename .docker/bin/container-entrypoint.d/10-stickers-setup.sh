#!/usr/bin/env bash
#
# Stickers Manager — one-time application setup, run by the base image's
# entrypoint (base.d/90-app.sh) on first container boot. It runs as a child
# process under `bash -e -o pipefail`, so an unguarded failure aborts the boot:
# guard whatever is allowed to fail. `exit` ends this hook, not the container.
#
APP_DIR="/app/src/stickers"
CONSOLE="${APP_DIR}/bin/console"

# Decorative: a banner that cannot be read must never stop the container.
print-banner /opt/config/banner || true

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
