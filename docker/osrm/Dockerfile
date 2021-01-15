FROM osrm/osrm-backend:v5.23.0

RUN apt-get update && apt-get install -y openssl wget

COPY ./start.sh /usr/local/bin/osrm-start

RUN chmod +x /usr/local/bin/osrm-start

CMD ["osrm-start"]
