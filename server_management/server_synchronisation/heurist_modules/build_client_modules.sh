#!/usr/bin/env bash
#
# build_client_modules.sh - Synchronise, build and deploy independent Heurist client modules.
#
# Current modules:
#   - heurist-map
#   - heurist-mirador4
#
# Dedicated source checkouts live under /var/www/html/HEURIST.
# Built distributions are published under:
#   /var/www/html/HEURIST/h7-alpha/hclient/bundles/<module>
#
# Routine output is written to LOG_FILE. Cron receives output only on failure,
# so MAILTO sends mail only when the run is unsuccessful.

set -Eeuo pipefail

HEURIST_ROOT="${HEURIST_ROOT:-/var/www/html/HEURIST}"
DIST_ROOT="${HEURIST_CLIENT_DIST_ROOT:-$HEURIST_ROOT/h7-alpha/hclient/bundles}"
LOG_FILE="${HEURIST_CLIENT_MODULE_LOG:-/var/log/heurist_client_modules.log}"
LOG_PREFIX="[heurist-client-modules]"
OWNER="${HEURIST_CLIENT_OWNER:-osmakov}"
GROUP="${HEURIST_CLIENT_GROUP:-heurist}"
BRANCH="${HEURIST_CLIENT_BRANCH:-main}"

# Override these environment variables if a repository uses a different URL.
HEURIST_MAP_REPO="${HEURIST_MAP_REPO:-git@github.com:HeuristNetwork/heurist-map.git}"
HEURIST_MIRADOR4_REPO="${HEURIST_MIRADOR4_REPO:-git@github.com:HeuristNetwork/heurist-mirador4.git}"

RUN_LOG="$(mktemp /tmp/heurist-client-modules.XXXXXX.log)"
TEE_PID=""
LOCK_FILE="${HEURIST_CLIENT_MODULE_LOCK:-/var/lock/heurist-client-modules.lock}"

# Preserve original stdout/stderr for the short cron failure report.
exec 3>&1 4>&2

cleanup_and_report() {
    local exit_code=$?

    exec 1>&- 2>&-
    if [[ -n "$TEE_PID" ]]; then
        wait "$TEE_PID" 2>/dev/null || true
    fi

    if (( exit_code != 0 )); then
        {
            echo "$LOG_PREFIX ERROR: client-module build/deployment failed (exit code $exit_code)."
            echo "$LOG_PREFIX Full log: $LOG_FILE"
            echo "$LOG_PREFIX Last 40 lines from this run:"
            tail -n 40 "$RUN_LOG"
        } >&3
    fi

    rm -f "$RUN_LOG"
    trap - EXIT
    exit "$exit_code"
}
trap cleanup_and_report EXIT

mkdir -p "$(dirname "$LOG_FILE")"
exec > >(tee -a "$RUN_LOG" >> "$LOG_FILE") 2>&1
TEE_PID=$!

echo
printf '%s %s\n' "$LOG_PREFIX" "$(date '+%Y-%m-%d %H:%M:%S %Z') Starting client-module build..."

require_command() {
    local command_name="$1"
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "$LOG_PREFIX ERROR: required command '$command_name' is not installed." >&2
        return 1
    fi
}

check_node_version() {
    # Vite 8 requires Node >=20.19, or >=22.12 on the Node 22 line.
    node - <<'NODE'
const [major, minor] = process.versions.node.split('.').map(Number);
const ok = (major === 20 && minor >= 19) || (major === 21) || (major === 22 && minor >= 12) || major >= 23;
if (!ok) {
    console.error(`Node.js ${process.versions.node} is too old. Vite 8 requires Node.js 20.19+ or 22.12+.`);
    process.exit(1);
}
NODE
}

acquire_lock() {
    if command -v flock >/dev/null 2>&1; then
        mkdir -p "$(dirname "$LOCK_FILE")"
        exec 9>"$LOCK_FILE"
        if ! flock -n 9; then
            echo "$LOG_PREFIX ERROR: another client-module build is already running." >&2
            exit 1
        fi
    fi
}

ensure_repository() {
    local name="$1"
    local repo_url="$2"
    local repo_dir="$HEURIST_ROOT/$name"

    if [[ ! -d "$repo_dir/.git" ]]; then
        if [[ -e "$repo_dir" ]]; then
            echo "$LOG_PREFIX [$name] ERROR: $repo_dir exists but is not a git repository." >&2
            return 1
        fi
        echo "$LOG_PREFIX [$name] Cloning $repo_url..."
        git clone --branch "$BRANCH" --single-branch "$repo_url" "$repo_dir"
    fi

    echo "$LOG_PREFIX [$name] Synchronising $BRANCH..."
    git -C "$repo_dir" fetch --prune origin "$BRANCH"
    git -C "$repo_dir" checkout -B "$BRANCH" "origin/$BRANCH"
    git -C "$repo_dir" reset --hard "origin/$BRANCH"

    if [[ ! -f "$repo_dir/package.json" ]]; then
        echo "$LOG_PREFIX [$name] ERROR: package.json not found." >&2
        return 1
    fi
    if [[ ! -f "$repo_dir/package-lock.json" ]]; then
        echo "$LOG_PREFIX [$name] ERROR: package-lock.json is required for reproducible npm ci builds." >&2
        return 1
    fi
}

build_and_deploy() {
    local name="$1"
    local repo_dir="$HEURIST_ROOT/$name"

    echo "$LOG_PREFIX [$name] Installing locked dependencies..."
    (
        cd "$repo_dir"
        npm ci --no-audit --no-fund

        # Vite is intentionally project-local. Do not require/install a global Vite.
        if [[ -x node_modules/.bin/vite ]]; then
            echo "$LOG_PREFIX [$name] Vite: $(node_modules/.bin/vite --version)"
        fi

        if ! npm run | grep -qE '^[[:space:]]*deploy:heurist([[:space:]]|$)'; then
            echo "$LOG_PREFIX [$name] ERROR: package.json has no deploy:heurist script." >&2
            exit 1
        fi

        echo "$LOG_PREFIX [$name] Building and deploying..."
        HEURIST_CLIENT_DIST_ROOT="$DIST_ROOT" npm run deploy:heurist
    )

    if [[ ! -d "$DIST_ROOT/$name" ]]; then
        echo "$LOG_PREFIX [$name] ERROR: deployment directory was not created: $DIST_ROOT/$name" >&2
        return 1
    fi

    echo "$LOG_PREFIX [$name] Deployed to $DIST_ROOT/$name"
}

fix_permissions() {
    mkdir -p "$DIST_ROOT"

    if id "$OWNER" >/dev/null 2>&1 && getent group "$GROUP" >/dev/null 2>&1; then
        chown -R "$OWNER:$GROUP" "$HEURIST_ROOT/heurist-map" "$HEURIST_ROOT/heurist-mirador4" "$DIST_ROOT"
    else
        echo "$LOG_PREFIX WARNING: owner/group $OWNER:$GROUP not found; ownership unchanged."
    fi

    chmod -R ug+rwX "$HEURIST_ROOT/heurist-map" "$HEURIST_ROOT/heurist-mirador4" "$DIST_ROOT"
    find "$HEURIST_ROOT/heurist-map" "$HEURIST_ROOT/heurist-mirador4" "$DIST_ROOT" \
        -type d -exec chmod g+s {} +
    chmod -R a+rX "$DIST_ROOT"
}

require_command git
require_command node
require_command npm
check_node_version
acquire_lock

mkdir -p "$HEURIST_ROOT" "$DIST_ROOT"

ensure_repository "heurist-map" "$HEURIST_MAP_REPO"
ensure_repository "heurist-mirador4" "$HEURIST_MIRADOR4_REPO"

build_and_deploy "heurist-map"
build_and_deploy "heurist-mirador4"

fix_permissions

echo "$LOG_PREFIX Client-module build/deployment completed successfully."
exit 0
