#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="/var/www/html/HEURIST/h7-alpha"
LOG_PREFIX="[heurist-sync]"
OWNER="osmakov"
GROUP="heurist"
REMOTE="upstream"
BRANCH="h7dev"

echo "$LOG_PREFIX Starting sync..."

if [ ! -d "$REPO_DIR/.git" ]; then
  echo "$LOG_PREFIX ERROR: $REPO_DIR is not a git repository" >&2
  exit 1
fi

cd "$REPO_DIR"

# Ensure we are on the branch that tracks h7dev
git checkout "$BRANCH"

echo "$LOG_PREFIX Fetching from $REMOTE ($BRANCH)..."
git fetch --prune "$REMOTE"

echo "$LOG_PREFIX Resetting to $REMOTE/$BRANCH..."
git reset --hard "$REMOTE/$BRANCH"

echo "$LOG_PREFIX Done."

echo "$LOG_PREFIX [OWNERSHIP] Fixing ownership to $OWNER:$GROUP..."
chown -R "$OWNER:$GROUP" "$REPO_DIR"
echo "$LOG_PREFIX [OWNERSHIP] Ownership fixed."
