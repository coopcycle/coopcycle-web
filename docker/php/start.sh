#!/bin/sh
set -xe

# Detect the host IP
export DOCKER_BRIDGE_IP=$(ip ro | grep default | cut -d' ' -f 3)

if [ ! -d var/jwt ]; then
    mkdir -p var/jwt
fi

if [ ! -f var/jwt/private.pem ]; then
    printf "\e[0;32mGenerating RSA key to encrypt webtokens..\e[0m\n"
    openssl genrsa -out var/jwt/private.pem -passout pass:coursiers -aes256 4096;
fi

if [ ! -f var/jwt/public.pem ]; then
    openssl rsa -pubout -passin pass:coursiers -in var/jwt/private.pem -out var/jwt/public.pem
fi

if [ "$SYMFONY_ENV" = 'prod' ]; then
    composer install --prefer-dist --no-dev --no-progress --no-suggest --optimize-autoloader --classmap-authoritative
else
    composer install --prefer-dist --no-progress --no-suggest
fi

php bin/console doctrine:database:create --if-not-exists --env=$SYMFONY_ENV
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis' --env=$SYMFONY_ENV
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis_topology' --env=$SYMFONY_ENV
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS pg_trgm' --env=$SYMFONY_ENV

php bin/console doctrine:database:create --if-not-exists --env=test
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis' --env=test
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis_topology' --env=test
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS pg_trgm' --env=test

exec php-fpm
