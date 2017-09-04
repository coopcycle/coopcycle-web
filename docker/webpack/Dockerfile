FROM node:8.2-alpine

RUN apk update && apk add --no-cache git

RUN npm install -g webpack-dev-server

COPY docker/webpack/start.sh /

WORKDIR /srv/coopcycle

COPY package.json /srv/coopcycle
COPY package-lock.json /srv/coopcycle
RUN npm install

CMD /start.sh
