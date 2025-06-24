#!/bin/bash

########################################################################
# Script: check_for_required_tables.sh
# Purpose: Check Heurist MySQL databases (starting with 'hdb_') for presence 
#          of required tables. Send an email if any are missing.
#          Tables have (once) disappeared from a database so this is a precaution
# Run via: cron (ensure full paths and independent environment)
# Locale-safe and logs to file.
########################################################################

# Configuration
HOSTNAME=$(hostname)
RECIPIENTS="ian.johnson.heurist@gmail.com,info.heurist@gmail.com,support@heuristnetwork.org"
REQUIRED_TABLES_FILE="/srv/scripts/check_for_required_tables_list.txt"
MYSQL="/usr/bin/mysql"
LOGIN_PATH="--login-path=local"
LOG_FILE="/srv/scripts/results/check_required_tables.log"

# remove logfile from previous run (only needed to check if it did not work) to avoid accumulation 
rm -f "$LOG_FILE"

# Temp files
TEMP_DB_TABLES=$(mktemp)
TEMP_REF_TABLES=$(mktemp)

# Start log block
{
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting database table check"

    # Check that the reference table list exists
    if [ ! -f "$REQUIRED_TABLES_FILE" ]; then
        echo "ERROR: Required tables file not found: $REQUIRED_TABLES_FILE"
        exit 1
    fi

    # Sort the required tables list using byte-wise (C) locale
    LC_ALL=C sort "$REQUIRED_TABLES_FILE" > "$TEMP_REF_TABLES"

    EMAIL_BODY=""
    SUBJECT_PREFIX="$HOSTNAME"

    # Get list of databases starting with hdb_
    DATABASES=$($MYSQL $LOGIN_PATH -N -e \
        "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'hdb_%';")

    for DB in $DATABASES; do
        echo "Checking database: $DB"

        # Get and sort actual tables from the database
        $MYSQL $LOGIN_PATH -N -B -D "$DB" -e "SHOW TABLES;" > "$TEMP_DB_TABLES"
        LC_ALL=C sort -o "$TEMP_DB_TABLES" "$TEMP_DB_TABLES"
        # was: $MYSQL $LOGIN_PATH -N -B -D "$DB" -e "SHOW TABLES;" | LC_ALL=C sort > "$TEMP_DB_TABLES"
        # which gave occasional inconsistent sorting

        # Compare against required list
        MISSING=$(comm -23 "$TEMP_REF_TABLES" "$TEMP_DB_TABLES")

        if [ -n "$MISSING" ]; then
            EMAIL_BODY+="$DB is missing the following tables:\n"
            while IFS= read -r TABLE; do
                EMAIL_BODY+="  - $TABLE\n"
            done <<< "$MISSING"
            EMAIL_BODY+="\n"
        fi
    done

    # Clean up temp files
    rm -f "$TEMP_DB_TABLES" "$TEMP_REF_TABLES"

    # Send email
    if [ -z "$EMAIL_BODY" ]; then
        echo "All databases OK"
        echo "All databases contain the required tables." | \
            /usr/bin/mail -s "$SUBJECT_PREFIX: all dbs have correct tables" "$RECIPIENTS"
    else
        echo -e "$EMAIL_BODY"
        echo -e "$EMAIL_BODY" | \
            /usr/bin/mail -s "$SUBJECT_PREFIX: WARNING databases with missing tables" "$RECIPIENTS"
    fi

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Finished database table check"
    echo ""
} >> "$LOG_FILE" 2>&1

