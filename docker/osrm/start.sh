#!/bin/sh

if [ ! -f /data/marker ]; then
  echo "Executing script because this is the first launch...";

  wget --no-check-certificate https://coopcycle-assets.sfo2.digitaloceanspaces.com/osm/paris-france.osm.pbf -O /data/data.osm.pbf
  osrm-extract -p /opt/bicycle.lua /data/data.osm.pbf
  osrm-partition /data/data.osrm
  osrm-customize /data/data.osrm

  touch /data/marker;
else
  echo "Script has already been executed in a previous launch.";
fi

if [ -f "/data/$OSRM_FILENAME" ]; then
    osrm-routed --algorithm mld /data/$OSRM_FILENAME
fi
