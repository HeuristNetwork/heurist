#!/usr/bin/env bash
set -euo pipefail

# This script is for use only on the HeuristRef.net server to obtain the association membership list.

# TO DO:  Change h7-alpha to /heurist/ once h7 is migrated to be the standard /heurist/ version on Huma-Num

# Usage /var/www/html/HEURIST/heurist/server_scripts/utility/refresh_association_members.sh  <memsec password>
# memsec is a special user of the Heurist_Contacts database
# The script is placed in the root crontab to be run hourly

# It downloads a list of Heurist Network association members in CSV format
# Members can be projects (databases) or individual users
# It is required since the records in the Contacts database are not public and they cannot therefore be exposed through a normal report

# CHANGE THE URLS if the Heurist_Contacts database is moved

# --- Config ---
DB="Heurist_Contacts"
USER="memsec"
#PASS="define your pass";
PASS="${1:-}"   # password passed as first argument
AUTH_URL="https://heurist.huma-num.fr/h7-alpha/hserv/controller/auth.php"
DATA_URL="https://heurist.huma-num.fr/h7-alpha/index.php"
OUT_FILE="/var/www/html/HEURIST/association_members.txt"
TMP_FILE="${OUT_FILE}.tmp"

if ! command -v jq >/dev/null 2>&1; then
  echo "ERROR: 'jq' is required (apt-get install jq / yum install jq)." >&2
  exit 2
fi

if [[ -z "${PASS}" ]]; then
  echo "Usage: $0 '<password>'" >&2
  exit 2
fi

mkdir -p "$(dirname "$OUT_FILE")"

# --- 1) Authenticate to get a JWT token ---
AUTH_BODY="$(jq -n \
  --arg db "$DB" \
  --arg username "$USER" \
  --arg password "$PASS" \
  '{db:$db, username:$username, password:$password}')"

AUTH_RESP="$(curl -sS -X POST "$AUTH_URL" \
  -H 'Content-Type: application/json' \
  -d "$AUTH_BODY")"

# Some servers prepend "application/json " before the JSON.
# Trim anything before the first '{' so jq gets valid JSON.
AUTH_JSON="$(printf '%s' "$AUTH_RESP" | sed 's/^[^{]*//')"

# Extract token and type (hard-fail if missing)
TOKEN="$(jq -er '.access_token' <<<"$AUTH_JSON")" || {
  echo "ERROR: no access_token in response:" >&2
  echo "$AUTH_RESP" >&2
  exit 1
}
TYPE="$(jq -r '.token_type // "Bearer"' <<<"$AUTH_JSON")"

if [[ -z "$TOKEN" ]]; then
  echo "ERROR: Authentication failed. Server response was:" >&2
  echo "$AUTH_RESP" >&2
  exit 1
fi

# --- 2) Use token to fetch data (GET with query params) ---
# -f makes curl fail (non-zero) on HTTP 4xx/5xx so we don't overwrite the file on errors.
curl -fsS -G "$DATA_URL" \
  -H "Authorization: $TYPE $TOKEN" \
  --data "db=$DB" \
  --data "mode=txt" \
  --data-urlencode "template=Members_as_CSV.tpl" \
  --data-urlencode "q=svs:74" \
  -o "$TMP_FILE" \
  && mv "$TMP_FILE" "$OUT_FILE" \
  || { status=$?; echo "FAILED to refresh $(basename "$OUT_FILE") (exit $status). Previous file kept."; exit 1; }

echo "OK: Updated $OUT_FILE"
