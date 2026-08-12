#!/bin/sh

if [ -f "/data/$OSRM_FILENAME" ]; then
    osrm-routed --algorithm mld /data/$OSRM_FILENAME
fi
