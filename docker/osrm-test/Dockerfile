FROM osrm/osrm-backend:v5.16.0

RUN apk add wget

RUN wget --no-check-certificate https://coopcycle.org/osm/paris-france.osm.pbf -O data.osm.pbf
RUN osrm-extract -p /opt/bicycle.lua data.osm.pbf
RUN osrm-partition data.osrm
RUN osrm-customize data.osrm

CMD ["osrm-routed", "--algorithm", "mld", "data.osrm"]
