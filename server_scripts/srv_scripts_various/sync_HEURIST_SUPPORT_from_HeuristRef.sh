#!/bin/bash

# File: sync_HEURIST_SUPPORT_from_HeuristRef.sh
# Purpose: Download and replace files listed in manifest.txt from heuristref.net/HEURIST/DISTRIBUTION/HEURIST_SUPPORT
# Sends email to support@heuristnetwork.org if any file fails
# ChatGPT prompted by Ian Johnson 13/7/25

# Run from crontab: sudo crontab -e
# 20 0 * * * /srv/scripts/sync_HEURIST_SUPPORT_from_HeuristRef.sh

BASE_URL="https://heuristref.net/HEURIST/DISTRIBUTION/HEURIST_SUPPORT"
TARGET_DIR="/var/www/html/HEURIST/DISTRIBUTION/HEURIST_SUPPORT"
TMP_DIR="/tmp"
LOG_FILE="/var/log/heurist_sync.log"
MAIL_TO="support@heuristnetwork.org"
HOSTNAME=$(hostname -f)
FAILED_FILES=()

# Download manifest.txt
MANIFEST_URL="${BASE_URL}/manifest.txt"
MANIFEST_LOCAL="${TMP_DIR}/manifest.txt"

if ! curl --fail --silent --show-error --location -o "$MANIFEST_LOCAL" "$MANIFEST_URL"; then
  echo "$(date) - Failed to download manifest.txt from $MANIFEST_URL" >> "$LOG_FILE"
  echo "Failed to download manifest.txt from $MANIFEST_URL on $HOSTNAME at $(date)" | \
    mail -s "[HEURIST] Manifest download failed on $HOSTNAME" "$MAIL_TO"
  exit 1
fi

# Read and process manifest
while IFS= read -r FILE || [ -n "$FILE" ]; do
  # Trim whitespace
  FILE=$(echo "$FILE" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')

  # Skip blank lines and comments
  [[ -z "$FILE" || "$FILE" == \#* ]] && continue

  # Determine full remote URL and local path
  if [[ "$FILE" == */* ]]; then
    RELATIVE_PATH="$FILE"
  else
    RELATIVE_PATH="$FILE"
  fi

  TMPFILE="${TMP_DIR}/$(basename "$FILE").tmp"
  TARGET="${TARGET_DIR}/${FILE}"
  URL="${BASE_URL}/${RELATIVE_PATH}"

  # Ensure local subdirectory exists
  mkdir -p "$(dirname "$TARGET")"

  if curl --fail --silent --show-error --location -o "$TMPFILE" "$URL"; then
    mv "$TMPFILE" "$TARGET"
    echo "$(date) - $FILE updated successfully" >> "$LOG_FILE"
  else
    echo "$(date) - Failed to download $FILE" >> "$LOG_FILE"
    FAILED_FILES+=("$FILE")
    rm -f "$TMPFILE"
  fi
done < "$MANIFEST_LOCAL"

# Send email if any download failed
if [ "${#FAILED_FILES[@]}" -gt 0 ]; then
  {
    echo "One or more files failed to sync from heuristref.net to $HOSTNAME:"
    echo ""
    for f in "${FAILED_FILES[@]}"; do
      echo " - $f"
    done
    echo ""
    echo "Check the server ($HOSTNAME) for details."
    echo "Timestamp: $(date)"
  } | mail -s "[HEURIST] File sync failure on $HOSTNAME" "$MAIL_TO"
fi