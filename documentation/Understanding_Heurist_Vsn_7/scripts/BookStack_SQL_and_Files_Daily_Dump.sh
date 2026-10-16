#!/usr/bin/env bash

# This script does a complete backup of the BookStack Heurist documentation system
# Specifically the end-user documentation "Understanding Heurist"
# but could later include multiple versions and/or additional documentation 
# Written by ChatGPT & Ian Johnson 2 July 2026

set -euo pipefail

# === CONFIGURATION ===
BOOKSTACK_DIR="/var/www/bookstack"        # change if needed
BACKUP_DIR="/srv/BACKUP/BookStack/SQL_and_Files_retain_1_month"
DB_NAME="bookstack"
DB_USER="heurist"
DB_PASS="password here"
MYSQL_HOST="localhost"

KEEP_DAYS=30

# === DO NOT EDIT BELOW UNLESS NEEDED ===
DATE="$(date +%F_%H-%M-%S)"
DEST="$BACKUP_DIR/$DATE"

mkdir -p "$DEST"

echo "Starting BookStack backup: $DATE"

# SQL dump
mysqldump \
  --user="$DB_USER" \
  --password="$DB_PASS" \
  --single-transaction \
  --quick \
  --lock-tables=false \
  "$DB_NAME" > "$DEST/bookstack.sql"

gzip "$DEST/bookstack.sql"

# Required BookStack files
tar -czf "$DEST/bookstack-files.tar.gz" \
  -C "$BOOKSTACK_DIR" \
  .env \
  public/uploads \
  storage/uploads \
  themes

# Optional manifest
{
  echo "BookStack backup"
  echo "Date: $DATE"
  echo "BookStack dir: $BOOKSTACK_DIR"
  echo "Database: $DB_NAME"
  echo
  ls -lh "$DEST"
} > "$DEST/manifest.txt"

# Remove old backups
find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} \;

echo "Backup complete: $DEST"
