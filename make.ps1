Write-Output "Generating RSA key to encrypt webtokens.."

Remove-Item -Path var -Include jwt -Recurse -Force
New-Item -ItemType directory -Path var -Name jwt -Force

openssl genrsa -out var/jwt/private.pem -passout pass:coursiers -aes256 4096
openssl rsa -pubout -passin pass:coursiers -in var\jwt\private.pem -out var\jwt\public.pem

Write-Output "Calculating cycling routes for Paris.."

Remove-Item -Path var -Include osrm -Recurse -Force
New-Item -ItemType directory -Path var -Name osrm -Force

Invoke-WebRequest -Uri "https://coopcycle.org/osm/paris-france.osm.pbf" -OutFile var\osrm\data.osm.pbf

docker-compose run osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
docker-compose run osrm osrm-partition /data/data.osrm
docker-compose run osrm osrm-customize /data/data.osrm

Write-Output "Creating database.."

docker-compose run php composer install --prefer-dist --no-progress --no-suggest
docker-compose run php php bin/console doctrine:database:create --if-not-exists --env=dev
docker-compose run php php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis' --env=dev
docker-compose run php php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis_topology' --env=dev

Write-Output "Populating schema.."

docker-compose run php php bin/console doctrine:schema:create --env=dev
docker-compose run php php bin/demo --env=dev
docker-compose run php php bin/console doctrine:migrations:version --add --all

docker-compose build