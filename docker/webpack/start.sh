#!/bin/sh

cd /srv/coopcycle
npm install
node_modules/.bin/webpack-dev-server --watch-poll --host 0.0.0.0 --content-base=web/
