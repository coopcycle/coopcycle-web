FROM node:12-alpine

WORKDIR /srv/coopcycle

COPY package.json /srv/coopcycle
COPY package-lock.json /srv/coopcycle

RUN apk update && apk upgrade && \
    apk add --no-cache bash git openssh
RUN npm install

COPY docker/webpack/start.sh /

RUN chmod +x /start.sh

ENTRYPOINT ["/start.sh"]
