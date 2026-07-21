#!/bin/sh
set -e

RAW_PBF="/data/$(basename "$OSRM_FILENAME" .osrm).osm.pbf"
LUA_PROFILE="/opt/bicycle.lua" # shipped by the upstream project-osrm image

if [ ! -f "/data/${OSRM_FILENAME}.cells" ]; then
    if [ ! -f "$RAW_PBF" ]; then
        echo "[error] Neither preprocessed data nor $RAW_PBF found in /data."
        exit 1
    fi

    echo "[init] Running data pipeline on $RAW_PBF..."
    osrm-extract   -p "$LUA_PROFILE" "$RAW_PBF"
    osrm-partition "/data/$OSRM_FILENAME"
    osrm-customize "/data/$OSRM_FILENAME"
    echo "[init] Data pipeline complete."
else
    echo "[init] Using existing preprocessed data for $OSRM_FILENAME."
fi

exec osrm-routed --algorithm mld "/data/$OSRM_FILENAME"
