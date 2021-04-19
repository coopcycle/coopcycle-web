CoopCycle
=========

[![Build Status](https://travis-ci.org/coopcycle/coopcycle-web.svg?branch=master)](https://travis-ci.org/coopcycle/coopcycle-web)
[![Financial Contributors on Open Collective](https://opencollective.com/coopcycle/all/badge.svg?label=financial+contributors)](https://opencollective.com/coopcycle) 

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

#### Setup OpenStreetMap geocoders (optional)

CoopCycle uses [OpenStreetMap](https://www.openstreetmap.org/) to geocode addresses and provide autocomplete features.

##### Address autocomplete

To configure address autocomplete, choose a provider below, grab the credentials, and configure environment variables accordingly.

```
ALGOLIA_PLACES_APP_ID
ALGOLIA_PLACES_API_KEY
LOCATIONIQ_ACCESS_TOKEN
GEOCODE_EARTH_API_KEY
```

- For [Algolia Places](https://community.algolia.com/places/), set `COOPCYCLE_AUTOCOMPLETE_ADAPTER=algolia`
- For [Geocode Earth](https://geocode.earth/), set `COOPCYCLE_AUTOCOMPLETE_ADAPTER=geocode-earth`
- For [LocationIQ](https://locationiq.com/), set `COOPCYCLE_AUTOCOMPLETE_ADAPTER=locationiq`

##### Geocoding

To configure geocoding, create an account on [OpenCage](https://opencagedata.com/), and configure the `OPENCAGE_API_KEY` environement variable.

### Run the application

#### Pull the Docker containers (optional)

We have prebuilt some images and uploaded them to [Docker Hub](https://hub.docker.com/u/coopcycle).
To avoid building those images locally, you can pull them first.

```
docker-compose pull
```

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
	"configurations": [
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

## Contributors

### Code Contributors

This project exists thanks to all the people who contribute. [[Contribute](CONTRIBUTING.md)].
<a href="https://github.com/coopcycle/coopcycle-web/graphs/contributors"><img src="https://opencollective.com/coopcycle/contributors.svg?width=890&button=false" /></a>

### Financial Contributors

Become a financial contributor and help us sustain our community. [[Contribute](https://opencollective.com/coopcycle/contribute)]

#### Individuals

<a href="https://opencollective.com/coopcycle"><img src="https://opencollective.com/coopcycle/individuals.svg?width=890"></a>

#### Organizations

Support this project with your organization. Your logo will show up here with a link to your website. [[Contribute](https://opencollective.com/coopcycle/contribute)]

<a href="https://opencollective.com/coopcycle/organization/0/website"><img src="https://opencollective.com/coopcycle/organization/0/avatar.svg"></a>
<a href="https://opencollective.com/coopcycle/organization/1/website"><img src="https://opencollective.com/coopcycle/organization/1/avatar.svg"></a>
<a href="https://opencollective.com/coopcycle/organization/2/website"><img src="https://opencollective.com/coopcycle/organization/2/avatar.svg"></a>
<a href="https://opencollective.com/coopcycle/organization/3/website"><img src="https://opencollective.com/coopcycle/organization/3/avatar.svg"></a>
<a href="https://opencollective.com/coopcycle/organization/4/website"><img src="https://opencollective.com/coopcycle/organization/4/avatar.svg"></a>
<a href="https://opencollective.com/coopcycle/organization/5/website"><img src="https://opencollective.com/coopcycle/organization/5/avatar.svg"></a>
<a href="https://opencollective.com/coopcycle/organization/6/website"><img src="https://opencollective.com/coopcycle/organization/6/avatar.svg"></a>
<a href="https://opencollective.com/coopcycle/organization/7/website"><img src="https://opencollective.com/coopcycle/organization/7/avatar.svg"></a>
<a href="https://opencollective.com/coopcycle/organization/8/website"><img src="https://opencollective.com/coopcycle/organization/8/avatar.svg"></a>
<a href="https://opencollective.com/coopcycle/organization/9/website"><img src="https://opencollective.com/coopcycle/organization/9/avatar.svg"></a>
