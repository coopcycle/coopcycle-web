#!/bin/sh

cd /srv/coopcycle

pm2-runtime pm2.config.js --env=test > /dev/null 2>&1 &
node node_modules/.bin/mocha --require @babel/register --exit js/tests/
pm2-runtime pm2.config.js
