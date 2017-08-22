#!/bin/sh

cd /srv/coopcycle
npm install
webpack-dev-server --watch-poll --content-base=web/
