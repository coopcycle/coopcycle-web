FROM node:7.9.0-alpine

RUN npm install -g webpack-dev-server

COPY docker/webpack/start.sh /

WORKDIR /srv/coopcycle

CMD /start.sh
