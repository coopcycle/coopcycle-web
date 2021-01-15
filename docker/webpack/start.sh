#!/bin/sh

cd /srv/coopcycle

# https://docs.cypress.io/guides/getting-started/installing-cypress.html#Skipping-installation
CYPRESS_INSTALL_BINARY=0 npm install

node_modules/.bin/encore dev-server --watch-poll --host 0.0.0.0 --public localhost:8080 --port 8080 --content-base=web/ --disable-host-check
