# syntax=docker/dockerfile:1.6

FROM dunglas/frankenphp:1-php8.3

WORKDIR /app
ENV APP_ENV=prod
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
    && rm -rf /var/lib/apt/lists/*

# The gateway has no Composer dependencies — it only needs the autoloader — so
# composer is the sole build-time extension. opcache is the runtime one.
# Shopify API calls go through file_get_contents() over HTTPS (openssl is in the
# base image), so no ext-curl is required.
RUN install-php-extensions @composer opcache

COPY frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/
COPY frankenphp/Caddyfile /etc/caddy/Caddyfile

# Install dependencies first (better layer caching), then copy sources.
COPY composer.json composer.lock ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY . ./
RUN rm -rf frankenphp \
    && composer dump-autoload --classmap-authoritative --no-dev

# nginx on the host reverse-proxies to the port SERVER_NAME binds (see
# compose.yaml). 80/443 stay exposed for a standalone run where FrankenPHP
# terminates TLS itself.
EXPOSE 80 443
HEALTHCHECK --start-period=15s --interval=30s --timeout=5s \
    CMD curl -fsS "http://127.0.0.1:${GATEWAY_PORT:-8083}/health" || exit 1
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
