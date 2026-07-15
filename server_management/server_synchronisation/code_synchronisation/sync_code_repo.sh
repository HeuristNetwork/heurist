#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="/var/www/html/HEURIST/h7-alpha"
LOG_PREFIX="[heurist-sync]"
OWNER="osmakov"
GROUP="heurist"
REMOTE="upstream"
BRANCH="h7dev"

build_heurist_js_bundle() {
  local repo_dir="$1"

  echo "$LOG_PREFIX [JS] Building Heurist JavaScript bundle..."

  if [ ! -f "$repo_dir/package.json" ]; then
    echo "$LOG_PREFIX [JS] ERROR: package.json not found in $repo_dir" >&2
    exit 1
  fi

  if [ ! -f "$repo_dir/scripts/build-terser.mjs" ]; then
    echo "$LOG_PREFIX [JS] ERROR: scripts/build-terser.mjs not found in $repo_dir" >&2
    exit 1
  fi

  if ! command -v npm >/dev/null 2>&1; then
    echo "$LOG_PREFIX [JS] ERROR: npm is not installed. Install Node.js/npm before running this script." >&2
    exit 1
  fi

  cd "$repo_dir"

  if [ -f package-lock.json ]; then
    npm ci --no-audit --no-fund
  else
    npm install --no-audit --no-fund
  fi

  npm run build:js

  echo "$LOG_PREFIX [JS] JavaScript bundle built."
}

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

echo "$LOG_PREFIX [GIT] Done."

if ! build_heurist_js_bundle "$REPO_DIR"; then
  echo "$LOG_PREFIX WARNING: JS bundle build failed; continuing sync." >&2
fi

echo "$LOG_PREFIX [OWNERSHIP] Fixing ownership to $OWNER:$GROUP and permissions..."
chown -R "$OWNER:$GROUP" "$REPO_DIR"

# Group-writable files; searchable/traversable directories.
# Preserve executable status for files already marked executable by Git.
chmod -R ug+rwX "$REPO_DIR"

# Ensure new files inherit the repository group.
find "$REPO_DIR" -type d -exec chmod g+s {} +

# Scripts that must always be directly executable.
chmod ug+x "$REPO_DIR/copy_distribution_files.sh"

# The generated bundle must be readable by the web server.
chmod -R a+rX "$REPO_DIR/hclient/bundles" 2>/dev/null || true

echo "$LOG_PREFIX [PERMISSIONS] Ownership and permissions fixed."
