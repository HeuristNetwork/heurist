#!/bin/bash

# File: list_all_heurist_servers.sh

# This script, which is only needed on the HeuristRef.net server, identifies all Heurist servers 

# It looks in the access logs (http and https) for a call to /admin/setup/dbproperties/getCurrentVersion.php
# which is used to check whether the code on the server is up-to-date with the Heurist prduction version.
# It writes a cumulative list of Heurist server IP addresses with the date of last access

# Target cumulative output file
OUTPUT="/var/www/html/HEURIST/all_heurist_servers.txt"
TMPFILE=$(mktemp)
NEWFILE=$(mktemp)

# The path to match in logs
TARGET_PATH="/admin/setup/dbproperties/getCurrentVersion.php"

# Gather log files (including main access_log)
LOGFILES=(/var/log/httpd/access_log* /var/log/httpd/ssl_access_log*)

# Collect latest date per IP from all access logs
for LOG in "${LOGFILES[@]}"; do
    [[ -f "$LOG" ]] || continue
    grep "$TARGET_PATH" "$LOG" | awk '
        {
            ip = $1
            match($0, /\[([0-9]{2}\/[A-Za-z]{3}\/[0-9]{4})/, m)
            if (m[1] != "") {
                date = m[1]
                if (!seen[ip] || date > seen[ip]) {
                    seen[ip] = date
                }
            }
        }
        END {
            for (ip in seen) {
                print ip, seen[ip]
            }
        }
    '
done | sort > "$TMPFILE"

# Read existing file and preserve structure
declare -A ip_date_map

# Load new data into memory
while read -r ip date; do
    ip_date_map["$ip"]="$date"
done < "$TMPFILE"

# Process existing file line by line
if [[ -f "$OUTPUT" ]]; then
    while IFS= read -r line; do
        # If comment or blank, preserve as-is
        if [[ "$line" =~ ^#.* || -z "$line" ]]; then
            echo "$line" >> "$NEWFILE"
            continue
        fi

        # If IP line, check for updates
        if [[ "$line" =~ ^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)[[:space:]]+([0-9]{2}/[A-Za-z]{3}/[0-9]{4})$ ]]; then
            ip="${BASH_REMATCH[1]}"
            old_date="${BASH_REMATCH[2]}"
            new_date="${ip_date_map[$ip]}"

            if [[ -n "$new_date" && "$new_date" > "$old_date" ]]; then
                echo "$ip $new_date" >> "$NEWFILE"
            else
                echo "$ip $old_date" >> "$NEWFILE"
            fi

            # Remove from new IP list so we don’t duplicate
            unset ip_date_map["$ip"]
        else
            # Unknown format — preserve
            echo "$line" >> "$NEWFILE"
        fi
    done < "$OUTPUT"
fi

# Add remaining new IPs
for ip in "${!ip_date_map[@]}"; do
    echo "$ip ${ip_date_map[$ip]}" >> "$NEWFILE"
done

# Sort IP lines, preserve comments and blanks at their positions
# Replace old file atomically
mv "$NEWFILE" "$OUTPUT"
chmod 644 "$OUTPUT"

rm -f "$TMPFILE"

