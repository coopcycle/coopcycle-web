CoopCycle
=========

![Build Status](https://github.com/coopcycle/coopcycle-web/actions/workflows/test_behat.yml/badge.svg) ![Build Status](https://github.com/coopcycle/coopcycle-web/actions/workflows/test_e2e.yml/badge.svg)

CoopCycle is a **self-hosted** platform to order meals in your neighborhood and get them delivered by bike couriers. The only difference with proprietary platforms as Deliveroo or UberEats is that this software is [reserved to co-ops](#license).

The main idea is to **decentralize** this kind of service and to allow couriers to **own the platform** they are working for.
In each city, couriers are encouraged to organize into co-ops, and to run their very own version of the software.

The software is under active development. If you would like to contribute we will be happy to hear from you! All instructions are [in the Contribute file](CONTRIBUTING.md).

Coopcycle-web is the main repo, containing the web API, the front-end for the website and the dispatch algorithm : [Technical Overview](https://github.com/coopcycle/coopcycle-web/wiki/Technical-Overview). You can see it in action & test it here : https://demo.coopcycle.org

You can find a comprehensive list of our repos here : [Our repos comprehensive list](https://github.com/coopcycle/coopcycle-web/wiki/Our-repos-comprehensive-list).

How to run a local instance
---------------------------

Please find below the steps to install the platform locally. You can find additional tips & configurations [in the wiki](https://github.com/coopcycle/coopcycle-web/wiki/Developing).

### Prerequisites

Install [Docker](https://www.docker.com/) and [Docker Compose](https://docs.docker.com/compose/install).

#### OSX

Use [Docker for Mac](https://www.docker.com/docker-mac) which will provide you both `docker` and `docker-compose`.

#### Windows

Use [Docker for Windows](https://www.docker.com/docker-windows) which will provide you both `docker` and `docker-compose`.
Depending on your platform, Docker could be installed as Native or you have to install Docker toolbox which use VirtualBox instead of Hyper-V causing a lot a differences in implementations.
If you have the luck to have a CPU that supports native Docker you can [share your hard disk as a virtual volume for your appliances](https://blogs.msdn.microsoft.com/stevelasker/2016/06/14/configuring-docker-for-windows-volumes/).

Docker doesn't work under Windows, you need to install linux in hypervisualization. Follow the recommendations here to activate the necessary features under windows 11 and make sure you have an administrator account
  https://docs.docker.com/desktop/troubleshoot/topics/

Download docker
https://www.docker.com/products/docker-desktop/
Check in the BIOS that :
-hypervisualization (HYPER-V)
-Data Execution Prevention (DEP).
You can also use the following procedure for DEP:
Windows + r
Search for sysdm.cpl
Advanced system settings
In Performance, select settings data execution prevention  "enable for all except those I select...". click on apply

install, from your PowerShell WSL 2 terminal
 https://learn.microsoft.com/en-us/windows/wsl/install

configure your WSL 2 environment by creating a Linux administrator account. The password is not displayed (this is normal), so remember it.
https://learn.microsoft.com/fr-fr/windows/wsl/setup/environment#file-storage

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
LOCATIONIQ_ACCESS_TOKEN
GEOCODE_EARTH_API_KEY
```

- For [Geocode Earth](https://geocode.earth/), set `COOPCYCLE_AUTOCOMPLETE_ADAPTER=geocode-earth`
- For [LocationIQ](https://locationiq.com/), set `COOPCYCLE_AUTOCOMPLETE_ADAPTER=locationiq`

##### Geocoding

To configure geocoding, create an account on [OpenCage](https://opencagedata.com/), and configure the `OPENCAGE_API_KEY` environement variable.

### Run the application

#### Pull the Docker containers (optional)

We have prebuilt some images and uploaded them to [Docker Hub](https://hub.docker.com/u/coopcycle).
To avoid building those images locally, you can pull them first.

```sh
docker compose pull
```

Populate your local `.env` and `.env.test.local` files:

```sh
cp .env.dist .env
touch .env.test.local
```

You only need to override the desired env vars at `.env.test.local`, like setting your `GEOCODE_EARTH_API_KEY=...`

#### Start the Docker containers

Minimum configuration:

```sh
docker compose up
```

With Storybook:

```sh
docker compose --profile devFrontend up
```

With Odoo:

```sh
docker compose --profile devOdoo up
```

At this step, the platform should be up & running, but the database is still empty.
To create the schema & initialize the platform with demo data, run:
```sh
make install
```

#### Open the platform in your browser
```sh
open http://localhost
```

Testing
-------

#### Create the test database

```sh
docker compose run php bin/console doctrine:schema:create --env=test
```

### Launch the PHPUnit tests

#### All Tests:

```sh
make phpunit
```

#### One package/test:

For example, to run only the tests in the `AppBundle\Sylius\OrderProcessing` folder:

```sh
sh ./bin/phpunit /var/www/html/tests/AppBundle/Sylius/OrderProcessing
```

See more command line options [here](https://docs.phpunit.de/en/9.6/textui.html#command-line-options).

### Launch the Behat tests

#### All Tests:

```sh
make behat
```

#### One package/test:

For example, to run only the tests in the `features/authentication.feature` file:

```sh
sh ./bin/behat features/authentication.feature
```

To run only the tests with the `@only` tag:

```sh
make behat-only
```

To only show errors in logs:

```sh
sh ./bin/behat features/authentication.feature --no-snippets --format progress
```

See more command line options [here](https://behat.org/en/latest/user_guide/command_line_tool.html).

### Launch the Jest tests

```sh
make jest
```

or to run only one test file:

```sh
sh ./bin/jest path/to/test/file.test.js
```

### Launch the Cypress tests

Cypress is a JS program for end-to-end testing and integration testing of components. You will launch a server in the test environment and run cypress on your own machine.

Installation:

```sh
make cypress-install
```

# install typesense for test env (automatically done with `make install` or `make setup`)
```sh
docker compose exec -T php bin/console typesense:create --env=test
```

In the `.env` file you need to set `GEOCODE_EARTH_API_KEY` to a valid API key. You need also Stripe configured on the platform or in the `.env` file (`STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_CONNECT_CLIENT_ID`).

and then this command will lead you to Cypress GUI
```sh
make cypress-open
```

The Cypress tests will run automatically in Github CI on the `master` branch. You can get screenshots of the failed tests from the `Upload images for failed test` step (there is a link there to download the failed steps).


### Run linters (phpStan)

```sh
docker compose exec php php vendor/bin/phpstan analyse -v
```

Debugging
------------------
#### 1. Install and enable xdebug in the php container

```sh
make enable-xdebug
```
> **Note:** If you've been working with this stack before you'll need to rebuild the php image for this command to work:
> ```
> docker compose build php
> docker compose restart php nginx
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

```sh
docker compose restart php nginx
```

Running migrations
------------------

When pulling change from the remote, the database models may have changed. To apply the changes, you will need to run a database migration.

```sh
make migrations-migrate
```

License
-------

The code is licensed under the [Coopyleft License](https://wiki.coopcycle.org/en:license), meaning you can use this software provided:

- You are matching with the social and common company’s criteria as define by their national law, or by the European Commission in its [October 25th, 2011 communication](http://www.europarl.europa.eu/meetdocs/2009_2014/documents/com/com_com(2011)0681_/com_com(2011)0681_en.pdf), or by default by the Article 1 of the French law [n°2014-856 of July 31st, 2014](https://www.legifrance.gouv.fr/affichTexte.do?cidTexte=JORFTEXT000029313296&categorieLien=id) “relative à l’économie sociale et solidaire”
- You are using a cooperative model in which workers are employees
