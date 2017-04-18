CoopCycle
=========

[![Build Status](https://travis-ci.org/coopcycle/coopcycle-web.svg?branch=master)](https://travis-ci.org/coopcycle/coopcycle-web)

CoopCycle is a **self-hosted** platform to order meals in your neighborhood and get them delivered by bike couriers. The only difference is the software is [reserved to co-ops](#license).

The main idea here is to **decentralize** this kind of services, by allowing couriers to **own the platform** they are working for.
In each city, couriers are encouraged to organize into co-ops, and to run their very own version of the software.

The software is still under development, it is not even pre-alpha.

Of course, there is also a [native app](https://github.com/coopcycle/coopcycle-app).

Technical overview
------------------

This repository is a monolith containing the platform itself.
It is basically a PHP application backed by a PostgreSQL database + Redis/Node.js code to power the realtime services.
It also provides a routing service with [OSRM](http://project-osrm.org/).

![alt tag](https://raw.githubusercontent.com/coopcycle/coopcycle-web/master/docs/img/technical-overview.png)

[API documentation](https://coopcycle.org/api/docs)

How to develop
--------------

Developement environment comes in two flavors: Vagrant & Docker.

### Prerequisites

* Install [VirtualBox](https://www.virtualbox.org/)
* Generate the SSH keys for JSON Web Token:
```
$ mkdir -p var/jwt
$ openssl genrsa -out var/jwt/private.pem -aes256 4096
$ openssl rsa -pubout -in var/jwt/private.pem -out var/jwt/public.pem
```
* Download metro extract for your area from [MapZen](https://mapzen.com/data/metro-extracts/) (example for Paris)
```
mkdir -p var/osrm
wget https://s3.amazonaws.com/metro-extracts.mapzen.com/paris_france.osm.pbf -O var/osrm/data.osm.pbf
```

### Using Vagrant

* Install [Vagrant](https://docs.vagrantup.com/v2/installation/index.html) & [Ansible](http://docs.ansible.com/intro_installation.html#installation).
* Install Ansible roles with Ansible Galaxy
```
$ ansible-galaxy install -r ansible/requirements.yml
```
```
* Install PHP, Composer, and Node
* Run `composer install`
* Run `npm install`
* Run `vagrant up`
* Create the database schema
```
vagrant ssh -c 'sudo -u www-data php /var/www/coopcycle/bin/console doctrine:schema:create'
```
* Add a host to the `/etc/hosts` file:
```
192.168.33.7 coopcycle.dev
```
* Run `npm run start` to launch `webpack-dev-server`

### Using Docker

* Create a Docker Machine if needed
```
docker-machine create -d virtualbox
eval $(docker-machine env default)
```
* Start the Docker containers
```
docker-compose up -d
```
* Create the database
```
docker-compose run php bin/console doctrine:database:create
docker-compose run php bin/console doctrine:schema:create
```
* Pre-process the OpenStreeMap data for OSRM
```
docker-compose run osrm osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
docker-compose run osrm osrm-contract /data/data.osrm
```

* Open the platform in your browser
```
open http://`docker-machine ip`
```

How to provision a server
-------------------------

The same Ansible roles used to provision the virtual machine are used to provision the server.

Copy `ansible/hosts.dist`

```
cp ansible/hosts.dist ansible/hosts
```
Modify `ansible/hosts.dist` to put your server name and IP address.
```
[server_name]
XXX.XXX.XXX.XXX
```

Copy `ansible/group_vars/prod.yml.dist`
```
cp ansible/group_vars/prod.yml.dist ansible/group_vars/server_name.yml
```

Run `ansible-playbook` to provision the server.
```
ansible-playbook -i ansible/hosts ansible/playbook.yml
```

License
-------

The code is licensed under the [Peer Production License](https://wiki.p2pfoundation.net/Peer_Production_License), meaning you can use this software provided:

* You are a worker-owned business or worker-owned collective
* All financial gain, surplus, profits and benefits produced by the business or collective are distributed among the worker-owners