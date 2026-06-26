#!/bin/sh
PRIMARY_HOST="postgres"
REPLICATION_USER="replicator"
REPLICATION_PASSWORD="replicator_password"
DATA_DIR="/var/lib/postgresql/data"

if [ ! -s "$DATA_DIR/PG_VERSION" ]; then
    echo "Starting base backup from primary..."
    until pg_isready -h "$PRIMARY_HOST" -U postgres; do
      echo "Waiting for primary database..."
      sleep 2
    done
    PGPASSWORD="$REPLICATION_PASSWORD" pg_basebackup -h "$PRIMARY_HOST" -D "$DATA_DIR" -U "$REPLICATION_USER" -v -P -X stream -R
    echo "Base backup completed."
fi

chmod 700 "$DATA_DIR"
echo "Starting PostgreSQL in standby mode..."
exec postgres
