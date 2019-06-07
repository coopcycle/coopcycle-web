FROM php:7.2-fpm-alpine

RUN apk update

RUN apk add --no-cache \
        git \
        icu \
        zlib

ENV APCU_VERSION 5.1.8

RUN set -xe \
    && apk add --no-cache \
        icu-dev \
        postgresql-dev \
        zlib-dev \
    && apk add --no-cache \
        libpng \
        libjpeg-turbo \
        freetype \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && apk add --no-cache \
        unzip \
        chromium \
        chromium-chromedriver \
    && apk add --no-cache \
        jpegoptim \
        optipng \
        pngquant \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure pdo_pgsql \
    && docker-php-ext-configure gd \
        --with-gd \
        --with-freetype-dir=/usr/include/ \
        --with-png-dir=/usr/include/ \
        --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install \
        intl \
        pdo_pgsql \
        zip \
        bcmath \
        gd \
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
    && sh /usr/local/bin/docker-app-install-composer \
    && mv composer.phar /usr/local/bin/composer \
    && apk del .fetch-deps

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN composer global require "hirak/prestissimo:^0.3" --prefer-dist --no-progress --no-suggest --optimize-autoloader --classmap-authoritative \
    && composer clear-cache

ENV COMPOSER_ALLOW_SUPERUSER 0

ENV PANTHER_CHROME_BINARY /usr/bin/chromium-browser
ENV PANTHER_CHROME_DRIVER_BINARY /usr/lib/chromium/chromedriver
ENV PANTHER_NO_SANDBOX 1

WORKDIR /srv/coopcycle

COPY composer.json ./
COPY composer.lock ./

RUN mkdir -p var/cache var/logs var/sessions vendor \
    # Permissions hack because setfacl does not work on Mac and Windows
    && chown -R www-data var/cache \
    && chown -R www-data var/logs \
    && chown -R www-data var/sessions \
    && chmod -R +w var/cache \
    && chmod a+w var \
    && chown -R www-data vendor

COPY app app/
COPY bin bin/
COPY src src/
COPY web web/

COPY docker/php/start.sh /usr/local/bin/docker-app-start

RUN chmod +x /usr/local/bin/docker-app-start
RUN chmod +x bin/demo

# Needed to fix permissions error
USER 82

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev
RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress --no-suggest \
    && composer clear-cache

ENTRYPOINT ["docker-app-start"]
