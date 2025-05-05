FROM coopcycle/php:8.3

ENTRYPOINT ["sh", "-c", "/var/www/html/bin/console messenger:consume async --env=${APP_ENV} --limit=100 --time-limit=900 -n"]
