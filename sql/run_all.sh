#!/bin/sh
set -e
DB="${1:-hotelproj}"
createdb "$DB" 2>/dev/null || true
for f in 01_schema.sql 02_constraints.sql 03_indexes.sql 04_triggers.sql 05_views.sql 06_seed.sql; do
  psql -v ON_ERROR_STOP=1 "$DB" -f "$(dirname "$0")/$f"
done
echo "Loaded into database: $DB"
