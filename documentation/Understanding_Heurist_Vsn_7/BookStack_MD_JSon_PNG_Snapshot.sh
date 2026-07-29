#!/usr/bin/env bash
set -euo pipefail

# Written by ChatGPT & Ian Johnson 3 July 2026

# This version outputs:
#    the MD (a single file containing the whole book), 
#    a JSon file which also represents all the data, 
#    image files in a files dirctory
# The snapshot does not have file naming with date to allow syncing with gitHub. 

# Note that the JSon file which contains the complete textual data has a date of export near the end, 
# so it will change every day. That does not seem to be the case for the MD file or image files.


BASE_URL="https://docs.heuristref.net"
TOKEN_FILE="/etc/bookstack-api-token"
EXPORT_BASE="/srv/BACKUP/BookStack"
OUT_DIR="${EXPORT_BASE}/daily_MD_JSon_files_snapshot"

TOKEN="$(cat "$TOKEN_FILE")"

# Old codeTO BE DELETED, removes preeding work rm -rf "$OUT_DIR/"
# mkdir -p "$OUT_DIR"

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

export_book() {
  local book_id="$1"
  local book_name="$2"
  local book_dir="$3"
  local zipfile="$book_dir/portable_export.zip"

  echo "Exporting book: $book_name"

  mkdir -p "$book_dir"

  # 1. Portable ZIP: contains book.json, files/, pages/, etc.
  api_file "${BASE_URL}/api/books/${book_id}/export/zip" "$zipfile"
  unzip -q "$zipfile" -d "$book_dir"
  rm -f "$zipfile"

  # 2. Whole-book Markdown
  echo "Exporting whole book markdown"
  api_file "${BASE_URL}/api/books/${book_id}/export/markdown" \
    "$book_dir/${book_id}_${book_name}.book.md"

  # 3. Chapters belonging to this book
  echo "Exporting chapters"
  chapters_json="$(api_json "${BASE_URL}/api/chapters?count=500")"

  echo "$chapters_json" | jq -c --argjson book_id "$book_id" \
    '.data[] | select(.book_id == $book_id)' | while read -r chapter; do

      chapter_id="$(echo "$chapter" | jq -r '.id')"
      chapter_name="$(safe_name "$(echo "$chapter" | jq -r '.name')")"

      api_file "${BASE_URL}/api/chapters/${chapter_id}/export/markdown" \
        "$book_dir/${chapter_id}_${chapter_name}.chapter.md"
  done

  # 4. Pages belonging to this book
  echo "Exporting pages"
  pages_json="$(api_json "${BASE_URL}/api/pages?count=1000")"

  echo "$pages_json" | jq -c --argjson book_id "$book_id" \
    '.data[] | select(.book_id == $book_id)' | while read -r page; do

      page_id="$(echo "$page" | jq -r '.id')"
      page_name="$(safe_name "$(echo "$page" | jq -r '.name')")"

      api_file "${BASE_URL}/api/pages/${page_id}/export/markdown" \
        "$book_dir/${page_id}_${page_name}.page.md"
  done
}

# Main loop: one directory per book
offset=0
count=100

while :; do
  echo "Books loop start"

  books_json="$(api_json "${BASE_URL}/api/books?count=${count}&offset=${offset}")"
  total="$(echo "$books_json" | jq -r '.total')"

  echo "$books_json" | jq -c '.data[]' | while read -r book; do
    book_id="$(echo "$book" | jq -r '.id')"
    book_name="$(safe_name "$(echo "$book" | jq -r '.name')")"
    book_dir="$OUT_DIR/${book_id}_${book_name}"  

    export_book "$book_id" "$book_name" "$book_dir"
  
  done

  offset=$((offset + count))
  [ "$offset" -ge "$total" ] && break
  
done

echo "BookStack API export complete: $OUT_DIR"
