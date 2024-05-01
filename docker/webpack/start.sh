#!/bin/sh
set -xe

cd /srv/coopcycle

# https://docs.cypress.io/guides/getting-started/installing-cypress.html#Skipping-installation
CYPRESS_INSTALL_BINARY=0 npm install

node_modules/.bin/encore dev-server --public $WEBPACK_PUBLIC_PATH
