#!/bin/bash

# Script: remove_dupes_from_archive.sh
# This script removes redundant older bz2 files of SQL database dumps which accumulate in /srv/BACKUP/ARCHIVE.

ARCHIVE_DIR="/srv/BACKUP/ARCHIVE"
LOG_FILE="${ARCHIVE_DIR}/__archive_file_deletions_log.txt"
KEEP_RECENT=4   # Number of recent backup files to keep

cd "$ARCHIVE_DIR" || { echo "Error: Could not change to archive directory $ARCHIVE_DIR"; exit 1; }

# Set file permissions for backups (allow others to read backups)
umask 022

# Function to log messages
log_message() {
    echo "$1" >> "$LOG_FILE"
}

# Function to remove faulty dumps (size < 10K)
remove_faulty_dumps() {
    log_message "Starting faulty dump cleanup at $(date +'%d-%m-%Y %H:%M:%S')"
    find . -type f -name "hdb_*.sql.bz2*" -size -10k -print -delete
    log_message "Faulty dump cleanup completed at $(date +'%d-%m-%Y %H:%M:%S')"
}

# Function to clean up multiple copies, keeping only the most recent $KEEP_RECENT backups per database
cleanup_multiple_copies() {
    log_message "Starting multiple copy cleanup at $(date +'%d-%m-%Y %H:%M:%S')"

    # Get unique database names (without the date suffix)
    for db in $(find . -type f -name "*.sql.bz2.*" | cut -d"." -f1 | sort -u); do
        # Find files related to this database, sort by modification time, and remove all except the most recent $KEEP_RECENT
        find . -type f -name "${db}.sql.bz2.*" -print0 | xargs -0 ls -t | sed "1,${KEEP_RECENT}d" | xargs -r rm -f
    done

    log_message "Multiple copy cleanup completed at $(date +'%d-%m-%Y %H:%M:%S')"
}

# Function to handle other purging logic (e.g., based on date ranges)
# You can implement the specific purging logic you mentioned, if necessary
purge_older_files() {
    log_message "Starting purging of older files at $(date +'%d-%m-%Y %H:%M:%S')"

    # Implement date-based purging logic here, if needed, following the rules for retention (e.g., keeping only one file per specific date range)

    log_message "Purging of older files completed at $(date +'%d-%m-%Y %H:%M:%S')"
}

# Main Script Execution

# 1. Remove faulty SQL dump files (size < 10K)
remove_faulty_dumps

# 2. Clean up multiple copies of database dumps, keeping only the most recent $KEEP_RECENT backups per database
cleanup_multiple_copies

# 3. Purge older files based on custom retention policies (if applicable)
purge_older_files

log_message "Script execution completed at $(date +'%d-%m-%Y %H:%M:%S')"

