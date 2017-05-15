#!/bin/sh

cd /srv/coopcycle
npm install
webpack-dev-server --host 0.0.0.0 --content-base=web/
