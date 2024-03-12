#!/bin/sh

cd /srv/coopcycle

# https://docs.cypress.io/guides/getting-started/installing-cypress.html#Skipping-installation
CYPRESS_INSTALL_BINARY=0 npm install

node_modules/.bin/encore dev-server --public http://206.189.30.248:8080 --no-static-watch --no-live-reload --no-hot
