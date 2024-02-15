install:
	@printf "\e[0;32mCalculating cycling routes for Paris..\e[0m\n"
	"$(MAKE)" osrm
	@printf "\e[0;32mPopulating schema..\e[0m\n"
	@docker-compose exec php php bin/console doctrine:schema:create --env=dev
	@docker-compose exec php bin/demo --env=dev
	@docker-compose exec php php bin/console doctrine:migrations:sync-metadata-storage
	@docker-compose exec php php bin/console doctrine:migrations:version --no-interaction --quiet --add --all

osrm:
	@docker-compose run --rm osrm wget --no-check-certificate https://coopcycle-assets.sfo2.digitaloceanspaces.com/osm/paris-france.osm.pbf -O /data/data.osm.pbf
	@docker-compose run --rm osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
	@docker-compose run --rm osrm osrm-partition /data/data.osrm
	@docker-compose run --rm osrm osrm-customize /data/data.osrm

phpunit:
	@docker-compose exec php php bin/console doctrine:schema:update --env=test --force --no-interaction --quiet
	@docker-compose exec php php vendor/bin/phpunit

behat:
	@docker-compose exec php php vendor/bin/behat

jest:
	@docker-compose exec -e SYMFONY_ENV=test -e NODE_ENV=test webpack npm run jest

migrations-diff:
	@docker-compose exec php php bin/console doctrine:migrations:diff --no-interaction

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

log:
	@docker-compose exec php tail -f var/logs/dev-$(shell date --rfc-3339=date).log | grep -v "restart_requested_timestamp" | sed --unbuffered \
    -e 's/\(.*INFO\)/\o033[90m\1\o033[39m/' \
    -e 's/\(.*NOTICE\)/\o033[37m\1\o033[39m/' \
    -e 's/\(.*WARNING\)/\o033[33m\1\o033[39m/' \
    -e 's/\(.*ERROR\)/\o033[93m\1\o033[39m/' \
    -e 's/\(.*CRITICAL\)/\o033[31m\1\o033[39m/' \
    -e 's/\(.*ALERT\)/\o033[91m\1\o033[39m/' \
    -e 's/\(.*EMERGENCY\)/\o033[95m\1\o033[39m/' \
    -e 's/\(.*DEBUG\)/\o033[90m\1\o033[39m/' \
    -e 's/\(stacktrace\)/\o033[37m\1\o033[39m/'
