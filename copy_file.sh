#!/bin/bash
# Copy a file, or the immediate files in a directory, between Heurist installs.
BASE=/var/www/html/HEURIST

usage() {
    cat >&2 <<'HELP'
Usage: ./copy_file.sh PATH SOURCE_INSTALL TARGET_INSTALL
Example: ./copy_file.sh /hclient/widgets/entity/manageRecords.js h7-alpha heurist
Directory: ./copy_file.sh /hclient/widgets/entity/ h7-alpha heurist
Paths are relative to /var/www/html/HEURIST/<install>.
A leading / is optional. A trailing / copies files only (including hidden files),
without subdirectories. Existing destination files are overwritten.
The destination directory must already exist.
HELP
}
fail() { printf 'Error: %s\n' "$1" >&2; exit 1; }
invalid() { printf 'Error: %s\n\n' "$1" >&2; usage; exit 2; }

[[ $# -eq 3 ]] || { usage; exit 2; }
[[ -n $1 ]] || invalid 'PATH must not be empty.'
for install in "$2" "$3"; do
    [[ $install =~ ^[a-zA-Z0-9_-][a-zA-Z0-9_.-]*$ && $install != . && $install != .. ]] \
        || invalid 'Install names must be directory names such as h7-alpha or heurist.'
done
path=/${1#/}
[[ $path != *//* ]] || invalid 'PATH must not contain repeated slashes.'
case "$path/" in
    */../*|*/./*) invalid 'PATH must not contain . or .. components.' ;;
esac
source_path="$BASE/$2$path"
target_path="$BASE/$3$path"

if [[ $path == */ ]]; then
    [[ -d $source_path ]] || fail "Source directory not found: $source_path"
    [[ -d $target_path ]] || fail "Target directory not found: $target_path"
    shopt -s nullglob dotglob
    count=0
    for file in "$source_path"*; do
        [[ -f $file ]] || continue
        destination="$target_path${file##*/}"
        [[ ! -d $destination ]] || fail "Target is a directory: $destination"
        cp -- "$file" "$destination" || fail "Could not copy: $file"
        ((count+=1))
    done
    printf 'Copied %d file(s) from %s to %s\n' "$count" "$source_path" "$target_path"
else
    [[ -f $source_path ]] || fail "Source file not found: $source_path"
    target_dir=${target_path%/*}
    [[ -d $target_dir ]] || fail "Target directory not found: $target_dir"
    [[ ! -d $target_path ]] || fail "Target is a directory: $target_path"
    cp -- "$source_path" "$target_path" || fail "Could not copy: $source_path"
    printf 'Copied %s to %s\n' "$source_path" "$target_path"
fi
