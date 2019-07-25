#!/bin/sh

cd /srv/coopcycle
npm install

# Use --keep-public-path to avoid having absolute URLs
# @see https://github.com/symfony/webpack-encore/blob/b5750b2fb09053687bcaca59b27a79a8396d304b/lib/WebpackConfig.js#L235
node_modules/.bin/encore dev-server --watch-poll --host 0.0.0.0 --port 8080 --content-base=web/ --disable-host-check
