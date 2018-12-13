#!/bin/sh

cd /srv/coopcycle
npm install

node_modules/.bin/encore dev-server --watch-poll --host 0.0.0.0 --content-base=web/ --disable-host-check
