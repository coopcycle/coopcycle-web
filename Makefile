.PHONY: setup install osrm phpunit phpunit-only behat behat-only cypress cypress-only cypress-open cypress-install jest migrations migrations-diff migrations-migrate email-preview enable-xdebug start start-fresh fresh fresh-db perms lint test testdata1 testdata2 testdata3 testdata4 demodata testserver console log log-requests ftp

setup: install migrations perms

install:
	@printf "\e[0;32mCalculating cycling routes for Paris..\e[0m\n"
	@$(MAKE) osrm
	@printf "\e[0;32mPopulating schema..\e[0m\n"
	@docker compose exec php php bin/console doctrine:schema:create --env=dev
	@docker compose exec php php bin/console doctrine:schema:create --env=test
	@docker compose exec php php bin/console typesense:create --env=test
	@docker compose exec php php bin/console coopcycle:setup --env=test
	@$(MAKE) demodata testdata2
	@docker compose exec php php bin/console doctrine:migrations:sync-metadata-storage
	@docker compose exec php php bin/console doctrine:migrations:version --no-interaction --quiet --add --all

osrm:
	@docker compose run --rm osrm wget --no-check-certificate https://coopcycle-assets.sfo2.digitaloceanspaces.com/osm/paris-france.osm.pbf -O /data/data.osm.pbf
	@docker compose run --rm osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
	@docker compose run --rm osrm osrm-partition /data/data.osrm
	@docker compose run --rm osrm osrm-customize /data/data.osrm

phpunit:
	@docker compose exec php php bin/console doctrine:schema:update --env=test --force --no-interaction --quiet
	@docker compose exec php php vendor/bin/phpunit
# Add as annotation in any testcase:
# /**
# * @group only
# */
# public function testSomething()..
phpunit-only:
	@clear && docker compose exec php php vendor/bin/phpunit --group only

behat:
	@docker compose exec php php vendor/bin/behat
# For now, just change here the `features/file.feature:xx` to run a specific test
behat-only:
	@clear && docker compose exec php php vendor/bin/behat features/stores.feature:96

cypress:
	@npm run e2e
# For now, just change here the `cypress/e2e/xxx/file.cy.js` to run a specific test
cypress-only:
	@clear && cypress run --browser chrome --headless --no-runner-ui --spec cypress/e2e/dispatch/admin_invite_dispatcher.cy.js
cypress-open:
	@cypress open
# NOTE: This command is not needed if you run `npm run e2e` or `npm run e2e:headless`
# in the terminal, as it will install cypress automatically
cypress-install:
	@docker compose exec -e APP_ENV=test -e SYMFONY_ENV=test -e NODE_ENV=test webpack npm install -g cypress@13.15.0 @cypress/webpack-preprocessor@6.0.2 @cypress/react18@2.0.1
	@npm install -g cypress@13.15.0 @cypress/webpack-preprocessor@6.0.2 @cypress/react18@2.0.1

jest:
	@docker compose exec -e APP_ENV=test -e SYMFONY_ENV=test -e NODE_ENV=test webpack npm run jest

# Just an alias
migrations: migrations-migrate

migrations-migrate:
	@docker compose exec php php bin/console doctrine:migrations:migrate
	@docker compose exec php php bin/console doctrine:schema:update --env=test --force --no-interaction --complete

migrations-diff:
	@docker compose exec php php bin/console doctrine:migrations:diff --no-interaction

email-preview:
	@docker compose exec php php bin/console coopcycle:email:preview > /tmp/coopcycle_email_layout.html && open /tmp/coopcycle_email_layout.html

enable-xdebug:
	@docker compose exec php /usr/local/bin/enable-xdebug
	@docker compose restart php nginx

start:
	@clear && docker compose up --remove-orphans

# Once everything is restarted, you need to run in another terminal: `make setup`
start-fresh: fresh-db fresh

fresh:
	@clear && docker compose down
	@docker compose up --build --remove-orphans

fresh-db:
	@docker compose up -d postgres
	@docker compose exec -T postgres dropdb -U postgres coopcycle
	@docker compose exec -T postgres dropdb -U postgres coopcycle_test

# This one solves weird file permissions issues when
# browsing the `test` env at http://localhost:9080/
perms:
	@docker compose exec php sh -c "chown -R www-data:www-data web/ var/ && chmod 777 web/ var/"

lint:
	@docker compose exec php php vendor/bin/phpstan analyse -v

test: phpunit jest behat cypress

testdata1:
	@docker compose exec php bin/console coopcycle:fixtures:load -f cypress/fixtures/high_volume_instance.yml --env test
testdata2:
	@docker compose exec php bin/console coopcycle:fixtures:load -f cypress/fixtures/dispatch.yml --env test
testdata3:
	@docker compose exec php bin/console coopcycle:fixtures:load -f cypress/fixtures/package_delivery_orders.yml --env test
testdata4:
	@docker compose exec php bin/console coopcycle:fixtures:load -f cypress/fixtures/restaurant.yml --env test

demodata:
	@docker compose exec php bin/demo --env=dev

# This one seems to be not needed..!
testserver:
	@docker compose run --service-ports -e APP_ENV=test php

console:
	@clear && docker compose exec php sh

log:
	@docker compose exec php tail -f var/logs/dev-$(shell date --rfc-3339=date).log | grep -v "restart_requested_timestamp" | sed --unbuffered \
    -e 's/\(.*INFO\)/\o033[90m\1\o033[39m/' \
    -e 's/\(.*NOTICE\)/\o033[37m\1\o033[39m/' \
    -e 's/\(.*WARNING\)/\o033[33m\1\o033[39m/' \
    -e 's/\(.*ERROR\)/\o033[93m\1\o033[39m/' \
    -e 's/\(.*CRITICAL\)/\o033[31m\1\o033[39m/' \
    -e 's/\(.*ALERT\)/\o033[91m\1\o033[39m/' \
    -e 's/\(.*EMERGENCY\)/\o033[95m\1\o033[39m/' \
    -e 's/\(.*DEBUG\)/\o033[90m\1\o033[39m/' \
    -e 's/\(stacktrace\)/\o033[37m\1\o033[39m/'
# Request loglines appears as "request.INFO"
log-requests:
	@clear && docker compose exec php tail -f var/logs/dev-$(shell date --rfc-3339=date).log | grep "request.INFO" | sed --unbuffered \
    -e 's/\(.*INFO\)/\n\o033[90m\1\o033[39m/' \

ftp:
	$(eval TMP := $(shell mktemp -d))
	@mkdir -p $(TMP)/from_coopx
	@mkdir -p $(TMP)/to_coopx
	@echo "Temp folder: $(TMP)"
	@docker run --name "coopcycle-transporter-ftp" -d --rm \
		--net "container:coopcycle-web-php-1" \
		-e FTP_USERNAME=user -e FTP_PASSWORD=123 \
		-v $(TMP):/home/user \
		monteops/proftpd
