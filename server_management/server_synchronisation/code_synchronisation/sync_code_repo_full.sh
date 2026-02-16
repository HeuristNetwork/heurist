#!/usr/bin/env bash
set -euo pipefail

REPO_DIR_ASSOC="/var/www/html/HEURIST/h7-alpha-assoc"
REPO_DIR_GPL="/var/www/html/HEURIST/h7-alpha-gpl"
LOG_PREFIX="[heurist-sync]"
OWNER="osmakov"
GROUP="heurist"

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

echo "$LOG_PREFIX [GPL] Done."

echo "$LOG_PREFIX [OWNERSHIP] Fixing ownership to $OWNER:$GROUP..."
chown -R "$OWNER:$GROUP" "REPO_DIR_ASSOC" "$REPO_DIR_GPL"
echo "$LOG_PREFIX [OWNERSHIP] Ownership fixed."
