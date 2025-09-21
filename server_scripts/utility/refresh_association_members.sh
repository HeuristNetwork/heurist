#!/usr/bin/env bash
set -euo pipefail

# /srv/scripts/refresh_association_members.sh : This script is for use only on the HeuristRef.net server 
# to obtain the association membership list from the Heurist_Contacts database
# Place this script in /srv/scripts
# The memsec users password is set in the following file:  MEMSEC_PASS_FILE=/srv/scripts/.secrets/heuristnetwork_memsec.pass
# CHANGE THE URLS if the Heurist_Contacts database is moved

# TO DO:  Change h7-alpha to /heurist/ once h7 is migrated to be the standard /heurist/ version on Huma-Num

# Silent on success; on failure sends an email with custom subject. DEBUG=1 adds rich diagnostics.
# memsec is a special user of the Heurist_Contacts database
# The script is placed in the root crontab to be run hourly

# It downloads a list of Heurist Network association members in CSV format
# Members can be projects (databases) or individual users
# It is required since the records in the Contacts database are not public and they cannot therefore be exposed through a normal report

# Can debug on command line with:  DEBUG=1 QUIET=0 MEMSEC_PASS_FILE=/srv/scripts/.secrets/heuristnetwork_memsec.pass /srv/scripts/refresh_association_members.sh

#Crontab entry as follows:
#    17 * * * * MAILTO="support@heuristnetwork.org" MEMSEC_PASS_FILE=/srv/scripts/.secrets/heuristnetwork_memsec.pass /srv/scripts/refresh_association_members.sh >/dev/null 2>&1

umask 077
PATH="/usr/sbin:/usr/bin:/sbin:/bin"

# --- Config -------------------------------------------------------------------
readonly DB="Heurist_Contacts"
readonly USER="memsec"
readonly BASE="https://heurist.huma-num.fr/h7-alpha"   # TODO: switch to /heurist/ when migrated
readonly AUTH_URL="${BASE}/hserv/controller/auth.php"
readonly DATA_URL="${BASE}/index.php"
readonly OUT_FILE="/var/www/html/HEURIST/association_members.txt"
readonly TEMPLATE="Members_as_CSV.tpl"
readonly QUERY="svs:74"
readonly EXPECT_CT_REGEX='^(text/plain|text/csv|application/octet-stream)\b'

# Email subject prefix (override with SUBJECT_PREFIX=...)
SUBJECT_PREFIX="${SUBJECT_PREFIX:-Heurist: ERROR on refresh_association_members.sh}"

# Recipients preference: 1st arg > MAILTO > RECIPIENTS > default
_default_to="support@heuristnetwork.org"
recips="${1:-${MAILTO:-${RECIPIENTS:-$_default_to}}}"
to_header="${recips// /, }"   # spaces -> comma list (sendmail)
mail_list="${recips//,/ }"    # commas -> spaces (mail CLI)

# Quiet on success by default; set QUIET=0 for an "OK" line on success
QUIET="${QUIET:-1}"

# DEBUG=1 prints HTTP status + response body on failures (no secrets leaked).
DEBUG="${DEBUG:-0}"

# --- Secrets ------------------------------------------------------------------
MEMSEC_PASS=""
if [[ -n "${MEMSEC_PASS_FILE:-}" ]]; then
  [[ -r "$MEMSEC_PASS_FILE" ]] || { echo "ERROR: cannot read MEMSEC_PASS_FILE: $MEMSEC_PASS_FILE" >&2; exit 2; }
  MEMSEC_PASS="$(head -n1 -- "$MEMSEC_PASS_FILE" || true)"
elif [[ -n "${MEMSEC_PASS:-}" ]]; then
  MEMSEC_PASS="$MEMSEC_PASS"
else
  echo "ERROR: supply MEMSEC_PASS_FILE or MEMSEC_PASS env." >&2
  exit 2
fi
# Strip Windows CR if edited on Windows
MEMSEC_PASS="${MEMSEC_PASS//$'\r'/}"

# --- Tooling ------------------------------------------------------------------
command -v jq   >/dev/null 2>&1 || { echo "ERROR: 'jq' is required."   >&2; exit 2; }
command -v curl >/dev/null 2>&1 || { echo "ERROR: 'curl' is required." >&2; exit 2; }

# --- Job as a function so we can capture output/status ------------------------
run_job() {
  set -Eeuo pipefail

  mkdir -p -- "$(dirname -- "$OUT_FILE")"

  local orig_owner="" orig_group="" orig_mode=""
  if [[ -e "$OUT_FILE" ]]; then
    orig_owner="$(stat -c '%U' "$OUT_FILE" 2>/dev/null || true)"
    orig_group="$(stat -c '%G' "$OUT_FILE" 2>/dev/null || true)"
    orig_mode="$(stat -c '%a' "$OUT_FILE" 2>/dev/null || true)"
  fi

  local tmp_data tmp_hdrs tmp_auth
  tmp_data="$(mktemp "${OUT_FILE}.XXXXXX")"
  tmp_hdrs="$(mktemp "${OUT_FILE}.headers.XXXXXX")"
  tmp_auth="$(mktemp "${OUT_FILE}.auth.XXXXXX")"
  trap 'rm -f -- "$tmp_data" "$tmp_hdrs" "$tmp_auth"' RETURN

  # ---------- 1) Authenticate ----------
  local auth_body auth_resp auth_json token token_type
  auth_body="$(jq -n --arg db "$DB" --arg username "$USER" --arg password "$MEMSEC_PASS" \
               '{db:$db, username:$username, password:$password}')"

  if [[ "$DEBUG" == "1" ]]; then
    # In DEBUG, get status & body even on errors (don’t use --fail)
    local auth_status
    auth_status="$(curl --silent --show-error --request POST "$AUTH_URL" \
                     --header 'Content-Type: application/json' \
                     --proto '=https' --tlsv1.2 \
                     --data "$auth_body" \
                     --write-out '%{http_code}' --output "$tmp_auth" || true)"
    auth_resp="$(cat "$tmp_auth" 2>/dev/null || true)"
    if [[ "$auth_status" != "200" ]]; then
      echo "DEBUG: AUTH HTTP $auth_status" >&2
      # Show server message; no token present when 401 so safe to print
      printf '%s\n' "$auth_resp" >&2
      return 1
    fi
  else
    # Normal mode: fail fast on HTTP errors
    auth_resp="$(curl --silent --show-error --fail --request POST "$AUTH_URL" \
                   --header 'Content-Type: application/json' \
                   --proto '=https' --tlsv1.2 \
                   --data "$auth_body")"
  fi

  # Some servers prepend junk before JSON
  auth_json="$(printf '%s' "$auth_resp" | sed 's/^[^{]*//')"
  token="$(jq -er '.access_token' <<<"$auth_json" 2>/dev/null)" || {
    # Redact token if the server mistakenly returned one in an error
    local redacted
    redacted="$(printf '%s' "$auth_resp" | sed -E 's/"access_token":"[^"]{6,}"/"access_token":"<redacted>"/g')"
    echo "ERROR: Authentication failed (no access_token). Raw response follows:" >&2
    printf '%s\n' "$redacted" >&2
    return 1
  }
  token_type="$(jq -r '.token_type // "Bearer"' <<<"$auth_json")"

  # ---------- 2) Fetch ----------
  if [[ "$DEBUG" == "1" ]]; then
    local data_status
    data_status="$(curl --silent --show-error --get "$DATA_URL" \
                   --header "Authorization: ${token_type} ${token}" \
                   --proto '=https' --tlsv1.2 \
                   --dump-header "$tmp_hdrs" \
                   --data-urlencode "db=${DB}" \
                   --data-urlencode "mode=txt" \
                   --data-urlencode "template=${TEMPLATE}" \
                   --data-urlencode "q=${QUERY}" \
                   --write-out '%{http_code}' \
                   --output "$tmp_data" || true)"
    if [[ "$data_status" != "200" ]]; then
      echo "DEBUG: DATA HTTP $data_status" >&2
      echo "DEBUG: HEADERS:" >&2
      sed -n '1,40p' "$tmp_hdrs" >&2
      echo "DEBUG: BODY (first 80 lines):" >&2
      sed -n '1,80p' "$tmp_data" >&2
      return 1
    fi
  else
    curl --silent --show-error --fail --get "$DATA_URL" \
         --header "Authorization: ${token_type} ${token}" \
         --proto '=https' --tlsv1.2 \
         --dump-header "$tmp_hdrs" \
         --data-urlencode "db=${DB}" \
         --data-urlencode "mode=txt" \
         --data-urlencode "template=${TEMPLATE}" \
         --data-urlencode "q=${QUERY}" \
         --output "$tmp_data"
  fi

  # ---------- 3) Validate ----------
  local ct
  ct="$(awk 'BEGIN{IGNORECASE=1} /^Content-Type:/ {print $2; exit}' "$tmp_hdrs" | tr -d '\r\n')"
  if [[ -z "$ct" || ! "$ct" =~ $EXPECT_CT_REGEX ]]; then
    echo "ERROR: Unexpected Content-Type: '${ct:-<missing>}' — refusing to overwrite ${OUT_FILE}." >&2
    cat "$tmp_hdrs" >&2
    return 1
  fi
  [[ -s "$tmp_data" ]] || { echo "ERROR: Empty response body — refusing to overwrite ${OUT_FILE}." >&2; return 1; }
  if grep -qiE '<(html|head|body|!doctype)' "$tmp_data"; then
    echo "ERROR: Response looks like HTML (possibly error/login) — refusing to overwrite ${OUT_FILE}." >&2
    return 1
  fi

  # ---------- 4) Publish atomically & restore perms ----------
  mv -f -- "$tmp_data" "$OUT_FILE"
  rm -f -- "$tmp_hdrs" "$tmp_auth"

  if [[ -e "$OUT_FILE" ]]; then
    if [[ -n "$orig_owner" && -n "$orig_group" ]]; then chown "$orig_owner:$orig_group" "$OUT_FILE" 2>/dev/null || true; fi
    if [[ -n "$orig_mode"  ]]; then chmod "$orig_mode" "$OUT_FILE"  2>/dev/null || true; fi
  fi

  if [[ "$QUIET" != "1" ]]; then
    echo "OK: Updated ${OUT_FILE}"
  fi
}

# --- Run & email-on-failure ---------------------------------------------------
set +e
OUT="$(run_job 2>&1)"
STATUS=$?
set -e

if [[ $STATUS -ne 0 ]]; then
  SUBJECT="${SUBJECT_PREFIX} on $(hostname -s) (exit ${STATUS})"
  if command -v sendmail >/dev/null 2>&1; then
    {
      echo "To: ${to_header}"
      echo "Subject: ${SUBJECT}"
      echo "From: root@$(hostname -f)"
      echo
      printf '%s\n' "$OUT"
    } | /usr/sbin/sendmail -t
  elif command -v mail >/dev/null 2>&1; then
    printf '%s\n' "$OUT" | mail -s "$SUBJECT" ${mail_list}
  else
    logger -t refresh_association_members "FAIL (exit ${STATUS}): $OUT"
  fi
  exit "$STATUS"
fi

# Success: absolutely silent
exit 0
