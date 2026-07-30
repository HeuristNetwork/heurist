#!/usr/bin/env bash
set -euo pipefail

# Written by ChatGPT & Ian Johnson 3 July 2026, revised with image renaming to stable value 29 July 2026

# This version outputs:
#    the MD (a single file containing the whole book), 
#    a JSon file which also represents all the data, 
#    image files in a files dirctory
# The snapshot does not have file naming with date to allow syncing with gitHub. 

# Note that the JSon file which contains the complete textual data has a date of export near the end, 
# so it will change every day. That does not seem to be the case for the MD file or image files.
#
# BookStack gives the files in each portable export new random names. To prevent
# GitHub seeing unchanged images as new files each day, this script renames each
# image using its stable BookStack image ID and updates the matching references
# in data.json. The export therefore remains usable/importable.
#
# Each book is first built in a temporary directory and then copied into the
# snapshot with rsync --delete. This prevents files from previous snapshots,
# including files whose page/chapter names have changed, accumulating.


BASE_URL="https://docs.heuristref.net"
TOKEN_FILE="/etc/bookstack-api-token"
EXPORT_BASE="/srv/BACKUP/BookStack"
OUT_DIR="${EXPORT_BASE}/daily_MD_JSon_files_snapshot"

TOKEN="$(cat "$TOKEN_FILE")"

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


# Bookstack creates new random image file names every export. This function ensures that the consistent 
# internal names are  used so gitHub is not filled with daily additions of images that are already in the repo.

normalise_export_images() {
  local export_dir="$1"
  local json="$export_dir/data.json"
  local id old extension new temporary_json
  local cover cover_extension cover_hash new_cover

  [ -f "$json" ] || return 0

  # The image ID is stable between exports, whereas the filename generated
  # inside each portable ZIP is not.
  jq -r '
    .. | objects
    | select(
        (.type == "gallery" or .type == "drawio")
        and (.id != null)
        and (.file != null)
      )
    | [.id, .file]
    | @tsv
  ' "$json" |
  while IFS=$'\t' read -r id old; do
    [ -f "$export_dir/files/$old" ] || continue

    extension="${old##*.}"
    new="image-${id}.${extension}"

    [ "$old" = "$new" ] && continue

    mv -f "$export_dir/files/$old" \
          "$export_dir/files/$new"

    temporary_json="${json}.tmp"

    # Update every JSON file reference to the renamed image. References in
    # page content such as [[bsexport:image:123]] already use the stable ID.
    jq --arg old "$old" --arg new "$new" '
      walk(
        if type == "object" and .file? == $old
        then .file = $new
        else .
        end
      )
    ' "$json" > "$temporary_json"

    mv "$temporary_json" "$json"
  done

  # Give the book cover a stable, content-based filename. BookStack does not
  # provide a persistent image ID for the cover.
  cover="$(jq -r '.book.cover // empty' "$json")"

  if [ -n "$cover" ] && [ -f "$export_dir/files/$cover" ]; then
    cover_extension="${cover##*.}"
    cover_hash="$(sha256sum "$export_dir/files/$cover" | cut -c1-16)"
    new_cover="cover-${cover_hash}.${cover_extension}"

    if [ "$cover" != "$new_cover" ]; then
      mv -f "$export_dir/files/$cover" \
            "$export_dir/files/$new_cover"

      temporary_json="${json}.tmp"

      jq --arg new_cover "$new_cover" '
        .book.cover = $new_cover
      ' "$json" > "$temporary_json"

      mv "$temporary_json" "$json"
    fi
  fi

  # The export date changes on every run but is not needed for importing.
  temporary_json="${json}.tmp"

  jq 'del(.exported_at)' "$json" > "$temporary_json"

  mv "$temporary_json" "$json"
}

export_book() {
  local book_id="$1"
  local book_name="$2"
  local book_dir="$3"
  local work_dir
  local zipfile

  work_dir="$(mktemp -d)"
  zipfile="$work_dir/portable_export.zip"

  echo "Exporting book: $book_name"

  mkdir -p "$work_dir/export"

  # 1. Portable ZIP: contains book.json, files/, pages/, etc.
  api_file "${BASE_URL}/api/books/${book_id}/export/zip" "$zipfile"
  unzip -q "$zipfile" -d "$work_dir/export"
  rm -f "$zipfile"

  normalise_export_images "$work_dir/export"

  # 2. Whole-book Markdown
  echo "Exporting whole book markdown"
  api_file "${BASE_URL}/api/books/${book_id}/export/markdown" \
    "$work_dir/export/${book_id}_${book_name}.book.md"

  # 3. Chapters belonging to this book
  echo "Exporting chapters"
  chapters_json="$(api_json "${BASE_URL}/api/chapters?count=500")"

  echo "$chapters_json" | jq -c --argjson book_id "$book_id" \
    '.data[] | select(.book_id == $book_id)' | while read -r chapter; do

      chapter_id="$(echo "$chapter" | jq -r '.id')"
      chapter_name="$(safe_name "$(echo "$chapter" | jq -r '.name')")"

      api_file "${BASE_URL}/api/chapters/${chapter_id}/export/markdown" \
        "$work_dir/export/${chapter_id}_${chapter_name}.chapter.md"
  done

  # 4. Pages belonging to this book
  echo "Exporting pages"
  pages_json="$(api_json "${BASE_URL}/api/pages?count=1000")"

  echo "$pages_json" | jq -c --argjson book_id "$book_id" \
    '.data[] | select(.book_id == $book_id)' | while read -r page; do

      page_id="$(echo "$page" | jq -r '.id')"
      page_name="$(safe_name "$(echo "$page" | jq -r '.name')")"

      api_file "${BASE_URL}/api/pages/${page_id}/export/markdown" \
        "$work_dir/export/${page_id}_${page_name}.page.md"
  done

  # Replace the previous snapshot only after the new export is complete.
  mkdir -p "$book_dir"
  rsync -a --delete "$work_dir/export/" "$book_dir/"

  rm -rf "$work_dir"
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
