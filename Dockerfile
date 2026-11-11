# syntax=docker/dockerfile:1.15
#
# Stickers Manager — packaged on the Smals base-php image.
# Dependencies (vendor/) and assets (public/assets) are pre-built on the host via
# `make build-app` (Composer + AssetMapper in the cli-dev container), then shipped
# in the build context. See README / Makefile.
#
# The three images are pinned by digest: base-php republishes its tags, and only a
# digest shows it. Renovate updates them (see renovate.json).

# —— Builder: lay out the app at /app/src/stickers and the .docker overlay ——————
FROM docker.io/smalswebtech/base-php:8.5-cli-dev@sha256:2879199f66108e2aed05414d3368db0792db52e7f26395c5bb3e51e33af761bc AS builder

USER root

COPY . /tmpfs

RUN mv /tmpfs/.docker /bootstrap \
 && mkdir -p /app/src \
 && mv /tmpfs /app/src/stickers \
 && rm -rf /app/src/stickers/var/* /app/src/stickers/.git

# —— Runtime (prod) ————————————————————————————————————————————————————————————
FROM docker.io/smalswebtech/base-php:8.5-nginx@sha256:dd6b44e0492ec9d90f32057cc78c589bb9e4101f5d6d920f4fbd824b1ab1d4df AS prd

USER root

# .docker overlay → /opt (nginx vhost .tmpl + entrypoint setup script)
COPY --from=builder --chmod=775 --chown=1001:0 /bootstrap/ /opt/
# application code → /app/src/stickers
COPY --from=builder --chmod=775 --chown=1001:0 /app/ /app/

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    VARNISH_ENABLED=false \
    NGINX_REAL_IP_ENABLED=true \
    NGINX_CLIENT_MAX_BODY_SIZE=8m \
    NGINX_FASTCGI_CACHE_ENABLED=true \
    NGINX_SOFT_THROTTLE_ENABLED=true \
    NGINX_SOFT_THROTTLE_DRY_RUN_ENABLED=off \
    PHP_OPENTELEMETRY_ENABLED=true

USER 1001
WORKDIR /app/src/stickers

# 9000 = app (nginx) · 9090 = base metrics server (nginx VTS /metrics + php-fpm /status)
EXPOSE 9000/tcp 9090/tcp

HEALTHCHECK --start-period=10s --interval=10s --timeout=3s --retries=5 \
    CMD [ "$(supervisorctl -c /opt/etc/supervisord.conf status php-fpm nginx | grep -c RUNNING)" -eq 2 ] || exit 1

# —— Runtime (dev) —————————————————————————————————————————————————————————————
FROM docker.io/smalswebtech/base-php:8.5-nginx-dev@sha256:338e5a761761e2a88ca7db2a3c5dd2a3dc9a2f7f09d9a93188c2851275bd5137 AS dev

USER root

COPY --from=builder --chmod=775 --chown=1001:0 /bootstrap/ /opt/
COPY --from=builder --chmod=775 --chown=1001:0 /app/ /app/

ENV APP_ENV=dev \
    APP_DEBUG=1 \
    VARNISH_ENABLED=false \
    NGINX_REAL_IP_ENABLED=true \
    NGINX_CLIENT_MAX_BODY_SIZE=8m \
    NGINX_FASTCGI_CACHE_ENABLED=false \
    NGINX_SOFT_THROTTLE_ENABLED=true \
    NGINX_SOFT_THROTTLE_DRY_RUN_ENABLED=on

# Same as prd: the extension ships in the image but is gated behind this flag.
# Left off, the auto-instrumentation package warns at autoload -- and this image
# prints warnings to STDOUT, so they land inside every response body, ahead of the
# doctype, breaking every served JS module.
ENV PHP_OPENTELEMETRY_ENABLED=true

USER 1001
WORKDIR /app/src/stickers

EXPOSE 9000/tcp 9090/tcp

HEALTHCHECK --start-period=10s --interval=10s --timeout=3s --retries=5 \
    CMD [ "$(supervisorctl -c /opt/etc/supervisord.conf status php-fpm nginx | grep -c RUNNING)" -eq 2 ] || exit 1
