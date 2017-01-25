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

Prerequisites
-------------

* Install [VirtualBox](https://www.virtualbox.org/) & [Vagrant](https://docs.vagrantup.com/v2/installation/index.html)
* Install [Ansible](http://docs.ansible.com/intro_installation.html#installation).
* Install Ansible roles with Ansible Galaxy
```
$ ansible-galaxy install -r ansible/requirements.yml
```
* Install PHP, Composer, and Node

How to develop
--------------

You can run the platform locally using Vagrant.

* Generate the SSH keys for JSON Web Token:
```
$ mkdir -p var/jwt
$ openssl genrsa -out var/jwt/private.pem -aes256 4096
$ openssl rsa -pubout -in var/jwt/private.pem -out var/jwt/public.pem
```
* Run `composer install`
* Run `npm install`
* Run `vagrant up`
* Add a host to the `/etc/hosts` file:
```
192.168.33.7 coopcycle.dev
```
* Run `npm run start` to launch `webpack-dev-server`

How to provision a server
---------------------------------

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