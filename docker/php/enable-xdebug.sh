#!/bin/sh

echo "Installing build tools..."
apk add autoconf make g++

echo "Installing and enabling xdebug..."
pecl install -f xdebug
docker-php-ext-enable xdebug

echo "Configuring xdebug..."
FILE=/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

IP=$(/sbin/ip route|awk '/default/ { print $3 }')

echo "xdebug.remote_enable=1" >> $FILE
echo "xdebug.remote_connect_back=1" >> $FILE
echo "xdebug.remote_port=9001" >> $FILE
echo "xdebug.remote_host=$IP" >> $FILE
echo "xdebug.remote_autostart=true" >> $FILE

echo "All done! To start using xdebug please restart this container"
