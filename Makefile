install:
	@printf "\e[0;32mGenerating RSA key to encrypt webtokens..\e[0m\n"
	@[ -d var/jwt ] || @mkdir -p var/jwt
	@openssl genrsa -out var/jwt/private.pem -passout pass:coursiers -aes256 4096;
	@openssl rsa -pubout -passin pass:coursiers -in var/jwt/private.pem -out var/jwt/public.pem
	@printf "\e[0;32mCalculating cycling routes for Paris..\e[0m\n"
	$(MAKE) osrm
	@printf "\e[0;32mCreating database..\e[0m\n"
	@docker-compose run php composer install --prefer-dist --no-progress --no-suggest
	@docker-compose run php php bin/console doctrine:database:create --if-not-exists --env=dev
	@docker-compose run php php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis' --env=dev
	@docker-compose run php php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis_topology' --env=dev
	@docker-compose run php php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS pg_trgm' --env=dev
	@printf "\e[0;32mPopulating schema..\e[0m\n"
	@docker-compose run php php bin/console doctrine:schema:create --env=dev
	@docker-compose run php bin/demo --env=dev
	@docker-compose run php php bin/console doctrine:migrations:version --no-interaction --quiet --add --all

osrm:
	@[ -d var/osrm ] || mkdir -p var/osrm
	@wget https://coopcycle.org/osm/paris-france.osm.pbf -O var/osrm/data.osm.pbf
	@docker-compose run osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
	@docker-compose run osrm osrm-partition /data/data.osrm
	@docker-compose run osrm osrm-customize /data/data.osrm

phpunit:
	@docker-compose run php bin/console doctrine:schema:update --env=test --force --no-interaction --quiet
	@docker-compose run php vendor/bin/phpunit

behat:
	@docker-compose run php php vendor/bin/behat

mocha:
	@docker-compose run -e SYMFONY_ENV=test -e NODE_ENV=test nodejs /run-tests.sh

migrations-diff:
	@docker-compose run php bin/console doctrine:migrations:diff

migrations-migrate:
	@docker-compose run php bin/console doctrine:migrations:migrate
	@docker-compose run php bin/console doctrine:schema:update --env=test --force --no-interaction

email-preview:
	@docker-compose run php bin/console coopcycle:email:preview > /tmp/coopcycle_email_layout.html && open /tmp/coopcycle_email_layout.html
