#!/usr/bin/env bash
set -euo pipefail

MAIL_TO="ian.johnson.heurist@gmail.com"
MAIL_SUBJECT="BookStack API export FAILED on $(hostname)"
LOG_FILE="/tmp/bookstack_api_export_$(date +%Y%m%d_%H%M%S).log"

exec > >(tee -a "$LOG_FILE") 2>&1

trap 'status=$?; if [ "$status" -ne 0 ]; then echo "BookStack API export failed with exit code $status"; mail -s "$MAIL_SUBJECT" "$MAIL_TO" < "$LOG_FILE"; fi' EXIT



################################################################################
# CONFIGURATION
################################################################################

# Remote backup server
REMOTE_USER="dbtunnel"    # change to actual remote user
REMOTE_HOST="heurist.huma-num.fr"  # change to actual host/IP
REMOTE_BASE="/var/www/html/HEURIST/HEURIST_FILESTORE/_DBS_FROM_REMOTES"

# Misc
DATE="$(date +%F_%H-%M-%S)"
LOG_TAG="heurist_backup"


################################################################################
# Backup the BookStack dump
################################################################################

    LOCAL_DIR="/srv/BACKUP/BookStack/"
    REMOTE_DIR="${REMOTE_BASE}/BookStack/"

    echo "[$LOG_TAG] Ensuring remote directory exists..."
    ssh -i /home/tunnel/.ssh/dbtunnel_ed25519 \
    -o IdentitiesOnly=yes \
    "${REMOTE_USER}@${REMOTE_HOST}" \
    "mkdir -p '${REMOTE_DIR}'"    

    echo "[$LOG_TAG] Rsync BookStack -> ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"


    rsync -az --delete --omit-dir-times \
      -e "ssh -i /home/tunnel/.ssh/dbtunnel_ed25519 -o IdentitiesOnly=yes" \
        "$LOCAL_DIR" \
        "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"


echo "[$LOG_TAG] $(date) - BookStack push to remote completed successfully."
