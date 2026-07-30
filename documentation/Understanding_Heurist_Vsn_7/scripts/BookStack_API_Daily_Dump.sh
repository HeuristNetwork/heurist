#!/usr/bin/env bash
set -euo pipefail

# Although possibly redundant, this is a useful duplication jsut in case

BASE_URL="https://docs.heuristref.net"
TOKEN_FILE="/etc/bookstack-api-token"
EXPORT_BASE="/srv/BACKUP/BookStack/API_exports_retain_1_month"
KEEP_DAYS=30

TOKEN="$(cat "$TOKEN_FILE")"
DATE="$(date +%F_%H-%M-%S)"
OUT_DIR="${EXPORT_BASE}/${DATE}"

mkdir -p \
  "$OUT_DIR/books_zip" \
  "$OUT_DIR/books_markdown" \
  "$OUT_DIR/chapters_markdown" \
  "$OUT_DIR/pages_markdown"

safe_name() {
  echo "$1" | tr '/: *?"<>|' '_' | sed 's/__*/_/g' | cut -c1-160
}

api_json() {
  curl -fsS \
    -H "Authorization: Token ${TOKEN}" \
    -H "Accept: application/json" \
    "$1"
}

api_file() {
  curl -fsSL \
    -H "Authorization: Token ${TOKEN}" \
    "$1" \
    -o "$2"
}

echo  ${TOKEN} 

export_list() {
  local type="$1"
  local out_subdir="$2"
  local format="$3"
  local ext="$4"

  local offset=0
  local count=100
  local json total id name filename

  while :; do
    json="$(api_json "${BASE_URL}/api/${type}?count=${count}&offset=${offset}")"
    total="$(echo "$json" | jq -r '.total')"


    echo "$json" | jq -c '.data[]' | while read -r item; do
      id="$(echo "$item" | jq -r '.id')"
      name="$(safe_name "$(echo "$item" | jq -r '.name')")"
      filename="${OUT_DIR}/${out_subdir}/${id}_${name}.${ext}"

      echo "Exporting ${type}/${id} as ${format}"
      api_file "${BASE_URL}/api/${type}/${id}/export/${format}" "$filename"
    done

    offset=$((offset + count))
    [ "$offset" -ge "$total" ] && break
  done
}

# Portable/importable ZIP for each book
export_list "books" "books_zip" "zip" "zip"

# Markdown exports
export_list "books" "books_markdown" "markdown" "md"
export_list "chapters" "chapters_markdown" "markdown" "md"
export_list "pages" "pages_markdown" "markdown" "md"

# create tar zip file from the folders created
# The zip file is <10%  smaller, so not worth creating
# tar -czf "${OUT_DIR}.tar.gz" -C "$EXPORT_BASE" "$DATE"

find "$EXPORT_BASE" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} \;
find "$EXPORT_BASE" -mindepth 1 -maxdepth 1 -type f -name '*.tar.gz' -mtime +"$KEEP_DAYS" -delete

echo "BookStack API export complete: ${OUT_DIR}"
