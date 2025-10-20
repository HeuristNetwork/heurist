#!/usr/bin/env bash
# ---------------------------------------------------------------------------
#  Monitor Heurist filestore directories and rsync changed ones to remotes.
#  Remote identifiers are read dynamically from heuristConfigIni.php
#  Email notification is sent if any rsync fails during a run.
# ---------------------------------------------------------------------------

set -Eeuo pipefail

# ===================== BASIC CONFIG =========================================
PARENT_DIR="${PARENT_DIR:-/var/www/html/HEURIST/HEURIST_FILESTORE}"
REMOTE_BASE="${REMOTE_BASE:-/var/www/html/HEURIST/HEURIST_FILESTORE}"
HEURIST_PHP_CONF="${HEURIST_PHP_CONF:-/var/www/html/HEURIST/heuristConfigIni.php}"

# Defaults for missing fields in PHP config
DEFAULT_SSHUSER="${DEFAULT_SSHUSER:-dbtunnel}"
DEFAULT_SSHPORT="${DEFAULT_SSHPORT:-22}"
DEFAULT_SSHKEY="${DEFAULT_SSHKEY:-/home/dbtunnel/.ssh/id_ed25519}"

# Rsync behaviour
RSYNC_OPTS_BASE=(-a --delete --numeric-ids --inplace --no-inc-recursive --stats)
[[ "${RSYNC_COMPRESS:-1}" == "1" ]] && RSYNC_OPTS_BASE+=(--compress)
EXCLUDES="${EXCLUDES:-}"
MAX_RETRIES="${MAX_RETRIES:-3}"
BACKOFF_BASE_SEC="${BACKOFF_BASE_SEC:-5}"

STATE_DIR="${STATE_DIR:-/var/lib/heurist-sync/state}"
LOG_DIR="${LOG_DIR:-/var/log/heurist-sync}"
LOCK_FILE="${LOCK_FILE:-/var/run/heurist-sync.lock}"

# Niceness
NICE_BIN="${NICE_BIN:-/usr/bin/nice}"
IONICE_BIN="${IONICE_BIN:-/usr/bin/ionice}"

# ===================== EMAIL NOTIFICATION ===================================
# Send mail via the system's `mail` command.  You can override in cron with:
#   export MAIL_TO="ian.johnson.heurist@gmail.com,osmakov@gmail.com"
#   export MAIL_SUBJECT_PREFIX="[Heurist Sync Alert]"
MAIL_TO="${MAIL_TO:-root}"
MAIL_FROM="${MAIL_FROM:-heurist-sync@$(hostname -f)}"
MAIL_SUBJECT_PREFIX="${MAIL_SUBJECT_PREFIX:-[Heurist Sync Alert]}"

send_failure_email() {
  local subject="$1" bodyfile="$2"
  if command -v mail >/dev/null 2>&1; then
    mail -aFrom:"$MAIL_FROM" -s "$MAIL_SUBJECT_PREFIX $subject" "$MAIL_TO" < "$bodyfile" || \
      log "WARN: Could not send mail (mail command failed)"
  else
    log "WARN: 'mail' command not available; cannot send email notifications"
  fi
}
# ===========================================================================

mkdir -p "$STATE_DIR" "$LOG_DIR" /var/run
DATE_UTC="$(date -u +%F)"
RUN_LOG="$LOG_DIR/run-$DATE_UTC.log"
SUMMARY_JSON="$LOG_DIR/summary-$DATE_UTC.json"
log(){ printf '%s %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*" | tee -a "$RUN_LOG" >/dev/null; }

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  log "Another run is in progress; exiting."
  exit 0
fi

IONICE_PREFIX=(); NICE_PREFIX=()
[[ -x "$IONICE_BIN" ]] && IONICE_PREFIX=("$IONICE_BIN" -c2 -n7)
[[ -x "$NICE_BIN"   ]] && NICE_PREFIX=("$NICE_BIN" -n10)

: > "$RUN_LOG" 2>/dev/null || true
touch "$SUMMARY_JSON"

# ---------------- Parse $remoteServers from heuristConfigIni.php ------------
if ! command -v php >/dev/null 2>&1; then
  log "ERROR: php CLI not found"
  exit 2
fi
if [[ ! -f "$HEURIST_PHP_CONF" ]]; then
  log "ERROR: Config file not found: $HEURIST_PHP_CONF"
  exit 2
fi

PHP_TSV="$(
  php -r '
    error_reporting(E_ERROR | E_PARSE);
    $conf=$argv[1]; include $conf;
    if(!isset($remoteServers)) exit;
    foreach($remoteServers as $id=>$cfg){
      if(strpos($id,"-")!==false) continue;
      $server=$cfg["server"]??""; if(!$server) continue;
      $sshuser=$cfg["sshuser"]??""; $sshport=$cfg["sshport"]??"";
      $sshkey=$cfg["sshkey"]??($cfg["key"]??"");
      printf("%s\t%s\t%s\t%s\t%s\n",$id,$server,$sshuser,$sshport,$sshkey);
    }
  ' "$HEURIST_PHP_CONF" 2>/dev/null
)"
if [[ -z "$PHP_TSV" ]]; then
  log "ERROR: \$remoteServers empty or unreadable"
  exit 3
fi

declare -A RS_SERVER RS_SSHUSER RS_SSHPORT RS_SSHKEY
IDENTIFIERS=()
while IFS=$'\t' read -r IDENT SERVER SSHUSER SSHPORT SSHKEY; do
  IDENTIFIERS+=("$IDENT")
  RS_SERVER["$IDENT"]="$SERVER"
  RS_SSHUSER["$IDENT"]="${SSHUSER:-$DEFAULT_SSHUSER}"
  RS_SSHPORT["$IDENT"]="${SSHPORT:-$DEFAULT_SSHPORT}"
  RS_SSHKEY["$IDENT"]="${SSHKEY:-$DEFAULT_SSHKEY}"
done <<<"$PHP_TSV"

# ---------------------------------------------------------------------------
backoff_for_attempt(){ printf '%s' $(( BACKOFF_BASE_SEC * (1 << ($1 - 1)) )); }

RUN_COUNT_OK=0
RUN_COUNT_FAIL=0
RUN_BYTES_SENT=0
RUN_BYTES_XFER=0
FAILED_DIRS=()   # collect for email

# ---------------------------------------------------------------------------
shopt -s nullglob
for dir in "$PARENT_DIR"/*; do
  [[ -d "$dir" ]] || continue
  db="$(basename "$dir")"

  match_ident=""
  for ident in "${IDENTIFIERS[@]}"; do
    if [[ "$db" == "$ident"-* ]]; then
      match_ident="$ident"; break
    fi
  done
  [[ -z "$match_ident" ]] && continue

  dest_db="${db#*-}"
  server="${RS_SERVER[$match_ident]}"
  sshuser="${RS_SSHUSER[$match_ident]}"
  sshport="${RS_SSHPORT[$match_ident]}"
  sshkey="${RS_SSHKEY[$match_ident]}"

  marker="$STATE_DIR/${db}.lastsync"
  [[ -f "$marker" ]] || : > "$marker"

  if /usr/bin/find "$dir" -type f -newer "$marker" -print -quit >/dev/null 2>&1; then
    log "Changes in $db; syncing to ${sshuser}@${server}:${REMOTE_BASE}/${dest_db}"

    SSH_CMD=(ssh -i "$sshkey" -p "$sshport" -o BatchMode=yes -o ConnectTimeout=10)
    attempt=1 success=0 start_ts=$(date +%s)
    while (( attempt <= MAX_RETRIES )); do
      tmp_out="$(mktemp)"
      if "${IONICE_PREFIX[@]}" "${NICE_PREFIX[@]}" rsync \
            "${RSYNC_OPTS_BASE[@]}" "${RSYNC_EXCLUDE_ARGS[@]}" \
            -e "${SSH_CMD[*]}" \
            "$dir/" "${sshuser}@${server}:${REMOTE_BASE}/${dest_db}/" \
            >"$tmp_out" 2>&1; then
        transferred_bytes=$(grep -Eo 'Total transferred file size: [0-9,]+' "$tmp_out" | awk '{gsub(",","",$5);print $5}')
        sent_bytes=$(grep -Eo 'Total bytes sent: [0-9,]+' "$tmp_out" | awk '{gsub(",","",$4);print $4}')
        [[ -z "$transferred_bytes" ]] && transferred_bytes=0
        [[ -z "$sent_bytes"       ]] && sent_bytes=0
        duration=$(( $(date +%s) - start_ts ))
        log "OK   $db -> ${dest_db} on ${server} | transferred=${transferred_bytes}B sent=${sent_bytes}B duration=${duration}s"
        touch "$marker"
        RUN_COUNT_OK=$((RUN_COUNT_OK+1))
        RUN_BYTES_XFER=$((RUN_BYTES_XFER+transferred_bytes))
        RUN_BYTES_SENT=$((RUN_BYTES_SENT+sent_bytes))
        success=1
        rm -f "$tmp_out"; break
      else
        err_preview="$(tail -n 5 "$tmp_out" | tr '\n' ' ' )"
        log "FAIL $db -> ${dest_db} on ${server} | attempt=${attempt} | $err_preview"
        rm -f "$tmp_out"
        (( attempt < MAX_RETRIES )) && sleep "$(backoff_for_attempt "$attempt")"
      fi
      attempt=$((attempt+1))
    done
    (( success )) || { RUN_COUNT_FAIL=$((RUN_COUNT_FAIL+1)); FAILED_DIRS+=("$db"); }
  fi
done

# ================== EMAIL NOTIFICATION ON FAILURES ===========================
if (( RUN_COUNT_FAIL > 0 )); then
  tmpmail="$(mktemp)"
  {
    echo "Heurist filestore sync encountered failures on $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
    echo
    echo "Failed directories:"
    for d in "${FAILED_DIRS[@]}"; do echo "  - $d"; done
    echo
    echo "See log: $RUN_LOG"
    echo
    echo "Last 20 log lines:"
    tail -n 20 "$RUN_LOG"
  } > "$tmpmail"
  send_failure_email "Rsync failures ($RUN_COUNT_FAIL)" "$tmpmail"
  rm -f "$tmpmail"
fi

# ================== SUMMARY JSON (same as before) ============================
ok_today=$(grep -c '^....-..-..T..:..:..Z OK   ' "$RUN_LOG" || true)
fail_today=$(grep -c '^....-..-..T..:..:..Z FAIL ' "$RUN_LOG" || true)
bytes_sent_today=$(grep -E 'OK\s' "$RUN_LOG" | grep -Eo 'sent=[0-9]+B' | awk -F= '{gsub(/B/,"",$2);s+=$2}END{print s+0}')
bytes_xfer_today=$(grep -E 'OK\s' "$RUN_LOG" | grep -Eo 'transferred=[0-9]+B' | awk -F= '{gsub(/B/,"",$2);s+=$2}END{print s+0}')

top_json="$(
  awk '/OK/{m=match($0,/OK[[:space:]]+([^-[:space:]]+-[^[:space:]]+)/,a);
       if(m)db=a[1];m2=match($0,/transferred=([0-9]+)B/,b);if(m2)b=b[1];else b=0;sum[db]+=b;}
       END{for(d in sum)printf "%s %d\n",d,sum[d]}' "$RUN_LOG" |
  sort -k2,2nr | head -n10 |
  awk 'BEGIN{printf("[")} {printf("%s{\"db\":\"%s\",\"bytes\":%d}",(NR>1?",":""),$1,$2)} END{printf("]")}'
)"
cat > "$SUMMARY_JSON" <<JSON
{
  "date_utc":"$DATE_UTC",
  "ok":${ok_today:-0},
  "fail":${fail_today:-0},
  "bytes_sent":${bytes_sent_today:-0},
  "bytes_transferred":${bytes_xfer_today:-0},
  "top_dbs_by_transferred":${top_json:-[]}
}
JSON

if [[ -t 1 ]]; then
  echo "Run summary: ok=$RUN_COUNT_OK fail=$RUN_COUNT_FAIL sent=${RUN_BYTES_SENT}B xfer=${RUN_BYTES_XFER}B"
  echo "Logs: $RUN_LOG"
  echo "Daily summary: $SUMMARY_JSON"
fi
