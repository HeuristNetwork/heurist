#!/usr/bin/env bash
set -Eeuo pipefail

# For use on HeuristRef.net only.
# Nightly sync/merge workflow for h8dev:
#   1) discard all local changes in h8dev working tree
#   2) fetch/sync h7dev from upstream and h8dev from origin
#   3) merge h7dev into h8dev
#   4) email a failure report if the merge/process fails
#   5) restore ownership

REPO_DIR="/var/www/html/HEURIST/h8-alpha"
LOG_PREFIX="[heurist-h8-merge]"
OWNER="osmakov"
GROUP="heurist"
SOURCE_REMOTE="origin"
TARGET_REMOTE="origin"
SOURCE_BRANCH="h7dev"
TARGET_BRANCH="h8dev"

# Comma- or space-separated recipient list accepted by most mail/mailx implementations.
MAIL_TO="support@heuristnetwork.org"
MAIL_SUBJECT_PREFIX="Heurist h8dev nightly merge failed"

# Removes untracked and ignored files. This is intentionally strict because the
# requirement is to reset all possible local changes before syncing.
# Change to "-fd" if ignored local runtime files must be preserved.
GIT_CLEAN_FLAGS="-fdx"

# Push merged h8dev back to TARGET_REMOTE after a successful merge commit.
# Set to 0 if this server should only update its local working tree.
PUSH_AFTER_MERGE=1

LOG_FILE="${LOG_FILE:-/tmp/heurist-h8-merge-$(date +%Y%m%d-%H%M%S).log}"
FAILURE_CONTEXT=""

mkdir -p "$(dirname "$LOG_FILE")"
exec > >(tee -a "$LOG_FILE") 2>&1

send_failure_report() {
  local exit_code="$1"
  local subject="$MAIL_SUBJECT_PREFIX on $(hostname -f 2>/dev/null || hostname)"

  if [ -z "${MAIL_TO// /}" ]; then
    echo "$LOG_PREFIX [EMAIL] MAIL_TO is empty; not sending failure report."
    return 0
  fi

  {
    echo "Nightly merge of $SOURCE_BRANCH into $TARGET_BRANCH failed."
    echo
    echo "Host: $(hostname -f 2>/dev/null || hostname)"
    echo "Repository: $REPO_DIR"
    echo "Source remote: $SOURCE_REMOTE"
    echo "Target remote: $TARGET_REMOTE"
    echo "Source branch: $SOURCE_BRANCH"
    echo "Target branch: $TARGET_BRANCH"
    echo "Exit code: $exit_code"
    echo "Time: $(date -Is)"
    echo
    if [ -n "$FAILURE_CONTEXT" ]; then
      echo "Failure context:"
      echo "$FAILURE_CONTEXT"
      echo
    fi
    echo "Last 200 log lines:"
    echo "------------------------------------------------------------"
    tail -n 200 "$LOG_FILE" 2>/dev/null || true
  } | if command -v mail >/dev/null 2>&1; then
        mail -s "$subject" $MAIL_TO
      elif command -v mailx >/dev/null 2>&1; then
        mailx -s "$subject" $MAIL_TO
      elif command -v sendmail >/dev/null 2>&1; then
        {
          echo "To: $MAIL_TO"
          echo "Subject: $subject"
          echo
          cat
        } | sendmail -t
      else
        echo "$LOG_PREFIX [EMAIL] ERROR: no mail, mailx, or sendmail command found; cannot send failure report."
        return 0
      fi

  echo "$LOG_PREFIX [EMAIL] Failure report sent to $MAIL_TO"
}

fix_ownership() {
  if [ -d "$REPO_DIR" ]; then
    echo "$LOG_PREFIX [OWNERSHIP] Fixing ownership to $OWNER:$GROUP..."
    chown -R "$OWNER:$GROUP" "$REPO_DIR"
    echo "$LOG_PREFIX [OWNERSHIP] Ownership fixed."
  fi
}

on_error() {
  local exit_code=$?
  echo "$LOG_PREFIX ERROR: failed with exit code $exit_code"
  send_failure_report "$exit_code" || true
  fix_ownership || true
  exit "$exit_code"
}
trap on_error ERR

run_git() {
  echo "$LOG_PREFIX git $*"
  git "$@"
}

echo "$LOG_PREFIX Starting nightly merge of $SOURCE_REMOTE/$SOURCE_BRANCH into $TARGET_REMOTE/$TARGET_BRANCH..."
echo "$LOG_PREFIX Log file: $LOG_FILE"

if [ ! -d "$REPO_DIR/.git" ]; then
  FAILURE_CONTEXT="$REPO_DIR is not a git repository"
  echo "$LOG_PREFIX ERROR: $FAILURE_CONTEXT" >&2
  exit 1
fi

cd "$REPO_DIR"

# Ensure there is no unfinished merge/rebase/cherry-pick from a previous run.
if [ -d .git/rebase-merge ] || [ -d .git/rebase-apply ]; then
  FAILURE_CONTEXT="Unfinished rebase found in $REPO_DIR"
  echo "$LOG_PREFIX ERROR: $FAILURE_CONTEXT" >&2
  exit 1
fi
if [ -f .git/MERGE_HEAD ]; then
  echo "$LOG_PREFIX Found unfinished merge; aborting it before reset..."
  git merge --abort || true
fi
if [ -f .git/CHERRY_PICK_HEAD ]; then
  echo "$LOG_PREFIX Found unfinished cherry-pick; aborting it before reset..."
  git cherry-pick --abort || true
fi

# Fetch latest remote state. h7dev is read from SOURCE_REMOTE; h8dev is read/pushed via TARGET_REMOTE.
run_git fetch --prune "$SOURCE_REMOTE"
run_git fetch --prune "$TARGET_REMOTE"

# Verify required remote branches exist before modifying local branch pointers.
if ! git rev-parse --verify --quiet "$SOURCE_REMOTE/$SOURCE_BRANCH" >/dev/null; then
  FAILURE_CONTEXT="Missing remote branch $SOURCE_REMOTE/$SOURCE_BRANCH"
  echo "$LOG_PREFIX ERROR: $FAILURE_CONTEXT" >&2
  exit 1
fi
if ! git rev-parse --verify --quiet "$TARGET_REMOTE/$TARGET_BRANCH" >/dev/null; then
  FAILURE_CONTEXT="Missing remote branch $TARGET_REMOTE/$TARGET_BRANCH"
  echo "$LOG_PREFIX ERROR: $FAILURE_CONTEXT" >&2
  exit 1
fi

# Reset h8dev working tree completely to remote h8dev.
run_git checkout -B "$TARGET_BRANCH" "$TARGET_REMOTE/$TARGET_BRANCH"
run_git reset --hard "$TARGET_REMOTE/$TARGET_BRANCH"
run_git clean "$GIT_CLEAN_FLAGS"

# Refresh local h7dev reference to match remote h7dev exactly.
run_git branch -f "$SOURCE_BRANCH" "$SOURCE_REMOTE/$SOURCE_BRANCH"

BEFORE_MERGE_HEAD="$(git rev-parse HEAD)"

# Merge h7dev into h8dev. Do not commit until conflict-marker checks pass.
set +e
git merge --no-ff --no-commit "$SOURCE_BRANCH"
MERGE_EXIT=$?
set -e

if [ "$MERGE_EXIT" -ne 0 ]; then
  CONFLICTED_FILES="$(git diff --name-only --diff-filter=U || true)"
  FAILURE_CONTEXT="Merge conflict while merging $SOURCE_BRANCH into $TARGET_BRANCH"
  echo "$LOG_PREFIX ERROR: $FAILURE_CONTEXT" >&2
  echo "$LOG_PREFIX Conflicted files:" >&2
  printf '%s\n' "$CONFLICTED_FILES" >&2
  echo "$LOG_PREFIX Git status:" >&2
  git status --short >&2 || true
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
  FAILURE_CONTEXT="Conflict markers found after merge"
  echo "$LOG_PREFIX ERROR: $FAILURE_CONTEXT" >&2
  cat "$TMP_MARKERS" >&2
  rm -f "$TMP_MARKERS"
  git merge --abort || true
  exit 1
fi
rm -f "$TMP_MARKERS"

# If the merge produced no changes, do not create an empty commit.
if git diff --quiet --cached && [ "$(git rev-parse HEAD)" = "$BEFORE_MERGE_HEAD" ]; then
  echo "$LOG_PREFIX No merge changes required; $TARGET_BRANCH is already up to date with $SOURCE_BRANCH."
else
  echo "$LOG_PREFIX Merge staged successfully. Changed files:"
  git diff --stat --cached

  run_git commit -m "Nightly merge $SOURCE_BRANCH into $TARGET_BRANCH"

  if [ "$PUSH_AFTER_MERGE" -eq 1 ]; then
    run_git push "$TARGET_REMOTE" "$TARGET_BRANCH"
  else
    echo "$LOG_PREFIX PUSH_AFTER_MERGE=0; skipping push."
  fi
fi

fix_ownership

echo "$LOG_PREFIX Done."
