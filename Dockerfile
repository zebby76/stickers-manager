# syntax=docker/dockerfile:1.29
#
# Stickers Manager — packaged on the Smals base-php image.
# Dependencies (vendor/) and assets (public/assets) are pre-built on the host via
# `make build-app` (Composer + AssetMapper in the cli-dev container), then shipped
# in the build context. See README / Makefile.
#
# The three images are pinned by digest: base-php republishes its tags, and only a
# digest shows it. Renovate updates them (see renovate.json).

# —— Builder: lay out the app at /app/src/stickers and the .docker overlay ——————
FROM docker.io/smalswebtech/base-php:8.5-cli-dev@sha256:9e4f66aeffeab17c4d0a8107b5b5456abe72ca8fa686d7f1ed102ba527e3fd19 AS builder

USER root

COPY . /tmpfs

RUN mv /tmpfs/.docker /bootstrap \
 && mkdir -p /app/src \
 && mv /tmpfs /app/src/stickers \
 && rm -rf /app/src/stickers/var/* /app/src/stickers/.git

# —— Runtime (prod) ————————————————————————————————————————————————————————————
FROM docker.io/smalswebtech/base-php:8.5-nginx@sha256:bc31fa92a3bc3174cc91c1276921c79e05e2bbed17b666350c5ee4603a149a21 AS prd

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
FROM docker.io/smalswebtech/base-php:8.5-nginx-dev@sha256:e4b05cbf3d7a6771c773e962d3b9a93ccfe349ed5e11a541e97ee0fa5cc9498a AS dev

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
