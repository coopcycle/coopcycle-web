#!/bin/sh

if [ -f "/data/$OSRM_FILENAME" ]; then
    osrm-routed /data/$OSRM_FILENAME
fi
