# syntax=docker/dockerfile:1.15
#
# Stickers Manager — packaged on the Smals base-php image.
# Dependencies (vendor/) and assets (public/assets) are pre-built on the host via
# `make build-app` (Composer + AssetMapper in the cli-dev container), then shipped
# in the build context. See README / Makefile.
#
ARG BASE_IMAGE=docker.io/smalswebtech/base-php
ARG PHP_TAG=8.5

# —— Builder: lay out the app at /app/src/stickers and the .docker overlay ——————
FROM ${BASE_IMAGE}:${PHP_TAG}-cli-dev AS builder

USER root

COPY . /tmpfs

RUN mv /tmpfs/.docker /bootstrap \
 && mkdir -p /app/src \
 && mv /tmpfs /app/src/stickers \
 && rm -rf /app/src/stickers/var/* /app/src/stickers/.git

# —— Runtime (prod) ————————————————————————————————————————————————————————————
FROM ${BASE_IMAGE}:${PHP_TAG}-nginx AS prd

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
FROM ${BASE_IMAGE}:${PHP_TAG}-nginx-dev AS dev

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

USER 1001
WORKDIR /app/src/stickers

EXPOSE 9000/tcp 9090/tcp

HEALTHCHECK --start-period=10s --interval=10s --timeout=3s --retries=5 \
    CMD [ "$(supervisorctl -c /opt/etc/supervisord.conf status php-fpm nginx | grep -c RUNNING)" -eq 2 ] || exit 1
