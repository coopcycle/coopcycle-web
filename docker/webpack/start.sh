#!/bin/sh

cd /srv/coopcycle

npm install

node_modules/.bin/encore dev-server --watch-poll --host 0.0.0.0 --public localhost:8080 --port 8080 --content-base=web/ --disable-host-check
