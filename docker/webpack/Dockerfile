FROM node:7.9.0-alpine

RUN apk update && apk add --no-cache git

RUN npm install -g webpack-dev-server

COPY docker/webpack/start.sh /

WORKDIR /srv/coopcycle

COPY package.json /srv/coopcycle
RUN npm install

CMD /start.sh
