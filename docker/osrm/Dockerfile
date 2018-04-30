FROM osrm/osrm-backend:v5.16.0

COPY ./start.sh /usr/local/bin/osrm-start

RUN chmod +x /usr/local/bin/osrm-start

CMD ["osrm-start"]
