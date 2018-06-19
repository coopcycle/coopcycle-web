#!/bin/sh

cd /srv/coopcycle

pm2-docker pm2.config.js --env=test > /dev/null 2>&1 &
node node_modules/.bin/mocha --compilers js:babel-core/register js/tests/
