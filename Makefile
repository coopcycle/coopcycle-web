install:
	@printf "\e[0;32mCalculating cycling routes for Paris..\e[0m\n"
	"$(MAKE)" osrm
	@printf "\e[0;32mPopulating schema..\e[0m\n"
	@docker-compose exec php php bin/console doctrine:schema:create --env=dev
	@docker-compose exec php bin/demo --env=dev
	@docker-compose exec php php bin/console doctrine:migrations:version --no-interaction --quiet --add --all

osrm:
	@docker-compose run --rm osrm wget --no-check-certificate https://coopcycle.org/osm/paris-france.osm.pbf -O /data/data.osm.pbf
	@docker-compose run --rm osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
	@docker-compose run --rm osrm osrm-partition /data/data.osrm
	@docker-compose run --rm osrm osrm-customize /data/data.osrm

phpunit:
	@docker-compose exec php php bin/console doctrine:schema:update --env=test --force --no-interaction --quiet
	@docker-compose exec php php vendor/bin/phpunit

behat:
	@docker-compose exec php php vendor/bin/behat

mocha:
	@docker-compose exec -e SYMFONY_ENV=test -e NODE_ENV=test nodejs /run-tests.sh

migrations-diff:
	@docker-compose exec php php bin/console doctrine:migrations:diff

migrations-migrate:
	@docker-compose exec php php bin/console doctrine:migrations:migrate
	@docker-compose exec php php bin/console doctrine:schema:update --env=test --force --no-interaction

email-preview:
	@docker-compose exec php php bin/console coopcycle:email:preview > /tmp/coopcycle_email_layout.html && open /tmp/coopcycle_email_layout.html

enable-xdebug:
	@docker-compose exec php /usr/local/bin/enable-xdebug
	@docker-compose restart php nginx

fresh:
	@docker-compose down
