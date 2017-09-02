#!/bin/sh

cd /srv/coopcycle
npm install
pm2-docker pm2.config.js
