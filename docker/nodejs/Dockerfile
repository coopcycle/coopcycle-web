FROM node:7.9.0-alpine

RUN npm install -g pm2

RUN apk update && apk add --no-cache git

COPY docker/nodejs/start.sh /

WORKDIR /srv/coopcycle

COPY package.json /srv/coopcycle
RUN npm install

CMD /start.sh
