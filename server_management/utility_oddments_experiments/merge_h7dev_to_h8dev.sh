#!/usr/bin/env bash
set -euo pipefail

# For use on HeuristRef.net only
# Merges changes in h7dev into a development branch h8dev
# Fails and emails server managers if there are merge conflicts (successful merge does not mean the code is coherent ...)
# ChatGPT 21 april 2026

REPO_DIR="/var/www/html/HEURIST/h8-alpha"
LOG_PREFIX="[heurist-h8-merge]"
OWNER="osmakov"
GROUP="heurist"
REMOTE="origin"
SOURCE_BRANCH="h7dev"
TARGET_BRANCH="h8dev"

echo "$LOG_PREFIX Starting nightly merge of $SOURCE_BRANCH into $TARGET_BRANCH..."

# This must be a dedicated clean clone used only for this automation.
if [ ! -d "$REPO_DIR/.git" ]; then
  echo "$LOG_PREFIX ERROR: $REPO_DIR is not a git repository" >&2
  exit 1
fi

cd "$REPO_DIR"

# Get latest remote branch state.
git fetch --prune "$REMOTE"

# Reset local source branch to match remote exactly.
git checkout "$SOURCE_BRANCH"
git reset --hard "$REMOTE/$SOURCE_BRANCH"

# Reset local target branch to match remote exactly.
git checkout "$TARGET_BRANCH"
git reset --hard "$REMOTE/$TARGET_BRANCH"

# Try the merge, but stop before committing so we can detect problems cleanly.
if ! git merge --no-ff --no-commit "$SOURCE_BRANCH"; then
  echo "$LOG_PREFIX ERROR: merge conflict while merging $SOURCE_BRANCH into $TARGET_BRANCH" >&2
  echo "$LOG_PREFIX Conflicted files:" >&2
  git diff --name-only --diff-filter=U >&2 || true
  git status >&2 || true
  git merge --abort || true
  exit 1
fi

# Extra safety: fail if conflict markers somehow remain in files.
TMP_MARKERS="$(mktemp)"
if grep -R -n -E '^(<<<<<<<|=======|>>>>>>>)' . \
    --exclude-dir=.git \
    --exclude='*.min.js' \
    --exclude='*.min.css' \
    > "$TMP_MARKERS"; then
  echo "$LOG_PREFIX ERROR: conflict markers found after merge" >&2
  cat "$TMP_MARKERS" >&2
  rm -f "$TMP_MARKERS"
  git merge --abort || true
  exit 1
fi
rm -f "$TMP_MARKERS"

# Show the staged merge summary in the log.
echo "$LOG_PREFIX Merge staged successfully. Changed files:"
git diff --stat --cached

# Commit the merge only after all checks passed.
git commit -m "Nightly merge $SOURCE_BRANCH into $TARGET_BRANCH"

# Push updated h8dev to the remote.
git push "$REMOTE" "$TARGET_BRANCH"

# Keep ownership consistent if needed by your deployment setup.
chown -R "$OWNER:$GROUP" "$REPO_DIR"

echo "$LOG_PREFIX Done."
