CoopCycle
=========

Prerequisites
-------------

* Install [VirtualBox](https://www.virtualbox.org/) & [Vagrant](https://docs.vagrantup.com/v2/installation/index.html)
* Install [Ansible](http://docs.ansible.com/intro_installation.html#installation).
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