#!/usr/bin/env bash
# Reports which DB filestore directories (matching ^[A-Z0-9]{1,6}-[^-]+$)
# under a parent had any file modified within the last N minutes (default: 60).
# Writes a CSV (with newest mtime per DB) and a plain-text list for rsync.

set -Eeuo pipefail

# ---- Config (override via env or CLI) ---------------------------------------
PARENT_DIR="${PARENT_DIR:-/var/www/html/HEURIST/HEURIST_FILESTORE}"
WINDOW_MIN="${WINDOW_MIN:-60}"
OUT_CSV="${OUT_CSV:-modified_db_filestores_last_hour.csv}"
OUT_LIST="${OUT_LIST:-modified_db_dirs.txt}"

# Exclusions (comma-separated globs). Example: "*/thumbnails/*,*/cache/*"
EXCLUDES="${EXCLUDES:-}"

# Identifier rule: 1–6 uppercase letters or digits + '-' + DB name w/o '-'
IDENT_REGEX="${IDENT_REGEX:-^[A-Z0-9]{1,6}-[^-]+$}"

NICE_BIN="${NICE_BIN:-/usr/bin/nice}"
IONICE_BIN="${IONICE_BIN:-/usr/bin/ionice}"

usage() {
  cat <<EOF
Usage: ${0##*/} [-p PARENT_DIR] [-w WINDOW_MIN] [-o OUT_CSV] [-l OUT_LIST] [-x EXCLUDES] [-r IDENT_REGEX]

Options:
  -p PARENT_DIR   Parent dir to scan (default: $PARENT_DIR)
  -w WINDOW_MIN   Minutes look-back window (default: $WINDOW_MIN)
  -o OUT_CSV      Output CSV (default: $OUT_CSV)
  -l OUT_LIST     Output list of dirs (default: $OUT_LIST)
  -x EXCLUDES     Comma-separated glob patterns to prune (default: none)
  -r IDENT_REGEX  ERE for top-level dir match (default: $IDENT_REGEX)

CSV columns:
  database,changed_files,newest_mtime_epoch,newest_mtime_utc
EOF
}

while getopts ":p:w:o:l:x:r:h" opt; do
  case "$opt" in
    p) PARENT_DIR="$OPTARG" ;;
    w) WINDOW_MIN="$OPTARG" ;;
    o) OUT_CSV="$OPTARG" ;;
    l) OUT_LIST="$OPTARG" ;;
    x) EXCLUDES="$OPTARG" ;;
    r) IDENT_REGEX="$OPTARG" ;;
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

# ---- Build prune expression --------------------------------------------------
IFS=',' read -r -a EX_PATTERNS <<< "$EXCLUDES"
build_prunes() {
  if [[ "${#EX_PATTERNS[@]}" -gt 0 && -n "${EX_PATTERNS[0]// }" ]]; then
    printf '('
    local first=1
    for pat in "${EX_PATTERNS[@]}"; do
      pat="${pat#"${pat%%[![:space:]]*}"}"
      pat="${pat%"${pat##*[![:space:]]}"}"
      [[ -z "$pat" ]] && continue
      if (( first )); then first=0; else printf ' -o '; fi
      printf -- "-path %q" "$pat"
    done
    printf ') -prune -o '
  fi
}

PRUNES=$(build_prunes || true)

# ---- Nice/ionice -------------------------------------------------------------
IONICE_PREFIX=(); NICE_PREFIX=()
[[ -x "$IONICE_BIN" ]] && IONICE_PREFIX=("$IONICE_BIN" -c2 -n7)
[[ -x "$NICE_BIN"   ]] && NICE_PREFIX=("$NICE_BIN" -n10)

TMP_CHANGED="$(mktemp)"
TMP_BASE="$(mktemp)"   # db,count,max_epoch (no header)
trap 'rm -f "$TMP_CHANGED" "$TMP_BASE"' EXIT

# ---- Find newer files (last WINDOW_MIN minutes), with mtime + relpath -------
# %T@ = epoch (float seconds) of file mtime; %P = path relative to PARENT_DIR
# NUL-delimit for safety.
bash -c '
  set -Eeuo pipefail
  P="$0"; W="$1"; OUT="$2"; PR="$3"
  if [[ -n "$PR" ]]; then
    eval /usr/bin/find "\"$P\"" $PR -type f -mmin -"${W}" -printf "%T@ %P\0"
  else
    /usr/bin/find "$P" -type f -mmin -"${W}" -printf "%T@ %P\0"
  fi > "$OUT"
' "$PARENT_DIR" "$WINDOW_MIN" "$TMP_CHANGED" "$PRUNES"

# Nothing changed
if [[ ! -s "$TMP_CHANGED" ]]; then
  printf "database,changed_files,newest_mtime_epoch,newest_mtime_utc\n" > "$OUT_CSV"
  : > "$OUT_LIST"
  exit 0
fi

# ---- Collapse to first component, filter by IDENT_REGEX, count & max mtime ---
AWK_AGG='
  BEGIN { RS = "\0"; ORS = "\n"; }
  {
    # Record is: "<epoch> <relpath>"
    space = index($0, " ")
    if (!space) next
    t = substr($0, 1, space-1) + 0
    p = substr($0, space+1)
    db = p
    sub(/\/.*/, "", db)           # first path component
    if (db ~ re) {
      cnt[db]++
      if (!(db in max) || t > max[db]) max[db]=t
    }
  }
  END {
    for (d in cnt) printf "%s,%d,%.3f\n", d, cnt[d], max[d]
  }
'

awk -v re="$IDENT_REGEX" "$AWK_AGG" < "$TMP_CHANGED" \
  | sort -t, -k3,3nr > "$TMP_BASE"   # sort by newest mtime desc

# ---- Build final CSV with UTC ISO time --------------------------------------
printf "database,changed_files,newest_mtime_epoch,newest_mtime_utc\n" > "$OUT_CSV"
while IFS=, read -r db cnt epoch; do
  # Trim fractional seconds for date conversion; keep epoch with decimals in CSV
  epoch_int="${epoch%.*}"
  iso_utc="$(date -u -d "@$epoch_int" +"%Y-%m-%dT%H:%M:%SZ")"
  printf "%s,%s,%s,%s\n" "$db" "$cnt" "$epoch" "$iso_utc"
done < "$TMP_BASE" >> "$OUT_CSV"

# ---- Plain list of absolute dirs to sync ------------------------------------
awk -F, 'NF>=3 {print "'"$PARENT_DIR"'/" $1}' "$TMP_BASE" > "$OUT_LIST"

if [[ -t 1 ]]; then
  echo "Wrote: $OUT_CSV"
  echo "Dirs:  $OUT_LIST"
fi
