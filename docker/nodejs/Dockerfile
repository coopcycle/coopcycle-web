FROM node:7.9.0-alpine

RUN npm install -g pm2

WORKDIR /srv/coopcycle

CMD ["pm2-docker", "pm2.config.js"]
