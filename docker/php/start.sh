#!/bin/sh
set -xe

# Detect the host IP
export DOCKER_BRIDGE_IP=$(ip ro | grep default | cut -d' ' -f 3)

if [ "$SYMFONY_ENV" = 'prod' ]; then
    composer install --prefer-dist --no-dev --no-progress --no-suggest --optimize-autoloader --classmap-authoritative
else
    composer install --prefer-dist --no-progress --no-suggest
fi

php bin/console doctrine:database:create --if-not-exists --env=dev
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis' --env=dev
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis_topology' --env=dev

php bin/console doctrine:database:create --if-not-exists --env=test
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis' --env=test
php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis_topology' --env=test

# Permissions hack because setfacl does not work on Mac and Windows
chown -R www-data var/cache && chown -R www-data var/logs && chown -R www-data var/sessions

exec php-fpm
