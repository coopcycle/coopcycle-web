FROM node:22-alpine

RUN apk update && apk upgrade && \
    apk add --no-cache bash git openssh

WORKDIR /srv/coopcycle

COPY package.json /srv/coopcycle
COPY package-lock.json /srv/coopcycle

COPY docker/webpack/start.sh /

RUN chmod +x /start.sh

ENTRYPOINT ["/start.sh"]
