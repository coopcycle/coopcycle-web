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

* Install [Docker](https://www.docker.com/) and [Docker Compose](https://docs.docker.com/compose/install). 

    - On OSX : use [Docker for Mac](https://www.docker.com/docker-mac) which will provide you both `docker` and `docker-compose`. It doesn't rely on Virtualbox as Docker used to.
    
    - On Windows : use [Docker for Windows](https://www.docker.com/docker-windows) which will provide you both `docker` and `docker-compose`. It doesn't rely on Virtualbox as Docker used to.
    
    - On Linux : follow [the instructions for your distribution](https://docs.docker.com/engine/installation/). `docker-compose` binary is to be installed independently. You can use Coopcycle without root privileges, to do so run `sudo usermod -aG docker your-user` (will add you to the `docker` group).
    
* Docker Sync : Sync between host source files and container can be painfully slow on OSX or Windows. We advise you to install [docker-sync](http://docker-sync.io/) to speed up things. The command to start the platform on localhost would then be `docker-sync start` to start sync in the background, then ` docker-compose -f docker-compose.yml -f docker-compose-dev.yml up`.  

* Get [a Google Map API Key](https://developers.google.com/maps/documentation/javascript/get-api-key#key) and copy it. You will be asked for it when running `make install`.

* [Create a Stripe account](https://dashboard.stripe.com/register) and copy your tests credentials. You will be asked for them when running `make install`.

* Run the install scripts - when asked for `app/config/parameters.yml` parameters please enter your Google (`google_api_key`) and Stripe test (`stripe_secret_key` and `stripe_publishable_key`) credentials, for others you can keep defaults.
```sh
make install
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

```
docker-compose run php bin/console doctrine:schema:create --env=test
```

* Launch the PHPUnit tests

```
make phpunit
```

* Launch the Behat tests

```
make behat
```

* Launch the Mocha tests

```
make mocha
```

Running migrations
-------

When pulling change from the remote, the database models may have change. To take account for the changes, you will need to run a database migration.

```
make migrations-migrate
```
 

License
-------

The code is licensed under the [Peer Production License](https://wiki.p2pfoundation.net/Peer_Production_License), meaning you can use this software provided:

* You are a worker-owned business or worker-owned collective
* All financial gain, surplus, profits and benefits produced by the business or collective are distributed among the worker-owners
