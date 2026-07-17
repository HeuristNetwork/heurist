#!/usr/bin/env bash

# Update h7-alpha to the latest state of h7dev.
# Routine output is written to LOG_FILE. Cron receives output only on failure,
# so MAILTO sends mail only when the run is unsuccessful.

set -Eeuo pipefail

REPO_DIR="/var/www/html/HEURIST/h7-alpha"
LOG_FILE="/var/log/heurist_sync.log"
LOG_PREFIX="[heurist-sync]"
OWNER="osmakov"
GROUP="heurist"
REMOTE="upstream"
BRANCH="h7dev"

RUN_LOG="$(mktemp /tmp/heurist-sync.XXXXXX.log)"
HAD_ERRORS=0
TEE_PID=""

# Preserve the original stdout/stderr for the short cron failure message.
exec 3>&1 4>&2

cleanup_and_report() {
  local exit_code=$?

  # A non-fatal stage may mark the run as unsuccessful without immediately
  # aborting the remaining cleanup/permission stages.
  if (( HAD_ERRORS != 0 && exit_code == 0 )); then
    exit_code=1
  fi

  # Close the logging pipe and wait until tee has flushed this run's output.
  exec 1>&- 2>&-
  if [[ -n "$TEE_PID" ]]; then
    wait "$TEE_PID" 2>/dev/null || true
  fi

  if (( exit_code != 0 )); then
    {
      echo "$LOG_PREFIX ERROR: repository synchronisation failed (exit code $exit_code)."
      echo "$LOG_PREFIX Full log: $LOG_FILE"
      echo "$LOG_PREFIX Last 30 lines from this run:"
      tail -n 30 "$RUN_LOG"
    } >&3
  fi

  rm -f "$RUN_LOG"
  trap - EXIT
  exit "$exit_code"
}
trap cleanup_and_report EXIT

# Write normal output to both the persistent log and this run's temporary log,
# but not to cron stdout.
mkdir -p "$(dirname "$LOG_FILE")"
exec > >(tee -a "$RUN_LOG" >> "$LOG_FILE") 2>&1
TEE_PID=$!

echo
printf '%s %s\n' "$LOG_PREFIX" "$(date '+%Y-%m-%d %H:%M:%S %Z') Starting sync..."

build_heurist_js_bundle() {
  local repo_dir="$1"

  echo "$LOG_PREFIX [JS] Building Heurist JavaScript bundle..."

  if [[ ! -f "$repo_dir/package.json" ]]; then
    echo "$LOG_PREFIX [JS] ERROR: package.json not found in $repo_dir" >&2
    return 1
  fi

  if [[ ! -f "$repo_dir/scripts/build-terser.mjs" ]]; then
    echo "$LOG_PREFIX [JS] ERROR: scripts/build-terser.mjs not found in $repo_dir" >&2
    return 1
  fi

  if ! command -v npm >/dev/null 2>&1; then
    echo "$LOG_PREFIX [JS] ERROR: npm is not installed." >&2
    return 1
  fi

  cd "$repo_dir"

  if [[ -f package-lock.json ]]; then
    npm ci --no-audit --no-fund
  else
    npm install --no-audit --no-fund
  fi

  npm run build:js
  echo "$LOG_PREFIX [JS] JavaScript bundle built."
}

if [[ ! -d "$REPO_DIR/.git" ]]; then
  echo "$LOG_PREFIX ERROR: $REPO_DIR is not a git repository" >&2
  exit 1
fi

cd "$REPO_DIR"

git checkout "$BRANCH"

echo "$LOG_PREFIX Fetching from $REMOTE ($BRANCH)..."
git fetch --prune "$REMOTE"

echo "$LOG_PREFIX Resetting to $REMOTE/$BRANCH..."
git reset --hard "$REMOTE/$BRANCH"

echo "$LOG_PREFIX [GIT] Done."

if ! build_heurist_js_bundle "$REPO_DIR"; then
  echo "$LOG_PREFIX [JS] ERROR: bundle build failed; continuing with permissions." >&2
  HAD_ERRORS=1
fi

echo "$LOG_PREFIX [OWNERSHIP] Fixing ownership to $OWNER:$GROUP and permissions..."

chown -R "$OWNER:$GROUP" "$REPO_DIR"
chmod -R ug+rwX "$REPO_DIR"
find "$REPO_DIR" -type d -exec chmod g+s {} +
chmod ug+x "$REPO_DIR/copy_distribution_files.sh"
chmod -R a+rX "$REPO_DIR/hclient/bundles" 2>/dev/null || true

echo "$LOG_PREFIX [PERMISSIONS] Ownership and permissions fixed."

if (( HAD_ERRORS != 0 )); then
  echo "$LOG_PREFIX Completed with errors." >&2
  exit 1
fi

echo "$LOG_PREFIX Sync completed successfully."
exit 0
