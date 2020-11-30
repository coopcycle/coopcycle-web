#!/bin/sh

cd /srv/coopcycle

npm install

if [ "$APP_ENV" = "test" ]; then
    pm2-runtime pm2.config.js --env=test
else
    pm2-runtime pm2.config.js
fi
