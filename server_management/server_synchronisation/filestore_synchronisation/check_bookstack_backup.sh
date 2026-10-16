#!/usr/bin/env bash

# Check the daily BookStack (Heurist end-user documentation) backup copied to the Huma-Num Heurist server.
# Intended to be run from cron after the daily backup has completed eg. 8am
# Code by ChatGPT 5.6 Sol light 14/8/26, instructions Ian Johnson

set -u
set -o pipefail

BASE_DIR="/var/www/html/HEURIST/HEURIST_FILESTORE/_DBS_FROM_REMOTES/BookStack"

# The result is mailed to MAILTO set in the crontab, an email address can also be passed as the first parameter
RECIPIENT="${1:-${MAILTO:-}}"

# These are the three directories containign the BookStack backups
# All files should have today's date, we also check that there are an adequate number of files 
# in each directory in case something has gone wrong during the backup/copying process

DAILY_TOP="daily_MD_JSon_files_snapshot"
API_TOP="API_exports_retain_1_month"
SQL_TOP="SQL_and_Files_retain_1_month"

TODAY=$(date +%F)
FAILURES=()

add_failure() {
    local top_directory=$1
    local message=$2
    FAILURES+=("$top_directory: $message")
}

# Report files whose modification date is not today. Empty directories are
# handled separately where the presence of files is required.
check_all_files_are_today() {
    local directory=$1
    local top_directory=$2
    local description=$3
    local file file_date
    local wrong_count=0
    local examples=()

    while IFS= read -r -d '' file; do
        file_date=$(date -r "$file" +%F 2>/dev/null || printf 'unknown')
        if [[ $file_date != "$TODAY" ]]; then
            ((wrong_count++))
            if (( ${#examples[@]} < 5 )); then
                examples+=("${file#$BASE_DIR/} ($file_date)")
            fi
        fi
    done < <(find "$directory" -type f -print0 2>/dev/null)

    if (( wrong_count > 0 )); then
        add_failure "$top_directory" \
            "$description contains $wrong_count file(s) not dated $TODAY; example(s): ${examples[*]}"
    fi
}

# Return the latest directory whose name is YYYY-MM-DD_HH-MM-SS for today.
find_latest_dated_directory() {
    local parent=$1
    local candidate name
    local matches=()

    while IFS= read -r -d '' candidate; do
        name=${candidate##*/}
        if [[ $name =~ ^${TODAY}_[0-9]{2}-[0-9]{2}-[0-9]{2}$ ]]; then
            matches+=("$candidate")
        fi
    done < <(find "$parent" -mindepth 1 -maxdepth 1 -type d -print0 2>/dev/null)

    if (( ${#matches[@]} == 0 )); then
        return 1
    fi

    printf '%s\n' "${matches[@]}" | sort | tail -n 1
}

check_daily_snapshot() {
    local top_path="$BASE_DIR/$DAILY_TOP"
    local book_path="$top_path/7_Understanding_Heurist_2026_Vsn_7"
    local files_path="$book_path/files"
    local count

    if [[ ! -d $top_path ]]; then
        add_failure "$DAILY_TOP" "top-level directory is missing"
        return
    fi

    count=$(find "$top_path" -type f -printf '.' 2>/dev/null | wc -c)
    if (( count == 0 )); then
        add_failure "$DAILY_TOP" "no files found"
    else
        check_all_files_are_today "$top_path" "$DAILY_TOP" "snapshot"
    fi

    if [[ ! -d $book_path ]]; then
        add_failure "$DAILY_TOP" \
            "directory 7_Understanding_Heurist_2026_Vsn_7 is missing"
        return
    fi

    count=$(find "$book_path" -maxdepth 1 -type f -printf '.' 2>/dev/null | wc -c)
    if (( count < 28 )); then
        add_failure "$DAILY_TOP" \
            "7_Understanding_Heurist_2026_Vsn_7 contains only $count files at its top level (minimum 28)"
    fi

    if [[ ! -d $files_path ]]; then
        add_failure "$DAILY_TOP" \
            "7_Understanding_Heurist_2026_Vsn_7/files directory is missing"
    else
        count=$(find "$files_path" -type f -printf '.' 2>/dev/null | wc -c)
        if (( count < 200 )); then
            add_failure "$DAILY_TOP" \
                "7_Understanding_Heurist_2026_Vsn_7/files contains only $count files (minimum 200)"
        fi
    fi
}

check_api_exports() {
    local top_path="$BASE_DIR/$API_TOP"
    local dated_path required_path folder count
    local required_folders=(pages_markdown chapters_markdown books_zip books_markdown)

    if [[ ! -d $top_path ]]; then
        add_failure "$API_TOP" "top-level directory is missing"
        return
    fi

    if ! dated_path=$(find_latest_dated_directory "$top_path"); then
        add_failure "$API_TOP" \
            "no directory named ${TODAY}_HH-MM-SS was found"
        return
    fi

    count=$(find "$dated_path" -type f -printf '.' 2>/dev/null | wc -c)
    if (( count == 0 )); then
        add_failure "$API_TOP" "$(basename "$dated_path") contains no files"
    else
        check_all_files_are_today "$dated_path" "$API_TOP" \
            "$(basename "$dated_path")"
    fi

    for folder in "${required_folders[@]}"; do
        required_path="$dated_path/$folder"
        if [[ ! -d $required_path ]]; then
            add_failure "$API_TOP" \
                "$(basename "$dated_path")/$folder directory is missing"
        elif ! find "$required_path" -type f -print -quit 2>/dev/null | grep -q .; then
            add_failure "$API_TOP" \
                "$(basename "$dated_path")/$folder contains no files"
        fi
    done
}

check_sql_and_files() {
    local top_path="$BASE_DIR/$SQL_TOP"
    local dated_path required_file file_date
    local required_files=(manifest.txt bookstack-files.tar.gz bookstack.sql.gz)

    if [[ ! -d $top_path ]]; then
        add_failure "$SQL_TOP" "top-level directory is missing"
        return
    fi

    if ! dated_path=$(find_latest_dated_directory "$top_path"); then
        add_failure "$SQL_TOP" \
            "no directory named ${TODAY}_HH-MM-SS was found"
        return
    fi

    for required_file in "${required_files[@]}"; do
        if [[ ! -f $dated_path/$required_file ]]; then
            add_failure "$SQL_TOP" \
                "$(basename "$dated_path")/$required_file is missing"
        else
            file_date=$(date -r "$dated_path/$required_file" +%F 2>/dev/null || printf 'unknown')
            if [[ $file_date != "$TODAY" ]]; then
                add_failure "$SQL_TOP" \
                    "$(basename "$dated_path")/$required_file is dated $file_date, not $TODAY"
            fi
        fi
    done
}

send_report() {
    local subject=$1
    local body=$2

    if [[ -z $RECIPIENT ]]; then
        printf 'ERROR: Set MAILTO in the crontab or pass the mail address as first parameter.\n%s\n' "$body" >&2
        return 2
    fi

    if ! command -v mail >/dev/null 2>&1; then
        printf 'ERROR: The mail command is not installed.\n%s\n' "$body" >&2
        return 2
    fi

    printf '%s\n' "$body" | mail -s "$subject" -- "$RECIPIENT"
}

if [[ ! -d $BASE_DIR ]]; then
    add_failure "BookStack" "base directory $BASE_DIR is missing or inaccessible"
else
    check_daily_snapshot
    check_api_exports
    check_sql_and_files
fi

if (( ${#FAILURES[@]} == 0 )); then
    send_report "BookStack backup to Huma-Num OK" \
        "All BookStack backup checks passed for $TODAY on $(hostname)."
    exit $?
fi

failure_body=$(
    printf 'BookStack backup check failed for %s on %s.\n\n' "$TODAY" "$(hostname)"
    printf '%s\n' "The following problems were found:"
    printf ' - %s\n' "${FAILURES[@]}"
)

send_report "FAILURE : BookStack backup to Huma-Num" "$failure_body"

exit 1
