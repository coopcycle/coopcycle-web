#!/bin/sh
set -xe

# Detect the host IP
export DOCKER_BRIDGE_IP=$(ip ro | grep default | cut -d' ' -f 3)

mkdir -p var/logs
chgrp www-data var/logs
chmod g+w var/logs

if [ ! -d var/jwt ]; then
    mkdir -p var/jwt
fi

if [ ! -f var/jwt/private.pem ]; then
    printf "\e[0;32mGenerating RSA key to encrypt webtokens..\e[0m\n"
    openssl genrsa -out var/jwt/private.pem -passout pass:coursiers -aes256 4096;
    chgrp www-data var/jwt/private.pem
    chmod 644 var/jwt/private.pem
fi

if [ ! -f var/jwt/public.pem ]; then
    openssl rsa -pubout -passin pass:coursiers -in var/jwt/private.pem -out var/jwt/public.pem
    chgrp www-data var/jwt/public.pem
    chmod 644 var/jwt/public.pem
fi

composer self-update --2

if [ "$APP_ENV" = 'prod' ]; then
    composer install --prefer-dist --no-plugins --no-progress --no-dev --optimize-autoloader --classmap-authoritative
else
    composer install --prefer-dist --no-plugins --no-progress
fi

php bin/console doctrine:database:create --if-not-exists --env=$APP_ENV
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis' --env=$APP_ENV
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis_topology' --env=$APP_ENV
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS pg_trgm' --env=$APP_ENV

php bin/console doctrine:database:create --if-not-exists --env=test
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis' --env=test
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis_topology' --env=test
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS pg_trgm' --env=test

exec docker-php-entrypoint "$@"
