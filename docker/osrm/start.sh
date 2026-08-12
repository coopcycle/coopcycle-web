#!/bin/sh

# The routing graph is built by CI / `make osrm`, not here. OSRM addresses the
# dataset by its base name (/data/data.osrm) but writes data.osrm.* files, so
# there is no plain "data.osrm" file to test for. The MLD graph
# (data.osrm.mldgr) is the final osrm-customize output, so its presence means
# the graph is ready to be served with --algorithm mld.
if [ -f "/data/${OSRM_FILENAME}.mldgr" ]; then
    exec osrm-routed --algorithm mld "/data/$OSRM_FILENAME"
else
    echo "[osrm] Routing graph not built yet (missing /data/${OSRM_FILENAME}.mldgr). Run 'make osrm'. Not starting osrm-routed."
fi
