FROM php:7.1-fpm-alpine

RUN apk add --no-cache --virtual .persistent-deps \
        git \
        icu-libs \
        zlib

ENV APCU_VERSION 5.1.8

RUN set -xe \
    && apk add --no-cache --virtual .persistent-deps \
        icu-dev \
        zlib-dev \
        postgresql-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure pdo_pgsql --with-pgsql \
    && docker-php-ext-install \
        intl \
        pdo_pgsql \
        zip \
    && pecl install \
        apcu-${APCU_VERSION} \
    && docker-php-ext-enable --ini-name 20-apcu.ini apcu \
    && docker-php-ext-enable --ini-name 05-opcache.ini opcache \
    && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/php.ini

COPY docker/php/install-composer.sh /usr/local/bin/docker-app-install-composer

RUN chmod +x /usr/local/bin/docker-app-install-composer

RUN set -xe \
    && apk add --no-cache --virtual .fetch-deps \
        openssl \
    && docker-app-install-composer \
    && mv composer.phar /usr/local/bin/composer \
    && apk del .fetch-deps

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN composer global require "hirak/prestissimo:^0.3" --prefer-dist --no-progress --no-suggest --optimize-autoloader --classmap-authoritative \
    && composer clear-cache

WORKDIR /srv/coopcycle

COPY composer.json ./
COPY composer.lock ./

RUN mkdir -p \
        var/cache \
        var/logs \
        var/sessions \
    && composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress --no-suggest \
    && composer clear-cache \
# Permissions hack because setfacl does not work on Mac and Windows
    && chown -R www-data var/cache \
    && chown -R www-data var/logs \
    && chown -R www-data var/sessions \
    && chmod -R +w var/cache

COPY app app/
COPY bin bin/
COPY src src/
COPY web web/

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

COPY docker/php/start.sh /usr/local/bin/docker-app-start

RUN chmod +x /usr/local/bin/docker-app-start

# Needed to fix permissions error
USER 100

CMD ["docker-app-start"]
