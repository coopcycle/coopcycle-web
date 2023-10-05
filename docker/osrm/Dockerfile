FROM osrm/osrm-backend:v5.23.0

# https://github.com/coopcycle/coopcycle-web/issues/3606
# https://stackoverflow.com/questions/76094428/debian-stretch-repositories-404-not-found
RUN sed -i s/deb.debian.org/archive.debian.org/g /etc/apt/sources.list
RUN sed -i 's|security.debian.org|archive.debian.org/|g' /etc/apt/sources.list
RUN sed -i '/stretch-updates/d' /etc/apt/sources.list

RUN apt-get update && apt-get install -y openssl wget

COPY ./start.sh /usr/local/bin/osrm-start

RUN chmod +x /usr/local/bin/osrm-start

CMD ["osrm-start"]
