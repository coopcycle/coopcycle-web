FROM php:8.3-fpm-alpine3.18

RUN apk update

RUN apk add --no-cache \
        git \
        icu \
        zlib

ENV APCU_VERSION 5.1.23
ENV PHPREDIS_VERSION 6.0.2

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
        libzip-dev \
    && apk add --no-cache \
        jpegoptim \
        optipng \
        pngquant \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure pdo_pgsql \
    && docker-php-ext-configure gd \
        --enable-gd \
        --with-freetype=/usr/include/ \
        --with-jpeg=/usr/include/ \
    && docker-php-ext-install \
        intl \
        pdo_pgsql \
        zip \
        bcmath \
        gd \
        pcntl \
        ftp \
    && pecl install \
        apcu-${APCU_VERSION} \
        redis-${PHPREDIS_VERSION} \
    && docker-php-ext-enable --ini-name 20-apcu.ini apcu \
    && docker-php-ext-enable --ini-name 05-opcache.ini opcache \
    && docker-php-ext-enable redis \
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

# Add healthcheck for PHP-FPM
# https://github.com/docker-library/php/issues/366
# https://github.com/renatomefi/php-fpm-healthcheck

RUN apk add --no-cache fcgi

RUN set -xe && echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/zz-docker.conf

RUN wget -O /usr/local/bin/php-fpm-healthcheck \
    https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/master/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

HEALTHCHECK \
  CMD php-fpm-healthcheck || exit 1

COPY composer.json ./
COPY composer.lock ./

RUN mkdir -p var/cache vendor \
    # Permissions hack because setfacl does not work on Mac and Windows
    && chown -R www-data var \
    && chmod -R a+w var \
    && chown -R www-data vendor

COPY app app/
COPY bin bin/
COPY src src/
COPY web web/

COPY docker/php/start.sh /usr/local/bin/docker-app-start
COPY docker/php/enable-xdebug.sh /usr/local/bin/enable-xdebug

RUN chmod +x /usr/local/bin/docker-app-start
RUN chmod +x /usr/local/bin/enable-xdebug
RUN chmod +x bin/demo

RUN git config --global --add safe.directory /var/www/html

# allow superuser to run plugins as root
# ref https://stackoverflow.com/questions/68973158/sh-symfony-cmd-command-not-found
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV APP_ENV dev
ENV APP_DEBUG 1

ENTRYPOINT ["docker-app-start"]
CMD ["php-fpm"]
