#!/usr/bin/env bash
# For setting up the remote database's filestore on the local server
#
# Exit codes:
#   0 - success
#   1 - lock held by another instance
#   2 - preflight failure (missing binaries or unreadable config)
#   3 - bad/empty PHP config of remote servers
#   4 - SSH connectivity check failure (when DEBUG_CHECK_SSH=1)
#
set -Eeuo pipefail

# ===================== BASIC CONFIG =========================================
PARENT_DIR="${PARENT_DIR:-/var/www/html/HEURIST/HEURIST_FILESTORE}"
# When you maintain per-identifier parents like /.../HEURIST_FILESTORE_HN, the script
# will automatically try ${PARENT_DIR}_<ID> first, then fall back to PARENT_DIR.
REMOTE_BASE="${REMOTE_BASE:-/var/www/html/HEURIST/HEURIST_FILESTORE}"
HEURIST_PHP_CONF="${HEURIST_PHP_CONF:-/var/www/html/HEURIST/heuristConfigIni.php}"
AWAITING_SYNC="${AWAITING_SYNC:-/var/www/html/HEURIST/HEURIST_FILESTORE/_DBS_AWAITING_SYNC.txt}"

# Check and prepare the list of databases waiting for their filestore
DATABASES=()
if [ ! -s "$AWAITING_SYNC" ]; then
  # log "SKIPPED: No databases awaiting"
  exit 0
else
  IFS=, read -r -a databases < "$AWAITING_SYNC"
  for database in "${databases[@]}"; do

    if [[ -z "$database" ]]; then
      continue
    fi

    IFS=_ read -r -a database_parts <<< "$database"

    # Check that: parts size == 2, that the server's filestore directory already exists, that the database name have been supplied, and that the database doesn't already exist within local filestore
    if [[ ${#database_parts} != 2 ]] || [ ! -d "${PARENT_DIR}_${database_parts[0]}" ] || [[ -z "${database_parts[1]}" ]] || [ -d "${PARENT_DIR}_${database_parts[0]}/${database_parts[1]}" ]; then
      # log "SKIPPING, DB: ${database_parts[0]}_${database_parts[1]}, Parts: ${#database_parts}"
      continue
    fi

    DATABASES+=("$database")
  done
fi

# space- or comma-separated is fine
EXCLUDE_DIRS="${EXCLUDE_DIRS:-scratch backup documentation filethumbs}"
RSYNC_EXCLUDES=()
# exclude those directories at the top level of each DB folder
for d in $EXCLUDE_DIRS; do
  RSYNC_EXCLUDES+=( --exclude="/$d/**" --exclude="/$d" )
done

# Defaults if fields are missing in PHP config
DEFAULT_SSHUSER="${DEFAULT_SSHUSER:-dbtunnel}"
DEFAULT_SSHPORT="${DEFAULT_SSHPORT:-22}"
DEFAULT_SSHKEY="${DEFAULT_SSHKEY:-/home/tunnel/.ssh/dbtunnel_ed25519}"

# Runtime / paths
LOG_DIR="${LOG_DIR:-/var/log/heurist-remote-setup}"
RUN_LOG="${RUN_LOG:-$LOG_DIR/run.log}"
LOCK_FILE="${LOCK_FILE:-/tmp/heurist-remote-setup.lock}"

# Behavior flags
MAX_RETRIES="${MAX_RETRIES:-3}"
DRY_RUN="${DRY_RUN:-0}"                  # 1 -> rsync -n
CONNECT_TIMEOUT="${CONNECT_TIMEOUT:-20}"  # ssh connect timeout (seconds)

PHP_BIN="${PHP:-/usr/bin/php}"
if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  log "ERROR: PHP not found at $PHP_BIN (set PHP=/path/to/php)"
  exit 2
fi

# Unidirectional sync options
# rsync options (no deleting), tuned for unprivileged users
RSYNC_OPTS="${RSYNC_PULL_OPTS:--rltz -O --itemize-changes --stats -u --timeout=60}"

# SSH host key policy (accept-new|yes|no). Default to accept-new on modern OpenSSH.
SSH_STRICT_HOSTKEY="${SSH_STRICT_HOSTKEY:-accept-new}"
# Optional known_hosts isolation for debug checks
TMP_KNOWN_HOSTS="${TMP_KNOWN_HOSTS:-$PWD/tmp/known_hosts}"

# Nice/ionice optional
USE_NICE="${USE_NICE:-0}"
NICE_BIN="${NICE_BIN:-/usr/bin/nice}"
USE_IONICE="${USE_IONICE:-0}"
IONICE_BIN="${IONICE_BIN:-/usr/bin/ionice}"

# ===================== EMAIL NOTIFICATION ===================================
MAIL_TO="ian.johnson@gmail.com,osmakov@gmail.com"
#MAIL_TO="${MAIL_TO:-root}"   # space- or comma-separated list
MAIL_FROM="${MAIL_FROM:-heurist-sync@$(hostname -f 2>/dev/null || echo localhost)}"
MAIL_SUBJECT_PREFIX="${MAIL_SUBJECT_PREFIX:-[Heurist Sync Alert]}"

send_failure_email() {
  local subject="$1" bodyfile="$2"

  # Normalize recipients: split on commas and/or spaces into an array
  local -a rcpts=()
  # shellcheck disable=SC2206  # intentional word-splitting on spaces after comma substitution
  rcpts=(${MAIL_TO//,/ })

  if command -v mailx >/dev/null 2>&1; then
    # s-nail / heirloom-mailx: -r sets the envelope sender
    mailx -r "$MAIL_FROM" -s "$MAIL_SUBJECT_PREFIX $subject" "${rcpts[@]}" <"$bodyfile" || true
  elif command -v mail >/dev/null 2>&1; then
    # s-nail 'mail' also supports -r (envelope sender)
    mail -r "$MAIL_FROM" -s "$MAIL_SUBJECT_PREFIX $subject" "${rcpts[@]}" <"$bodyfile" || true
  else
    # If no mailer is installed, log it (don’t fail the script)
    log "WARN: no 'mailx' or 'mail' found; unable to send alert: $MAIL_SUBJECT_PREFIX $subject"
  fi
}


# ===================== LOGGING & PREFLIGHT ==================================
mkdir -p "$LOG_DIR" "$(dirname "$LOCK_FILE")"
# Create run.log if missing; rotate (truncate) if it reached 100 KiB (102400 bytes)
if [[ -f "$RUN_LOG" ]]; then
  size=$(wc -c < "$RUN_LOG" 2>/dev/null || echo 0)
  if (( size >= 102400 )); then
    : > "$RUN_LOG"
  fi
else
  : > "$RUN_LOG"
fi

timestamp() { date -u +"%Y-%m-%dT%H:%M:%SZ"; }
log() { printf '%s %s\n' "$(timestamp)" "$*" | tee -a "$RUN_LOG" >&2; }

for bin in php rsync ssh; do
  command -v "$bin" >/dev/null 2>&1 || { log "ERROR: $bin not found"; exit 2; }
done

# ===================== LOCKING ==============================================
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  # log "Another instance is running (lock: $LOCK_FILE); exiting."
  exit 1
fi
echo $$ 1>&9

# ===================== RUN COUNTERS =========================================
RUN_COUNT_PULL_OK=0
RUN_COUNT_FAIL=0
RUN_COUNT_SKIP=0
RUN_BYTES_SENT=0
RUN_BYTES_XFER=0
FAILED_DIRS=()

backoff_for_attempt() {
  local attempt="${1:-1}"
  local sleep_for=$(( 1 << (attempt-1) ))
  (( sleep_for > 30 )) && sleep_for=30
  log "Retrying in ${sleep_for}s..."
  sleep "$sleep_for"
}

parse_rsync_stats() {
  local stats="$1" line sent xfer
  line="$(printf '%s\n' "$stats" | grep -E '^sent [0-9,]+ bytes' || true)"
  if [[ -n "$line" ]]; then
    sent="$(sed -E 's/^sent ([0-9,]+) bytes.*/\1/' <<<"$line" | tr -d ',')"
    [[ -n "$sent" ]] && RUN_BYTES_SENT=$(( RUN_BYTES_SENT + sent ))
  fi
  line="$(printf '%s\n' "$stats" | grep -E '^Total transferred file size:' || true)"
  if [[ -n "$line" ]]; then
    xfer="$(sed -E 's/^Total transferred file size: *([0-9,]+).*/\1/' <<<"$line" | tr -d ',')"
    [[ -n "$xfer" ]] && RUN_BYTES_XFER=$(( RUN_BYTES_XFER + xfer ))
  fi
}

# ===================== PARSE PHP CONFIG =====================================
if [[ ! -r "$HEURIST_PHP_CONF" ]]; then
  log "ERROR: cannot read $HEURIST_PHP_CONF"; exit 2
fi

# Expect: $remoteServers = ['ID' => ['server'=>'host','sshUser'=>'...','sshPort'=>22,'sshKey'=>'...'], ...];
PHP_TSV="$(
  "$PHP_BIN" -r '
    error_reporting(E_ERROR);
    $f = $argv[1];
    if (!$f || !file_exists($f)) { fwrite(STDERR, "missing conf\n"); exit(1); }
    chdir(dirname($f));
    require $f;
    if (!isset($remoteServers) || !is_array($remoteServers) || !count($remoteServers)) { exit(0); }
    foreach ($remoteServers as $id => $cfg) {
      $server = $cfg["server"] ?? "";
      $user   = $cfg["sshUser"] ?? "";
      $port   = $cfg["sshPort"] ?? "";
      $key    = $cfg["sshKey"]  ?? "";
      echo $id."\t".$server."\t".$user."\t".$port."\t".$key."\n";
    }
  ' "$HEURIST_PHP_CONF" 2>&1
)"
php_rc=$?

# If the PHP one-liner failed or produced nothing, log what came back
if (( php_rc != 0 )) || [[ -z "$PHP_TSV" ]]; then
  log "ERROR: failed to parse remote servers from $HEURIST_PHP_CONF (rc=$php_rc). Output:"
  printf '%s\n' "$PHP_TSV" | sed 's/^/PHP> /' >&2
  exit 3
fi


declare -A RS_SERVER RS_SSHUSER RS_SSHPORT RS_SSHKEY
IDENTIFIERS=()
while IFS=$'\t' read -r IDENT SERVER SSHUSER SSHPORT SSHKEY; do
  [[ -z "${IDENT:-}" ]] && continue
  IDENTIFIERS+=("$IDENT")
  RS_SERVER["$IDENT"]="$SERVER"
  RS_SSHUSER["$IDENT"]="${SSHUSER:-$DEFAULT_SSHUSER}"
  RS_SSHPORT["$IDENT"]="${SSHPORT:-$DEFAULT_SSHPORT}"
  RS_SSHKEY["$IDENT"]="${SSHKEY:-$DEFAULT_SSHKEY}"
done <<<"$PHP_TSV"

if ((${#IDENTIFIERS[@]}==0)); then
  log "ERROR: No identifiers parsed from PHP config"; exit 3
fi

# ===================== OPTIONAL DEBUG CHECKS =================================
DEBUG_EXIT_AFTER_CONFIG="${DEBUG_EXIT_AFTER_CONFIG:-0}"
DEBUG_CHECK_SSH="${DEBUG_CHECK_SSH:-0}"

if [[ "$DEBUG_EXIT_AFTER_CONFIG" == "1" ]]; then
  log "DEBUG: config parsed; exiting early"
  declare -p IDENTIFIERS RS_SERVER RS_SSHUSER RS_SSHPORT RS_SSHKEY 2>/dev/null || true
  exit 0
fi

if [[ "$DEBUG_CHECK_SSH" == "1" ]]; then
  log "DEBUG: checking SSH connectivity..."
  mkdir -p "$(dirname "$TMP_KNOWN_HOSTS")"
  failures=0
  for id in "${IDENTIFIERS[@]}"; do
    host="${RS_SERVER[$id]}" user="${RS_SSHUSER[$id]}" port="${RS_SSHPORT[$id]}" key="${RS_SSHKEY[$id]}"
    [[ -z "$host" || -z "$user" || -z "$port" ]] && { log "[$id] missing host/user/port"; failures=$((failures+1)); continue; }
    [[ -n "${key:-}" && ! -r "$key" ]] && { log "[$id] SSH key not readable: $key"; failures=$((failures+1)); continue; }
    ssh_cmd=(ssh -o BatchMode=yes -o ConnectTimeout="$CONNECT_TIMEOUT" -o StrictHostKeyChecking="$SSH_STRICT_HOSTKEY" -o UserKnownHostsFile="$TMP_KNOWN_HOSTS" -p "$port")
    [[ -n "${key:-}" ]] && ssh_cmd+=(-i "$key")
    if "${ssh_cmd[@]}" "${user}@${host}" 'printf ok' >/dev/null 2>&1; then
      log "[$id] SSH OK  ($user@$host:$port)"
    else
      log "[$id] SSH FAILED ($user@$host:$port)"
      "${ssh_cmd[@]}" -vvv "${user}@${host}" 'true' || true
      failures=$((failures+1))
    fi
  done
  (( failures > 0 )) && exit 4
  log "DEBUG: all SSH targets reachable. Exiting."
  exit 0
fi

# ===================== HELPERS ==============================================
do_rsync() {
  local direction="$1" src="$2" dst="$3" logf="$4" ssh_cmd_q opts rsync_output rc
  ssh_cmd_q="$(printf '%q ' "${ssh_cmd[@]}")"

  # Base options (from your env-configured sets)
  opts="$RSYNC_PULL_OPTS";

  # Ensure local files end up apache:heurist when *pulling* (requires root locally)
  opts="$opts --chown=apache:heurist"

  # Dry-run passthrough
  [[ "$DRY_RUN" == "1" ]] && opts="$opts -n"

  rsync_output="$( "${rsync_prefix[@]}" rsync $opts "${RSYNC_EXCLUDES[@]}" -e "$ssh_cmd_q" "$src" "$dst" 2>&1 | tee -a "$logf" )"
  rc=$?
  if (( rc == 0 )); then
    parse_rsync_stats "$rsync_output"
  fi
  return "$rc"
}

# ===================== MAIN SYNC ============================================
RSYNC_DRY=""; [[ "$DRY_RUN" == "1" ]] && RSYNC_DRY="-n"

shopt -s nullglob

database_done=()
for database in "${DATABASES[@]}"; do
  
  IFS=_ read -r -a database_pieces <<< "$database"

  id="${database_pieces[0]}"
  dest_db="${database_pieces[1]}"
  dir="${PARENT_DIR}_${id}/${dest_db}"
  mkdir -p "$dir"
  chown apache:heurist "$dir"

  host="${RS_SERVER[${id}]}" user="${RS_SSHUSER[${id}]}" port="${RS_SSHPORT[${id}]}" key="${RS_SSHKEY[${id}]}"
  [[ -z "$host" || -z "$user" || -z "$port" ]] && { log "[${database}] ERROR: missing host/user/port; skipping id."; RUN_COUNT_SKIP=$((RUN_COUNT_SKIP+1)); continue; }
  [[ -n "${key:-}" && ! -r "$key" ]] && { log "[${database}] ERROR: SSH key not readable: $key; skipping id."; RUN_COUNT_SKIP=$((RUN_COUNT_SKIP+1)); continue; }

  ssh_cmd=(ssh -o BatchMode=yes -o ConnectTimeout="$CONNECT_TIMEOUT" -o StrictHostKeyChecking="$SSH_STRICT_HOSTKEY" -p "$port")
  [[ -n "${key:-}" ]] && ssh_cmd+=(-i "$key")

  # Resolve local parent for this $id. Policy: require an $id-suffixed folder to exist.
  varname="PARENT_DIR_${id}"
  explicit_parent="${!varname-}"   # may be empty/unset under `set -u`
  auto_parent=""

  # Only form auto_parent if PARENT_DIR is set
  if [[ -n "${PARENT_DIR-}" ]]; then
    auto_parent="${PARENT_DIR}_${id}"
  fi

  if [[ -n "$explicit_parent" && -d "$explicit_parent" && -r "$explicit_parent" && -x "$explicit_parent" ]]; then
    local_parent="$explicit_parent"
  elif [[ -n "$auto_parent" && -d "$auto_parent" && -r "$auto_parent" && -x "$auto_parent" ]]; then
    local_parent="$auto_parent"
  else
    # Log all candidates we expected; skip this id
    if [[ -n "$explicit_parent" ]]; then
      log "[$database] local parent missing/unreadable: '$explicit_parent'"
    fi
    if [[ -n "$auto_parent" ]]; then
      log "[$database] local parent missing/unreadable: '$auto_parent'"
    else
      log "[$database] no id-suffixed local parent defined (PARENT_DIR or PARENT_DIR_$id)"
    fi
    RUN_COUNT_SKIP=$((RUN_COUNT_SKIP+1))
    continue
  fi
  
  if [[ ! -d "$local_parent" ]]; then log "[$database] WARN: local parent '$local_parent' does not exist; skipping id."; RUN_COUNT_SKIP=$((RUN_COUNT_SKIP+1)); continue; fi

  #Pre-open a control connection per $id
  control_path="/tmp/ssh_setup_fs_${database}"

  ssh_base=(ssh
  -o BatchMode=yes
  -o ConnectTimeout="${CONNECT_TIMEOUT:-20}"
  -o StrictHostKeyChecking="${SSH_STRICT_HOSTKEY:-accept-new}"
  -o ControlMaster=auto
  -o ControlPersist=60s
  -o ControlPath="$control_path"
  -o ServerAliveInterval=30
  -o ServerAliveCountMax=2
  -p "$port"
  )
  [[ -n "${key:-}" ]] && ssh_base+=(-i "$key")

  # Pre-open the master connection (background, ignore if already up)
  "${ssh_base[@]}" -N -f "${user}@${host}" 2>/dev/null || true

  # Use this for all SSH/rsync calls below
  ssh_cmd=("${ssh_base[@]}")

  remote_path="$REMOTE_BASE/$dest_db"

  log "[$database] local parent: $local_parent"
  log "[$database] remote base : $remote_path (on $user@$host:$port)"

  logf="$LOG_DIR/${database}.log"
  # truncate per-DB log at the start of each run
  : > "$logf"

  log "[$database] START"
  echo "$(timestamp) [$database] ==== START ====" >>"$logf"

  # --- require existing, accessible remote path; do NOT create it ---
  # Exists?
  if ! "${ssh_cmd[@]}" "${user}@${host}" "test -d \"$remote_path\""; then
    rc=$?
    if (( rc == 1 )); then
      log "[$database] SKIP: remote path missing: $remote_path"
      RUN_COUNT_SKIP=$((RUN_COUNT_SKIP+1))
      echo "$(timestamp) [$database] ==== END ====" >>"$logf"
      continue
    else
      log "[$database] WARN: SSH check failed (rc=$rc); proceeding to rsync with retries"
    fi
  fi

  # Read/traverse?
  if ! "${ssh_cmd[@]}" "${user}@${host}" "test -r \"$remote_path\" -a -x \"$remote_path\""; then
    rc=$?
    if (( rc == 1 )); then
      log "[$database] SKIP: remote path not readable/traversable: $remote_path"
      RUN_COUNT_SKIP=$((RUN_COUNT_SKIP+1))
      echo "$(timestamp) [$database] ==== END ====" >>"$logf"
      continue
    else
      log "[$database] WARN: SSH rx-check failed (rc=$rc); proceeding to rsync with retries"
    fi
  fi

  # Build src/dst pairs
  pull_src="${user}@${host}:$remote_path/"
  pull_dst="$dir"

  rsync_prefix=()
  [[ "$USE_IONICE" == "1" && -x "$IONICE_BIN" ]] && rsync_prefix+=("$IONICE_BIN" -c2 -n7)
  [[ "$USE_NICE"   == "1" && -x "$NICE_BIN"   ]] && rsync_prefix+=("$NICE_BIN" -n 19)

  # -------- PULL (remote -> local) --------
  attempt=1; rc_pull=1
  log "[$database] PULL start"
  while (( attempt <= MAX_RETRIES )); do
    if do_rsync "pull" "$pull_src" "$pull_dst" "$logf"; then
      log "[$database] PULL ok (attempt $attempt)"
      database_done+=("$database")
      RUN_COUNT_PULL_OK=$((RUN_COUNT_PULL_OK+1)); rc_pull=0; break
    else
      rc=$?
      log "[$database] PULL fail rc=$rc (attempt $attempt/$MAX_RETRIES)"
      (( attempt < MAX_RETRIES )) && backoff_for_attempt "$attempt"
      attempt=$((attempt+1))
    fi
  done

  if (( rc_pull != 0 )); then
    RUN_COUNT_FAIL=$((RUN_COUNT_FAIL+1)); FAILED_DIRS+=("${id}/${dest_db}")
  fi

  echo "$(timestamp) [$database] ==== END ====" >>"$logf"
  sleep 1
done
shopt -u nullglob

# ===================== SUMMARY ==============================================
log "SUMMARY: PULL_OK=$RUN_COUNT_PULL_OK FAIL=$RUN_COUNT_FAIL SKIP=$RUN_COUNT_SKIP SENT_BYTES=$RUN_BYTES_SENT XFER_BYTES=$RUN_BYTES_XFER"
if (( RUN_COUNT_FAIL > 0 )); then
  log "FAILED DIRS: ${FAILED_DIRS[*]:-none}"
  tmpmsg="$(mktemp)"
  {
    echo "Heurist rsync run on $(hostname -f 2>/dev/null || echo localhost) @ $(date -u)"
    echo "PULL_OK=$RUN_COUNT_PULL_OK FAIL=$RUN_COUNT_FAIL SKIP=$RUN_COUNT_SKIP"
    echo "SENT_BYTES=$RUN_BYTES_SENT XFER_BYTES=$RUN_BYTES_XFER"
    echo
    echo "FAILED DIRS:"
    for d in "${FAILED_DIRS[@]:-}"; do echo "  - $d"; done
  } >"$tmpmsg"
  send_failure_email "Failures on $(hostname -f 2>/dev/null || echo localhost)" "$tmpmsg" || true
  rm -f "$tmpmsg"
fi

# Update waiting list
IFS=, read -r -a CUR_WAITING_LIST < "$AWAITING_SYNC"
NEW_WAITING_LIST=()
for waiting_db in "${CUR_WAITING_LIST[@]}"; do

  if [[ -z "$database" ]]; then
    continue
  fi

  found=0

  for done_db in "${database_done[@]}"; do
    if [[ "$waiting_db" == "$done_db" ]]; then
      found=1
      break
    fi
  done

  if [[ "$found" -eq 0 ]]; then
    NEW_WAITING_LIST+=("$waiting_db")
  fi
done
(IFS=,; echo "${my_array[*]},") > "$AWAITING_SYNC"

exit 0
