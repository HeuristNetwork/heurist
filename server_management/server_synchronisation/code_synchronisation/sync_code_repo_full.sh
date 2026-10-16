#!/usr/bin/env bash
set -euo pipefail

REPO_DIR_ASSOC="/var/www/html/HEURIST/h7-alpha-assoc"
REPO_DIR_GPL="/var/www/html/HEURIST/h7-alpha-gpl"
LOG_PREFIX="[heurist-sync]"
OWNER="osmakov"
GROUP="heurist"

build_heurist_js_bundle() {
  local repo_dir="$1"
  local label="$2"

  echo "$LOG_PREFIX [$label] [JS] Building Heurist JavaScript bundle..."

  if [ ! -f "$repo_dir/package.json" ]; then
    echo "$LOG_PREFIX [$label] [JS] ERROR: package.json not found in $repo_dir" >&2
    exit 1
  fi

  if [ ! -f "$repo_dir/scripts/build-terser.mjs" ]; then
    echo "$LOG_PREFIX [$label] [JS] ERROR: scripts/build-terser.mjs not found in $repo_dir" >&2
    exit 1
  fi

  if ! command -v npm >/dev/null 2>&1; then
    echo "$LOG_PREFIX [$label] [JS] ERROR: npm is not installed. Install Node.js/npm before running this script." >&2
    exit 1
  fi

  cd "$repo_dir"

  if [ -f package-lock.json ]; then
    npm ci --no-audit --no-fund
  else
    npm install --no-audit --no-fund
  fi

  npm run build:js

  echo "$LOG_PREFIX [$label] [JS] JavaScript bundle built."
}

fix_repo_permissions() {
  local repo_dir="$1"

  echo "$LOG_PREFIX [OWNERSHIP] Fixing ownership and permissions for $repo_dir..."
  chown -R "$OWNER:$GROUP" "$repo_dir"

  # Group-writable files; searchable/traversable directories.
  # Preserve executable status for files already marked executable by Git.
  chmod -R ug+rwX "$repo_dir"

  # Ensure new files inherit the repository group.
  find "$repo_dir" -type d -exec chmod g+s {} +

  # Scripts that must always be directly executable.
  chmod ug+x "$repo_dir/copy_distribution_files.sh"

  # The generated bundle must be readable by the web server.
  chmod -R a+rX "$repo_dir/hclient/bundles" 2>/dev/null || true
}

###############################################################################
# A) Sync $REPO_DIR_ASSOC with:
#    - origin:   h7dev-assoc
#    - upstream: h7dev
###############################################################################
DATE="$(date +%F_%H-%M-%S)"

echo "$LOG_PREFIX [ASSOC] ${DATE} Starting sync..."

if [ ! -d "$REPO_DIR_ASSOC/.git" ]; then
  echo "$LOG_PREFIX [ASSOC] ERROR: $REPO_DIR_ASSOC is not a git repository" >&2
  exit 1
fi

cd "$REPO_DIR_ASSOC"

# Ensure we are on the association branch
git checkout h7dev-assoc

# 1) Fetch from origin so local tracking ref origin/h7dev-assoc is up to date
echo "$LOG_PREFIX [ASSOC] Fetching from origin (h7dev-assoc)..."
git fetch origin

echo "$LOG_PREFIX [ASSOC] Resetting local h7dev-assoc to origin/h7dev-assoc..."
git reset --hard origin/h7dev-assoc

# 2) Fetch from upstream so upstream/h7dev is current
echo "$LOG_PREFIX [ASSOC] Fetching from upstream (h7dev)..."
git fetch upstream

# 3) Merge upstream/h7dev into local h7dev-assoc
echo "$LOG_PREFIX [ASSOC] Merging upstream/h7dev into h7dev-assoc..."
if ! git merge --no-ff upstream/h7dev -m "Automated merge from upstream/h7dev into h7dev-assoc"; then
    echo "$LOG_PREFIX [ASSOC] MERGE CONFLICT detected. Aborting merge." >&2
    git merge --abort || true
    exit 1
fi

# 4) Push updated branch back to origin
echo "$LOG_PREFIX [ASSOC] Pushing h7dev-assoc to origin..."
git push origin h7dev-assoc

build_heurist_js_bundle "$REPO_DIR_ASSOC" "ASSOC"

echo "$LOG_PREFIX [ASSOC] Done."


###############################################################################
# B) Sync $REPO_DIR_GPL with upstream h7dev
#    (local h7dev is kept in sync with upstream/h7dev)
###############################################################################

echo "$LOG_PREFIX [GPL] Starting sync..."

if [ ! -d "$REPO_DIR_GPL/.git" ]; then
  echo "$LOG_PREFIX [GPL] ERROR: $REPO_DIR_GPL is not a git repository" >&2
  exit 1
fi

cd "$REPO_DIR_GPL"

# Ensure we are on the GPL branch that tracks h7dev
git checkout h7dev

echo "$LOG_PREFIX [GPL] Fetching from upstream (h7dev)..."
git fetch origin
git reset --hard origin/h7dev

build_heurist_js_bundle "$REPO_DIR_GPL" "GPL"

echo "$LOG_PREFIX [GPL] Done."

fix_repo_permissions "$REPO_DIR_ASSOC"
fix_repo_permissions "$REPO_DIR_GPL"

echo "$LOG_PREFIX [PERMISSIONS] Ownership and permissions fixed."
