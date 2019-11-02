FROM osrm/osrm-backend:v5.16.0

# Fix "can't execute 'ssl_helper'"
# https://github.com/Yelp/dumb-init/issues/73
RUN apk --no-cache add openssl wget

COPY ./start.sh /usr/local/bin/osrm-start

RUN chmod +x /usr/local/bin/osrm-start

CMD ["osrm-start"]
