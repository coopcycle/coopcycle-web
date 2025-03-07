FROM node:16-alpine

RUN apk update && apk upgrade && \
    apk add --no-cache bash git openssh

WORKDIR /srv/coopcycle

COPY package.json /srv/coopcycle
COPY package-lock.json /srv/coopcycle

COPY docker/storybook-react/start.sh /

RUN chmod +x /start.sh

ENTRYPOINT ["/start.sh"]
