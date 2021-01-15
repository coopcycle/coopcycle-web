#!/bin/sh

cd /srv/coopcycle

# https://docs.cypress.io/guides/getting-started/installing-cypress.html#Skipping-installation
CYPRESS_INSTALL_BINARY=0 npm install

if [ "$APP_ENV" = "test" ]; then
    pm2-runtime pm2.config.js --env=test
else
    pm2-runtime pm2.config.js
fi
