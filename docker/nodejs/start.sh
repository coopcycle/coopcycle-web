#!/bin/sh

cd /srv/coopcycle
npm install
pm2-runtime pm2.config.js
