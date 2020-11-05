CoopCycle
=========

[![Build Status](https://travis-ci.org/coopcycle/coopcycle-web.svg?branch=master)](https://travis-ci.org/coopcycle/coopcycle-web)

CoopCycle is a **self-hosted** platform to order meals in your neighborhood and get them delivered by bike couriers. The only difference with proprietary platforms as Deliveroo or UberEats is that this software is [reserved to co-ops](#license).

The main idea is to **decentralize** this kind of service and to allow couriers to **own the platform** they are working for.
In each city, couriers are encouraged to organize into co-ops, and to run their very own version of the software.

The software is under active development. If you would like to contribute we will be happy to hear from you! All instructions are [in the Contribute file](CONTRIBUTING.md).

Coopcycle-web is the main repo, containing the web API, the front-end for the website and the dispatch algorithm : [Technical Overview](https://github.com/coopcycle/coopcycle-web/wiki/Technical-Overview). You can see it in action & test it here : https://demo.coopcycle.org

You can find a comprehensive list of our repos here : [Our repos comprehensive list](https://github.com/coopcycle/coopcycle-web/wiki/Our-repos-comprehensive-list).

How to run a local instance
---------------------------

### Prerequisites

Install [Docker](https://www.docker.com/) and [Docker Compose](https://docs.docker.com/compose/install).

#### OSX

Use [Docker for Mac](https://www.docker.com/docker-mac) which will provide you both `docker` and `docker-compose`.

#### Windows

Use [Docker for Windows](https://www.docker.com/docker-windows) which will provide you both `docker` and `docker-compose`.
Depending on your platform, Docker could be installed as Native or you have to install Docker toolbox which use VirtualBox instead of Hyper-V causing a lot a differences in implementations.
If you have the luck to have a CPU that supports native Docker you can [share your hard disk as a virtual volume for your appliances](https://blogs.msdn.microsoft.com/stevelasker/2016/06/14/configuring-docker-for-windows-volumes/).

#### Linux

Follow [the instructions for your distribution](https://docs.docker.com/install/). `docker-compose` binary is to be installed independently.
Make sure:
- to install `docker-compose` [following instructions](https://docs.docker.com/compose/install/) to get the **latest version**.
- to follow the [post-installation steps](https://docs.docker.com/install/linux/linux-postinstall/).

#### Setup Google Maps API (optional)

CoopCycle uses the Google Maps API for Geocoding, as well as the Places API.
You will need to create a project in the Google Cloud Platform Console, and
enable the Google Maps API. GCP will give you an API token that you will need
later.  By default, the Geocoding and Places API will not be enabled, so you
need to enable them as well (`Maps API dashboard > APIs > Geocoding API >
Enable`, and `Maps API dashboard > APIs > Places API for Web > Enable`).

### Run the application

#### Configure the environment

Copy the `.env.dist` file and rename it to `.env`. Then, a few mandatory environment variables must be declared before continuing configuring a development environment.

- `GOOGLE_API_KEY`
  - A Google API key that supports `Geocoding API` and `Places API`.
- `COOPCYCLE_COUNTRY`
  - Options: `be`, `ca-bc`, `de`, `es`, `fr`, `gb`, `pl`, `ar`.
- `COOPCYCLE_LOCALE`
  - Options: `cs`, `en`, `es`, `fr`, `pt`, `ca`.
- `COOPCYCLE_REGION`
  - Options: `be`, `ca-bc`, `de`, `es`, `fr`, `gb`, `pl`, `ar`.
- `COOPCYCLE_REGION_LAT_LNG`
  - This latitude/longitude value will be used to generate restaurant seeds around that area.
- `COOPCYCLE_LEGACY_TAXES`
  - When activated (`1`), default (french) values will be used.
  - If deactivated, taxations according to `COOPCYCLE_COUNTRY` will be used.

#### Start the Docker containers

```
docker-compose up
```

At this step, the platform should be up & running, but the database is still empty.
To create the schema & initialize the platform with demo data, run:
```sh
make install
```

#### Open the platform in your browser
```
open http://localhost
```

Testing
-------

#### Create the test database

```
docker-compose run php bin/console doctrine:schema:create --env=test
```

#### Launch the PHPUnit tests

```
make phpunit
```

#### Launch the Behat tests

```
make behat
```

#### Launch the Mocha tests

```
make mocha
```
Debugging
------------------
#### 1. Install and enable xdebug in the php container

```
make enable-xdebug
```
> **Note:** If you've been working with this stack before you'll need to rebuild the php image for this command to work:
> ```
> docker-compose build php
> docker-compose restart php nginx
> ```
#### 2. Enable php debug in VSCode
1. Install a PHP Debug extension, this is tested with [felixfbecker.php-debug](https://marketplace.visualstudio.com/items?itemName=felixfbecker.php-debug) extension.
2. Add the following configuration in your `.vscode/launch.json` of your workspace:

```json
{
	...
	"configurations": [
    ...
    {
      "name": "Listen for XDebug",
      "type": "php",
      "request": "launch",
      "port": 9001,
      "pathMappings": {
          "/var/www/html": "${workspaceFolder}"
      },
      "xdebugSettings": {
          "max_data": 65535,
          "show_hidden": 1,
          "max_children": 100,
          "max_depth": 5
      }
    }
    ...
  ]
}
```
3. If you're having issues connecting the debugger yo can restart nginx and php containers to reload the xdebug extension.
```
docker-compose restart php nginx
```

Running migrations
------------------

When pulling change from the remote, the database models may have changed. To apply the changes, you will need to run a database migration.

```
make migrations-migrate
```

License
-------

The code is licensed under the [Coopyleft License](https://wiki.coopcycle.org/en:license), meaning you can use this software provided:

- You are matching with the social and common company’s criteria as define by their national law, or by the European Commission in its [October 25th, 2011 communication](http://www.europarl.europa.eu/meetdocs/2009_2014/documents/com/com_com(2011)0681_/com_com(2011)0681_en.pdf), or by default by the Article 1 of the French law [n°2014-856 of July 31st, 2014](https://www.legifrance.gouv.fr/affichTexte.do?cidTexte=JORFTEXT000029313296&categorieLien=id) “relative à l’économie sociale et solidaire”
- You are using a cooperative model in which workers are employees
