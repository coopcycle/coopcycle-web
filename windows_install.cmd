ECHO Generating RSA key to encrypt webtokens..
if not exist var\jwt mkdir var\jwt
"C:\Program Files (x86)\GnuWin32\bin\openssl" genrsa -out var/jwt/private.pem -passout pass:coursiers -aes256 4096
"C:\Program Files (x86)\GnuWin32\bin\openssl" rsa -pubout -passin pass:coursiers -in var\jwt\private.pem -out var\jwt\public.pem
ECHO Calculating cycling routes for Paris..
if not exist var\osrm mkdir var\osrm
"C:\Program Files (x86)\GnuWin32\bin\wget" --no-check-certificate https://s3.amazonaws.com/mapzen.odes/ex_i653FMk2VwCUGetCYpH2hR4hpNLKV.osm.pbf -O var\osrm\data.osm.pbf

docker-compose run osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
docker-compose run osrm osrm-contract /data/data.osrm
ECHO Creating database..
docker-compose run php composer install --prefer-dist --no-progress --no-suggest
docker-compose run php bin/console doctrine:database:create --if-not-exists --env=dev
docker-compose run php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis' --env=dev
docker-compose run php bin/console doctrine:query:sql 'CREATE EXTENSION IF NOT EXISTS postgis_topology' --env=dev
ECHO Populating schema..
docker-compose run php bin/demo
docker-compose run php bin/console doctrine:migrations:version --add --all
docker-compose build
