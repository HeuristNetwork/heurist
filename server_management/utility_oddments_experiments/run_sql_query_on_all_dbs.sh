#!/usr/bin/env bash
#
# Search every Heurist database for record IDs whose recDetails.dtl_Value matches a string,
# then write one URL per database:
#   https://heurist.huma-num.fr/?db=<db>&q=ids:1,2,3
#
# Usage:
#   ./run_sql_query_on_all_dbs.sh "SEARCH_STRING" [output_file] [base_url]
#
# Connection options (set via env vars or edit defaults below):
#   MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASS
#   Or, if you prefer, set MYSQL_LOGIN_PATH to use MySQL's --login-path.
#
set -euo pipefail

SEARCH=${1:-}
OUTFILE=${2:-heurist_urls.txt}

if [[ -z "$SEARCH" ]]; then
  echo "Usage: $0 \"SEARCH_STRING\" [output_file]" >&2
  exit 2
fi

# ---- DETECT hostname ----

BASE_URL="${3:-${BASE_URL:-}}"

# If not provided, try to auto-detect
if [ -z "$BASE_URL" ]; then
  BASE_URL_SOURCE="apachectl"
  if command -v apachectl >/dev/null 2>&1; then
    # Try ServerName lines from apachectl -S (won't abort under set -e)
    AP_DOMAIN="$({ apachectl -S 2>/dev/null || true; } | awk '
      $1=="ServerName" {print $2; exit}
      /namevhost/      {print $4; exit}
    ')"

    if [ -n "$AP_DOMAIN" ]; then
      BASE_URL="https://${AP_DOMAIN}"
    fi
  else
    BASE_URL_SOURCE="hostname"
  fi
fi

# Fallback
[ -z "$BASE_URL" ] && BASE_URL="https://$(hostname -f)"

# Validate BASE_URL and report
if [[ ! "$BASE_URL" =~ ^https?://[^/]+$ ]]; then
  echo "ERROR: BASE_URL detection produced invalid value: '$BASE_URL'" >&2
  echo "       Fix: pass explicitly as 3rd arg or BASE_URL env var, e.g. BASE_URL='https://your.domain'" >&2
  exit 2
fi

echo "INFO: Using BASE_URL=$BASE_URL" >&2

# ---- MySQL connection settings ----
MYSQL_HOST=${MYSQL_HOST:-"<SERVER>"}
MYSQL_PORT=${MYSQL_PORT:-3306}
MYSQL_USER=${MYSQL_USER:-"heurist"}
MYSQL_PASS=${MYSQL_PASS:-"<PASSWORD>"}
MYSQL_LOGIN_PATH=${MYSQL_LOGIN_PATH:-""}

mysql_base=(mysql --batch --skip-column-names --raw)

if [[ -n "$MYSQL_LOGIN_PATH" ]]; then
  mysql_base+=(--login-path="$MYSQL_LOGIN_PATH")
else
  mysql_base+=(-h"$MYSQL_HOST" -P"$MYSQL_PORT" -u"$MYSQL_USER" --password="$MYSQL_PASS")
fi

# Check MySQL connectivity early and exit with a clear message if it fails
if ! "${mysql_base[@]}" -e "SELECT 1" >/dev/null 2>&1; then
  echo "ERROR: Unable to connect to MySQL using the configured connection string." >&2
  echo "       Please verify host/port/user/password (and network/firewall if MySQL is remote)." >&2
  exit 1
fi

# ---- Helpers ----
# Escape backslashes and single-quotes for safe embedding into a single-quoted SQL string.
sql_escape_like() {
  local s="$1"
  s=${s//\\/\\\\}
  s=${s//\'/\'\'}
  printf "%s" "$s"
}

needle=$(sql_escape_like "$SEARCH")

# Find candidate Heurist schemas. Default filter matches the usual prefix.
# If your schemas are not prefixed with hdb_, change the LIKE clause.
mapfile -t DBS < <(
  echo "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'hdb\\_%' ORDER BY schema_name;" \
  | "${mysql_base[@]}"
)

: > "$OUTFILE"

for schema in "${DBS[@]}"; do
  # Only process schemas that actually have recDetails.
  has_table=$(echo "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${schema}' AND table_name='recDetails';" | "${mysql_base[@]}")
  if [[ "$has_table" -eq 0 ]]; then
    continue
  fi

  # Extract record IDs (unique).
  mapfile -t ids < <(
    echo "SELECT DISTINCT dtl_RecID FROM \`${schema}\`.recDetails WHERE dtl_Value LIKE '%${needle}%';" \
    | "${mysql_base[@]}" \
    | awk 'NF' \
    | sort -n -u
  )

  if [[ ${#ids[@]} -eq 0 ]]; then
    continue
  fi

  # Heurist URL db parameter omits the 'hdb_' prefix.
  dbparam=${schema#hdb_}

  # Join IDs with commas.
  ids_csv=$(printf "%s\n" "${ids[@]}" | paste -sd, -)

  printf "%s/heurist?db=%s&q=ids:%s\n" "$BASE_URL" "$dbparam" "$ids_csv" >> "$OUTFILE"

done

echo "Wrote $(wc -l < "$OUTFILE" | tr -d ' ') URL(s) to $OUTFILE" >&2
