#!/usr/bin/env bash
# Syncs only DB filestore directories listed in a file to a remote mirror,
# preserving the same path under the filestore root.
#
# Input file: one absolute dir per line, e.g.
# /var/www/html/HEURIST/HEURIST_FILESTORE/mydb1
# /var/www/html/HEURIST/HEURIST_FILESTORE/anotherdb
#
# Exit codes:
#   0: ok (even if there was nothing to sync)
#   1: usage/config error
#   2: input file missing/empty
#   3: another instance is running
#   4: rsync failure (first failure stops script)

set -Eeuo pipefail

# --- Config (override via env or CLI) -----------------------------------------
SRC_ROOT="${SRC_ROOT:-/var/www/html/HEURIST/HEURIST_FILESTORE}"
DEST_HOST="${DEST_HOST:-heuristeu@heurist.huma-num.fr}"
DEST_ROOT="${DEST_ROOT:-/var/www/html/HEURIST/HEURIST_FILESTORE}"

LIST_FILE="${LIST_FILE:-modified_db_dirs.txt}"
SSH_KEY="${SSH_KEY:-/home/heuristeu/.ssh/id_ed25519}"   # adjust to your key path
LOG_DIR="${LOG_DIR:-/var/log/heurist-sync}"
LOCK_FILE="${LOCK_FILE:-/var/run/sync_modified_db_filestores.lock}"

# Tuning
DRY_RUN="${DRY_RUN:-0}"             # set 1 for --dry-run
BWLIMIT_KB="${BWLIMIT_KB:-0}"       # e.g., 0=unlimited, 40960=~40MB/s
RSYNC_EXTRA="${RSYNC_EXTRA:-}"      # extra flags if needed

# --- CLI ----------------------------------------------------------------------
usage() {
  cat <<EOF
Usage: ${0##*/} [-l LIST_FILE] [-s SRC_ROOT] [-d DEST_ROOT] [-h DEST_HOST] [-k SSH_KEY] [--dry-run]

Environment overrides are also supported (see script header).
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    -l) LIST_FILE="$2"; shift 2;;
    -s) SRC_ROOT="$2"; shift 2;;
    -d) DEST_ROOT="$2"; shift 2;;
    -h) DEST_HOST="$2"; shift 2;;
    -k) SSH_KEY="$2"; shift 2;;
    --dry-run) DRY_RUN=1; shift;;
    -\?|--help) usage; exit 0;;
    *) echo "Unknown option: $1" >&2; usage; exit 1;;
  esac
done

# --- Preconditions ------------------------------------------------------------
mkdir -p "$LOG_DIR"

# Prevent concurrent runs
exec 9>"$LOCK_FILE" || { echo "Cannot open lock $LOCK_FILE" >&2; exit 1; }
if ! flock -n 9; then
  echo "Another sync is running (lock: $LOCK_FILE)"; exit 3
fi

timestamp() { date +"%Y-%m-%d %H:%M:%S%z"; }

if [[ ! -d "$SRC_ROOT" ]]; then
  echo "[$(timestamp)] ERROR: SRC_ROOT not found: $SRC_ROOT" >&2
  exit 1
fi

if [[ ! -f "$LIST_FILE" ]]; then
  echo "[$(timestamp)] Nothing to sync: list file not found: $LIST_FILE" >&2
  exit 2
fi

# Trim empty/comment lines to test emptiness
if ! grep -qE '^[[:space:]]*/' "$LIST_FILE"; then
  echo "[$(timestamp)] Nothing to sync: list file has no directories." >&2
  exit 0
fi

# --- Helpers ------------------------------------------------------------------
# Prefer gentle priorities
IONICE=( ); NICE=( )
command -v ionice >/dev/null 2>&1 && IONICE=(ionice -c2 -n7)
command -v nice   >/dev/null 2>&1 && NICE=(nice -n10)

SSH_OPTS=( -i "$SSH_KEY" -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 )
RSYNC_OPTS=(
  -a          # archive: perms, times, symlinks, etc.
  --delete    # mirror deletions within each DB dir
  --numeric-ids
  --partial   # keep partials to avoid re-sending from scratch
  --no-inc-recursive   # lower RAM in huge trees (one pass)
  --compress  # saves bandwidth for many small files
  --human-readable
  --itemize-changes
)

# optional limits
if [[ "${BWLIMIT_KB}" -gt 0 ]]; then
  RSYNC_OPTS+=( --bwlimit="${BWLIMIT_KB}" )
fi
[[ "$DRY_RUN" == "1" ]] && RSYNC_OPTS+=( --dry-run )

# Append any extra flags provided via env
if [[ -n "$RSYNC_EXTRA" ]]; then
  # shellcheck disable=SC2206
  RSYNC_OPTS+=( $RSYNC_EXTRA )
fi

# --- Main loop ----------------------------------------------------------------
rc=0
while IFS= read -r dir || [[ -n "$dir" ]]; do
  # Skip blanks/comments
  [[ -z "${dir// }" ]] && continue
  [[ "$dir" =~ ^# ]] && continue

  # Must be an absolute path under SRC_ROOT
  if [[ "${dir}" != "$SRC_ROOT"* ]]; then
    echo "[$(timestamp)] WARN: Skipping non-filestore path: $dir" >&2
    continue
  fi
  if [[ ! -d "$dir" ]]; then
    echo "[$(timestamp)] WARN: Skipping missing directory: $dir" >&2
    continue
  fi

  # Compute relative DB path and remote target
  rel="${dir#"$SRC_ROOT"/}"          # e.g., "mydb1"
  if [[ "$rel" == "$dir" || -z "$rel" ]]; then
    echo "[$(timestamp)] WARN: Cannot compute relative path for: $dir" >&2
    continue
  fi
  dest="${DEST_HOST}:${DEST_ROOT}/${rel}/"

  log_file="${LOG_DIR}/rsync_${rel//\//_}_$(date +%Y%m%dT%H%M%S).log"
  echo "[$(timestamp)] Syncing ${dir}/  ->  ${dest}"
  {
    echo "# $(timestamp) rsync ${dir}/ -> ${dest}"
    printf '# Options: %s\n' "${RSYNC_OPTS[*]}"
  } >> "$log_file"

  # Run rsync gently
  if ! "${IONICE[@]}" "${NICE[@]}" rsync "${RSYNC_OPTS[@]}" -e "ssh ${SSH_OPTS[*]}" \
        "${dir}/" "${dest}" >>"$log_file" 2>&1; then
    echo "[$(timestamp)] ERROR: rsync failed for ${dir} (see ${log_file})" >&2
    rc=4
    break
  else
    echo "[$(timestamp)] OK: ${rel}" | tee -a "$log_file" >/dev/null
  fi
done < "$LIST_FILE"

exit "$rc"
