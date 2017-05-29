#!/bin/sh

set -e

# Perform all actions as $POSTGRES_USER
export PGUSER="$POSTGRES_USER"

# Create the 'coopcycle_test' db
"${psql[@]}" <<- 'EOSQL'
CREATE DATABASE coopcycle_test;

EOSQL

# Load PostGIS into coopcycle_test
echo "Loading PostGIS extensions into coopcycle_test"
"${psql[@]}" --dbname="coopcycle_test" <<-'EOSQL'
    CREATE EXTENSION IF NOT EXISTS postgis;
    CREATE EXTENSION IF NOT EXISTS postgis_topology;
    CREATE EXTENSION IF NOT EXISTS fuzzystrmatch;
    CREATE EXTENSION IF NOT EXISTS postgis_tiger_geocoder;
EOSQL

