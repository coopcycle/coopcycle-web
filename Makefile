.PHONY: setup install install-db osrm phpunit phpunit-only behat behat-only cypress cypress-only cypress-only-until-fail cypress-open cypress-install jest migrations migrations-diff migrations-migrate email-preview enable-xdebug start start-fresh fresh fresh-db perms lint test testdata-dispatch testdata-foodtech testdata-high-volume-instance demodata testserver console log log-requests ftp

setup: install migrations perms demodata

install: osrm install-db

install-db:
	@printf "\e[0;32mPopulating schema..\e[0m\n"
	@docker compose exec php php bin/console doctrine:schema:create --env=dev
	@docker compose exec php php bin/console doctrine:schema:create --env=test
	@docker compose exec php php bin/console typesense:create --env=test
	@docker compose exec php php bin/console coopcycle:setup --env=test
	@docker compose exec php php bin/console doctrine:migrations:sync-metadata-storage
	@docker compose exec php php bin/console doctrine:migrations:version --no-interaction --quiet --add --all

osrm:
	@printf "\e[0;32mCalculating cycling routes for Paris..\e[0m\n"
	@docker compose run --rm osrm wget --no-check-certificate https://coopcycle-assets.sfo2.digitaloceanspaces.com/osm/paris-france.osm.pbf -O /data/data.osm.pbf
	@docker compose run --rm osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
	@docker compose run --rm osrm osrm-partition /data/data.osrm
	@docker compose run --rm osrm osrm-customize /data/data.osrm
	@docker compose restart osrm

phpunit:
	@docker compose exec php php bin/console doctrine:schema:update --env=test --force --no-interaction --quiet
	@docker compose exec php php vendor/bin/phpunit ${ARGS}
# Add as annotation at the top of any testcase:
# /**
# * @group only
# */
# public function testSomething()..
phpunit-only:
	@clear && make phpunit ARGS="--group only"

behat:
	@docker compose exec -e APP_ENV=test php php vendor/bin/behat ${ARGS}

# Add as annotation at the top of any scenario/feature:
# @only
# Scenario: Some description..
behat-only:
	@clear && make behat ARGS="-v --tags=@only"

cypress:
	@npm run e2e
# You can set the `TESTFILE` env var when running the target:
#    make cypress-only TESTFILE=invoicing/export_data_for_invoicing.cy.js
# Or just change here the `TESTFILE` env var to run the desired test
cypress-only: TESTFILE?=local-commerce/@admin/update_price.cy.js
cypress-only:
	@clear && cypress run --browser chrome --headless --no-runner-ui --spec cypress/e2e/${TESTFILE}
cypress-only-until-fail: TESTFILE?=local-commerce/@admin/update_price.cy.js
cypress-only-until-fail:
	@while clear && cypress run --browser chrome --headless --no-runner-ui --spec cypress/e2e/${TESTFILE}; do :; done
cypress-open:
	@npm run cy:open
cypress-install:
	@npm install

jest:
	@docker compose exec -e APP_ENV=test -e NODE_ENV=test webpack npm run jest ${ARGS}

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
	@clear && docker compose up --remove-orphans && docker compose stop

# Once everything is restarted, you need to run in another terminal: `make setup`
# And after setup is done, you need to stop/restart the containers again with: `make start`
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
	@docker compose exec php sh -c "chown -R www-data:www-data web/ var/ && chmod 777 web/ var/ && chmod 666 web/build/entrypoints.json"

lint:
	@docker compose exec php php vendor/bin/phpstan analyse -v

test: phpunit jest behat cypress

testdata-dispatch:
	@docker compose exec php bin/console coopcycle:fixtures:load -s fixtures/ORM/setup_default.yml -f fixtures/ORM/dispatch.yml --env test
testdata-foodtech:
	@docker compose exec php bin/console coopcycle:fixtures:load -f fixtures/ORM/foodtech.yml --env test
testdata-high-volume-instance:
	@docker compose exec php bin/console coopcycle:fixtures:load -f fixtures/ORM/high_volume_instance.yml --env test

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
