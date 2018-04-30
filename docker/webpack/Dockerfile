FROM node:8.11

WORKDIR /srv/coopcycle

COPY package.json /srv/coopcycle
COPY package-lock.json /srv/coopcycle
RUN npm install

COPY docker/webpack/start.sh /

CMD /start.sh
