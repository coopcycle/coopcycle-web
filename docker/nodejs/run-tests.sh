#!/bin/sh

cd /srv/coopcycle

pm2-docker pm2.config.js --env=test &
node node_modules/.bin/mocha js/tests/
