#!/bin/sh

echo "Installing build tools..."
apk add autoconf make g++ linux-headers

echo "Installing and enabling xdebug..."
pecl install -f xdebug
docker-php-ext-enable xdebug

echo "Configuring xdebug..."
FILE=/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

IP=$(/sbin/ip route|awk '/default/ { print $3 }')

# New config file for xdebug 3
echo "xdebug.client_host=host.docker.internal" >> $FILE
echo "xdebug.client_port=9003" >> $FILE
# https://xdebug.org/docs/all_settings#mode
echo "xdebug.mode=debug,profile" >> $FILE
# https://xdebug.org/docs/all_settings#start_with_request
echo "xdebug.start_with_request=trigger" >> $FILE
echo "xdebug.discover_client_host=false" >> $FILE
echo "xdebug.idekey=docker" >> $FILE
echo "xdebug.output_dir=/var/www/html/var/profiles" >> $FILE
echo "All done! To start using xdebug please restart this container"
