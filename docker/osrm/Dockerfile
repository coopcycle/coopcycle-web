FROM ghcr.io/project-osrm/osrm-backend:26.7-debian

# https://github.com/coopcycle/coopcycle-web/issues/3606
# https://stackoverflow.com/questions/76094428/debian-stretch-repositories-404-not-found
# RUN sed -i s/deb.debian.org/archive.debian.org/g /etc/apt/sources.list
# RUN sed -i 's|security.debian.org|archive.debian.org/|g' /etc/apt/sources.list
# RUN sed -i '/stretch-updates/d' /etc/apt/sources.list

RUN apt-get update && apt-get install -y --no-install-recommends openssl wget \
    && rm -rf /var/lib/apt/lists/*

COPY ./start.sh /usr/local/bin/osrm-start

RUN chmod +x /usr/local/bin/osrm-start

CMD ["osrm-start"]
