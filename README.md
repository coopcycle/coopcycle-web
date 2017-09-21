CoopCycle
=========

[![Build Status](https://travis-ci.org/coopcycle/coopcycle-web.svg?branch=master)](https://travis-ci.org/coopcycle/coopcycle-web)

CoopCycle is a **self-hosted** platform to order meals in your neighborhood and get them delivered by bike couriers. The only difference with proprietary platforms as Deliveroo or UberEats is that this software is [reserved to co-ops](#license).

The main idea is to **decentralize** this kind of service and to allow couriers to **own the platform** they are working for.
In each city, couriers are encouraged to organize into co-ops, and to run their very own version of the software.

The software is under active development. If you would like to contribute we will be happy to hear from you! All instructions are [in the Contribute file](CONTRIBUTING.md).

Coopcycle-web is the main repo, containing the web API, the front-end for the website and the dispatch algorithm : [ Technical Overview ](https://github.com/coopcycle/coopcycle-web/wiki/Technical-Overview). You can see it in action & test it here : https://demo.coopcycle.org

You can find a comprehensive list of our repos here : [ Our repos comprehensive list ](https://github.com/coopcycle/coopcycle-web/wiki/Our-repos-comprehensive-list).

How to run a local instance
--------------

### Prerequisites

* Install [Docker](https://www.docker.com/). On OSX/Windows we advise you to install the latest versions available, which don't rely on Virtualbox.

* Get [a Google Map API Key](https://developers.google.com/maps/documentation/javascript/get-api-key#key) and paste it [in the conf file](https://github.com/coopcycle/coopcycle-web/blob/0c3b628bb268b59b00db501580a2c1dff2a99b05/app/config/parameters.yml.dist#L31)

* Create a Stripe account and enter your test credentials in [the conf file](https://github.com/coopcycle/coopcycle-web/blob/0c3b628bb268b59b00db501580a2c1dff2a99b05/app/config/parameters.yml.dist#L33)

* Generate the SSH keys for JSON Web Token:
```
$ mkdir -p var/jwt
$ openssl genrsa -out var/jwt/private.pem -passout pass:courier -aes256 4096
$ openssl rsa -pubout -in var/jwt/private.pem -out var/jwt/public.pem
```

* Download metro extract for your area from [MapZen](https://mapzen.com/data/metro-extracts/) (example for Paris)
```
mkdir -p var/osrm
wget https://s3.amazonaws.com/metro-extracts.mapzen.com/paris_france.osm.pbf -O var/osrm/data.osm.pbf
```

* Create the database schema
```
docker-compose run php bin/console doctrine:schema:create
```

* Populate the database with users and restaurants.
```
docker-compose run php bin/demo
```

* Pre-process the OpenStreeMap data for OSRM
```
docker-compose run osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
docker-compose run osrm osrm-contract /data/data.osrm
```

### Run the application

* Start the Docker containers
```
docker-compose up
```

* Open the platform in your browser
```
open http://localhost
```

Testing
-------

* Create the test database

With Docker
```
docker-compose run php bin/console doctrine:schema:create --env=test
```

* Launch the Behat tests

With Docker
```
docker-compose run php php vendor/bin/behat
```

* Launch the Mocha tests

With Docker
```
docker-compose run -e SYMFONY_ENV=test -e NODE_ENV=test nodejs /run-tests.sh
```

License
-------

The code is licensed under the [Peer Production License](https://wiki.p2pfoundation.net/Peer_Production_License), meaning you can use this software provided:

* You are a worker-owned business or worker-owned collective
* All financial gain, surplus, profits and benefits produced by the business or collective are distributed among the worker-owners
