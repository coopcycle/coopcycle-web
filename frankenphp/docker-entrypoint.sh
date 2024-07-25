#!/bin/sh
set -xe

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then

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

  if [ "$APP_ENV" = 'prod' ]; then
      composer install --prefer-dist --no-progress --no-dev --optimize-autoloader --classmap-authoritative
  else
      composer install --prefer-dist --no-progress
  fi

  #FIXME: This is taken form the sample setup, but it's not executed currently
  # because we dont define DATABASE_URL in the .env file
	if grep -q ^DATABASE_URL= .env; then
		echo "Waiting for database to be ready..."
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo "The database is not up or not reachable:"
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo "The database is now ready and reachable"
		fi

		if [ "$( find ./migrations -iname '*.php' -print -quit )" ]; then
			php bin/console doctrine:migrations:migrate --no-interaction
		fi
	fi

	setfacl -R -m u:www-data:rwX -m u:"$(whoami)":rwX var
	setfacl -dR -m u:www-data:rwX -m u:"$(whoami)":rwX var
fi

php bin/console doctrine:database:create --if-not-exists --env=$APP_ENV

if [ "$APP_ENV" = 'dev' ] || [ "$APP_ENV" = 'test' ]; then
    php bin/console doctrine:database:create --if-not-exists --env=test
fi

exec docker-php-entrypoint "$@"
