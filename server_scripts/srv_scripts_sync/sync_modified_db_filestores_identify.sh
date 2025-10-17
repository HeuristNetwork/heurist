#!/usr/bin/env bash
# Reports which DB filestore directories under a parent had any file modified
# within the last N minutes (default: 60). Writes a CSV and a plain-text list.
#
# Example CSV (modified_db_filestores_last_hour.csv):
# database,changed_files
# mydb1,12
# anotherdb,3
#
# Example list (modified_db_dirs.txt):
# /var/www/html/HEURIST/HEURIST_FILESTORE/mydb1
# /var/www/html/HEURIST/HEURIST_FILESTORE/anotherdb

set -Eeuo pipefail

# ---- Config (override via environment or CLI flags) --------------------------
PARENT_DIR="${PARENT_DIR:-/var/www/html/HEURIST/HEURIST_FILESTORE}"
WINDOW_MIN="${WINDOW_MIN:-60}"
OUT_CSV="${OUT_CSV:-modified_db_filestores_last_hour.csv}"
OUT_LIST="${OUT_LIST:-modified_db_dirs.txt}"

# Exclusions (comma-separated globs, matched anywhere in path). Example:
# EXCLUDES="*/thumbnails/*,*/cache/*"
EXCLUDES="${EXCLUDES:-}"

# Niceness (reduce load impact)
NICE_BIN="${NICE_BIN:-/usr/bin/nice}"
IONICE_BIN="${IONICE_BIN:-/usr/bin/ionice}"

usage() {
  cat <<EOF
Usage: ${0##*/} [-p PARENT_DIR] [-w WINDOW_MIN] [-o OUT_CSV] [-l OUT_LIST] [-x EXCLUDES]

Options:
  -p PARENT_DIR   Parent dir to scan (default: $PARENT_DIR)
  -w WINDOW_MIN   Minutes look-back window (default: $WINDOW_MIN)
  -o OUT_CSV      Output CSV file (default: $OUT_CSV)
  -l OUT_LIST     Output list of dirs (default: $OUT_LIST)
  -x EXCLUDES     Comma-separated glob patterns to prune (default: none)

Environment variables with same names also supported.
EOF
}

while getopts ":p:w:o:l:x:h" opt; do
  case "$opt" in
    p) PARENT_DIR="$OPTARG" ;;
    w) WINDOW_MIN="$OPTARG" ;;
    o) OUT_CSV="$OPTARG" ;;
    l) OUT_LIST="$OPTARG" ;;
    x) EXCLUDES="$OPTARG" ;;
    h) usage; exit 0 ;;
    \?) echo "Unknown option: -$OPTARG" >&2; usage; exit 1 ;;
    :)  echo "Option -$OPTARG requires an argument." >&2; usage; exit 1 ;;
  esac
done

# ---- Safety checks -----------------------------------------------------------
if [[ ! -d "$PARENT_DIR" ]]; then
  echo "Error: PARENT_DIR not found: $PARENT_DIR" >&2
  exit 2
fi

# ---- Build find command with optional prunes ---------------------------------
# We want: files newer than WINDOW_MIN minutes anywhere under PARENT_DIR.
# Then collapse paths to their first component (the DB directory name).

# shellcheck disable=SC2206
IFS=',' read -r -a EX_PATTERNS <<< "$EXCLUDES"

build_prunes() {
  # Emit: \( -path 'pat1' -o -path 'pat2' \) -prune -o
  if [[ "${#EX_PATTERNS[@]}" -gt 0 && -n "${EX_PATTERNS[0]// }" ]]; then
    printf '('
    local first=1
    for pat in "${EX_PATTERNS[@]}"; do
      pat="${pat#"${pat%%[![:space:]]*}"}"  # ltrim
      pat="${pat%"${pat##*[![:space:]]}"}"  # rtrim
      [[ -z "$pat" ]] && continue
      if (( first )); then
        first=0
      else
        printf ' -o '
      end
      printf -- "-path %q" "$pat"
    done
    printf ') -prune -o '
  fi
}

# ---- Run (nicely) ------------------------------------------------------------
# Use -mmin to avoid temp files; -printf %P for path relative to parent.
# We only care about files under *subdirectories*, so we later require a slash.

FIND_CMD=(/usr/bin/find "$PARENT_DIR")
PRUNES=$(build_prunes || true)

# Compose the command safely
# shellcheck disable=SC2206
FIND_ARGS=()
if [[ -n "$PRUNES" ]]; then
  # Evaluate the prune expression with bash -c to keep quoting intact
  # We store it in a temp file to avoid eval. But for simplicity and readability,
  # we run find twice: once to get the list then process. Cheap with metadata.
  :
fi

# Prefer reduced CPU & IO priority if available
if [[ -x "$IONICE_BIN" ]]; then IONICE_PREFIX=("$IONICE_BIN" -c2 -n7); else IONICE_PREFIX=(); fi
if [[ -x "$NICE_BIN" ]];   then NICE_PREFIX=("$NICE_BIN" -n 10);     else NICE_PREFIX=();    fi

# Create a temp file to capture results
TMP_CHANGED="$(mktemp)"
trap 'rm -f "$TMP_CHANGED"' EXIT

# Build the find invocation string with optional prunes safely via bash -c
# to keep complex prune grouping intact without eval in the main shell.
bash -c '
  set -Eeuo pipefail
  P="$0"; W="$1"; OUT="$2"; PR="$3"
  # shellcheck disable=SC2086
  if [[ -n "$PR" ]]; then
    # We need grouping: \( ... \) -prune -o -type f ...
    eval /usr/bin/find "\"$P\"" $PR -type f -mmin -"${W}" -printf "%P\0"
  else
    /usr/bin/find "$P" -type f -mmin -"${W}" -printf "%P\0"
  fi > "$OUT"
' "$PARENT_DIR" "$WINDOW_MIN" "$TMP_CHANGED" "$PRUNES"

# If nothing changed, write empty CSV with header and exit
if [[ ! -s "$TMP_CHANGED" ]]; then
  printf "database,changed_files\n" > "$OUT_CSV"
  : > "$OUT_LIST"
  exit 0
fi

# Collapse to first path component (DB dir), NUL-safe pipeline, count per DB
# uniq -zc prints: "<count> <name>\n" (newline-delimited on output)
# We preserve spaces in names by stripping the count with a regex.
AWK_NUL_COLLAPSE='
  BEGIN { RS = "\0"; ORS = "\0"; }
  index($0,"/") { sub(/\/.*/,"",$0); print $0; }
'
tr -d '\n' < /dev/null >/dev/null 2>&1 || true # no-op for shellcheck noise

awk "$AWK_NUL_COLLAPSE" < "$TMP_CHANGED" \
| sort -z \
| uniq -zc \
| sort -nr \
| awk 'BEGIN{print "database,changed_files"} {c=$1; sub(/^[0-9]+\s+/,""); printf "%s,%s\n",$0,c}' \
> "$OUT_CSV"

# Also write a plain list of absolute directories to sync (one per line)
awk -F, 'NR>1 {print "'"$PARENT_DIR"'/" $1}' "$OUT_CSV" > "$OUT_LIST"

# Optional: print a tiny summary when run interactively
if [[ -t 1 ]]; then
  echo "Wrote: $OUT_CSV"
  echo "Dirs:  $OUT_LIST"
fi
