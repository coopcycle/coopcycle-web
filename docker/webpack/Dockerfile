FROM node:8.5-alpine

RUN apk update && apk add --no-cache git

WORKDIR /srv/coopcycle

COPY package.json /srv/coopcycle
COPY package-lock.json /srv/coopcycle
RUN npm install

COPY docker/webpack/start.sh /

CMD /start.sh
