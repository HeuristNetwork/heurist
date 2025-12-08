#!/usr/bin/env bash
set -euo pipefail

################################################################################
# CONFIGURATION
################################################################################

# MySQL connection
MYSQL_USER="heurist"
MYSQL_PASSWORD="SpeakEasy~1935"        # leave empty if using ~/.my.cnf
MYSQL_HOST="localhost"
DB_PREFIX="hdb_"

# Local filestore base
LOCAL_FILESTORE_BASE="/var/www/html/HEURIST/HEURIST_FILESTORE"

# Remote backup server
REMOTE_USER="dbtunnel"    # change to actual remote user
REMOTE_HOST="heurist.huma-num.fr"  # change to actual host/IP
REMOTE_BASE="/var/www/html/HEURIST/HEURIST_FILESTORE/_DBS_FROM_REMOTES"

# Misc
DATE="$(date +%F_%H-%M-%S)"
LOG_TAG="heurist_backup"


################################################################################
# MYSQL HELPER (handles optional password cleanly)
################################################################################

mysql_cmd() {
    if [[ -n "$MYSQL_PASSWORD" ]]; then
        mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -h "$MYSQL_HOST" "$@"
    else
        mysql -u "$MYSQL_USER" -h "$MYSQL_HOST" "$@"
    fi
}

mysqldump_cmd() {
    if [[ -n "$MYSQL_PASSWORD" ]]; then
        mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -h "$MYSQL_HOST" "$@"
    else
        mysqldump -u "$MYSQL_USER" -h "$MYSQL_HOST" "$@"
    fi
}


################################################################################
# FETCH DATABASE LIST
################################################################################

echo "[$LOG_TAG] $(date) - Starting backup run"

DBS=$(mysql_cmd -N -e "SHOW DATABASES LIKE '${DB_PREFIX}%'")

if [[ -z "$DBS" ]]; then
    echo "[$LOG_TAG] No databases found with prefix '${DB_PREFIX}'. Exiting."
    exit 0
fi


################################################################################
# LOOP OVER DATABASES
################################################################################

for DB in $DBS; do
    SHORT_NAME="${DB#$DB_PREFIX}"  # strip hdb_ prefix

    echo "[$LOG_TAG] Processing database: ${DB} (short name: ${SHORT_NAME})"

    # Ensure backup directory exists
    BACKUP_DIR="${LOCAL_FILESTORE_BASE}/${SHORT_NAME}/backup"
    mkdir -p "$BACKUP_DIR"

    # Dump + gzip
    DUMP_FILE="${BACKUP_DIR}/${DB}_${DATE}.sql.gz"
    echo "[$LOG_TAG] Dumping database ${DB} to ${DUMP_FILE}"

    mysqldump_cmd "$DB" | gzip > "$DUMP_FILE"

    # Rsync filestore directory for this DB
    LOCAL_DIR="${LOCAL_FILESTORE_BASE}/${SHORT_NAME}/"
    REMOTE_DIR="${REMOTE_BASE}/${SHORT_NAME}/"

    echo "[$LOG_TAG] Rsync ${LOCAL_DIR} -> ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"

    rsync -az --delete \
      -e "ssh -i /home/tunnel/.ssh/dbtunnel_ed25519 -o IdentitiesOnly=yes" \
      "$LOCAL_DIR" \
      "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"
        
    # TESTING: stop after first database
    break        
done

echo "[$LOG_TAG] $(date) - Backup run completed successfully."
