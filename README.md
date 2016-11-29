CoopCycle
=========

This project aims to build an API & Web interface to run a food delivery platform with bike couriers.
The main idea here is to decentralize this kind of services, by allowing couriers to own the platform they are working for.
In each city, couriers are encouraged to organize into co-ops, and to run their very own version of the software.

The software is still under development, it is not even pre-alpha.

Prerequisites
-------------

* Install [VirtualBox](https://www.virtualbox.org/) & [Vagrant](https://docs.vagrantup.com/v2/installation/index.html)
* Install [Ansible](http://docs.ansible.com/intro_installation.html#installation).
* Install Ansible roles with Ansible Galaxy
```
$ ansible-galaxy install -r ansible/requirements.yml
```
* Install PHP, Composer, and Node

How to install
--------------


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

License
-------

The code is licensed under the [Peer Production License](https://wiki.p2pfoundation.net/Peer_Production_License), meaning you can use this software provided:
* You are a worker-owned business or worker-owned collective
* All financial gain, surplus, profits and benefits produced by the business or collective are distributed among the worker-owners