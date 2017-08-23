#!/bin/sh

cd /srv/coopcycle
webpack-dev-server --watch-poll --host 0.0.0.0 --content-base=web/
