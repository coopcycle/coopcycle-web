#!/bin/sh

cd /srv/coopcycle
npm install
webpack-dev-server --watch-poll --host 0.0.0.0 --content-base=$WEBPACK_BASE_DIR
