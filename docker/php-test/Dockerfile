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
    && apk add --no-cache \
        xvfb \
        ttf-freefont \
        fontconfig \
        dbus \
        freetype \
        libpng \
        libjpeg-turbo \
        freetype-dev \
        libpng-dev \
        libjpeg-turbo-dev \
    && apk add qt5-qtbase-dev \
        wkhtmltopdf \
        --no-cache \
        --repository http://dl-3.alpinelinux.org/alpine/edge/testing/ \
        --allow-untrusted \
    && mv /usr/bin/wkhtmltopdf /usr/bin/wkhtmltopdf-origin \
    && echo $'#!/usr/bin/env sh\n\
Xvfb :0 -screen 0 1024x768x24 -ac +extension GLX +render -noreset & \n\
DISPLAY=:0.0 wkhtmltopdf-origin $@ \n\
killall Xvfb' > /usr/bin/wkhtmltopdf \
    && chmod +x /usr/bin/wkhtmltopdf \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure pdo_pgsql --with-pgsql \
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

COPY php.ini /usr/local/etc/php/php.ini

COPY install-composer.sh /usr/local/bin/docker-app-install-composer

RUN chmod +x /usr/local/bin/docker-app-install-composer

RUN set -xe \
    && apk add --no-cache --virtual .fetch-deps \
        openssl \
    && docker-app-install-composer \
    && mv composer.phar /usr/local/bin/composer \
    && apk del .fetch-deps
