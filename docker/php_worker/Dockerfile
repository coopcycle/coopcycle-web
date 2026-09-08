ARG PHP_VERSION=8.5
FROM coopcycle/php:${PHP_VERSION}

ENTRYPOINT ["sh", "-c", "/var/www/html/bin/console messenger:consume async --env=${APP_ENV} --limit=100 --time-limit=900 -n"]
