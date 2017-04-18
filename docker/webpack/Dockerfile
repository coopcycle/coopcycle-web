FROM node:7.9.0-alpine

RUN npm install -g webpack webpack-dev-server

WORKDIR /srv/coopcycle

CMD webpack-dev-server --host 0.0.0.0 --content-base=web/