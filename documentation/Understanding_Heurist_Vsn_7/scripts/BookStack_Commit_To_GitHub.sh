#!/usr/bin/env bash
set -euo pipefail

# Run nightly
# This script copies the current MD_JSon_Files snapshot directory to the gitHub staging area
# There should be no differences except file data
# then commits and pushes the content to  the Heurist gitHub


REPO="/srv/BACKUP/BookStack_github_staging/heurist"
SOURCE="/srv/BACKUP/BookStack/daily_MD_JSon_files_snapshot/7_Understanding_Heurist_2026_Vsn_7/"
DESTINATION="$REPO/documentation/Understanding_Heurist_Vsn_7/"


# Only transfers changed files to the staging area
rsync -a --delete "$SOURCE" "$DESTINATION"

cd "$REPO"

git add documentation/Understanding_Heurist_Vsn_7

# Commit only if there are changes
if ! git diff --cached --quiet; then
    git commit -m "Understanding Heurist (BookStack)"
fi

git pull --rebase origin h7dev

git push origin h7dev

