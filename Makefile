


install:
	@printf "\e[0;32mGenerating RSA key to encrypt webtokens..\e[0m\n"
	@mkdir -p var/jwt
	@openssl genrsa -out var/jwt/private.pem -passout pass:coursiers -aes256 4096;
	@openssl rsa -pubout -passin pass:coursiers -in var/jwt/private.pem -out var/jwt/public.pem
	@printf "\e[0;32mCalculating cycling routes for Paris..\e[0m\n"
	@mkdir -p var/osrm
	@wget https://s3.amazonaws.com/metro-extracts.mapzen.com/paris_france.osm.pbf -O var/osrm/data.osm.pbf
	@docker-compose run osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
	@docker-compose run osrm osrm-contract /data/data.osrm
	@printf "\e[0;32mCreating database schema..\e[0m\n"
	@docker-compose run php bin/console doctrine:schema:create
	@printf "\e[0;32mPopulating schema..\e[0m\n"
	@docker-compose run php bin/demo
