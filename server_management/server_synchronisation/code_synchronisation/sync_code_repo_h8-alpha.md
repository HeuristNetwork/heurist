# `sync_code_repo_h8-alpha.sh`

Short operational notes for the h8 development sync script.

## Purpose

`sync_code_repo_h8-alpha.sh` updates the `h8-alpha` working copy from Git and merges the latest `h7dev` changes into `h8dev`.

The script performs these actions:

1. Verifies that `/var/www/html/HEURIST/h8-alpha` is a Git repository.
2. Fetches and prunes the configured remote, normally `upstream`.
3. Checks that both required remote branches exist:
   - `upstream/h7dev`
   - `upstream/h8dev`
4. Resets the local `h8dev` working tree to match `upstream/h8dev`.
5. Removes local untracked and ignored files with `git clean -fdx`.
6. Refreshes the local `h7dev` reference from `upstream/h7dev`.
7. Merges `h7dev` into `h8dev`.
8. Checks for unresolved merge-conflict markers.
9. Commits and pushes the merge to `upstream/h8dev` if changes were created.
10. Emails a failure report if the process fails.
11. Restores ownership of the working tree to `osmakov:heurist`.

## Check that `h7dev` and `h8dev` branches exist

Run this before enabling the cron job:

```bash
cd /var/www/html/HEURIST/h8-alpha

git fetch --prune upstream

git rev-parse --verify upstream/h7dev
git rev-parse --verify upstream/h8dev
```

Both commands should return a commit hash. If either command fails, check the remote name and branch names:

```bash
git remote -v
git branch -r | grep -E 'upstream/(h7dev|h8dev)'
```

## Clone `h8dev`

If `/var/www/html/HEURIST/h8-alpha` does not already exist, clone it first.

```bash
cd /var/www/html/HEURIST

git clone --branch h8dev https://github.com/HeuristNetwork/heurist.git h8-alpha
cd h8-alpha

git remote -v
```

If the remote is named `origin` but the script expects `upstream`, either rename the remote:

```bash
git remote rename origin upstream
```

or edit the script and set:

```bash
REMOTE="origin"
```

## Script location and enabling

Place the script here:

```bash
/src/scripts/sync_code_repo_h8-alpha.sh
```

Make it executable:

```bash
chmod 750 /src/scripts/sync_code_repo_h8-alpha.sh
```

Check the configuration near the top of the script before enabling it:

```bash
REPO_DIR="/var/www/html/HEURIST/h8-alpha"
REMOTE="upstream"
SOURCE_BRANCH="h7dev"
TARGET_BRANCH="h8dev"
MAIL_TO="server-managers@example.org"
OWNER="osmakov"
GROUP="heurist"
```

Update `MAIL_TO` to the real administrator address or mailing list.

Run a manual test:

```bash
sudo /src/scripts/sync_code_repo_h8-alpha.sh
```

Then confirm:

```bash
cd /var/www/html/HEURIST/h8-alpha

git status
git log --oneline -5
```

## Cron job sample

Recommended scheduling: run the h8 script after the h7 script has completed.

If `sync_code_repo_h7-alpha.sh` runs hourly at minute 12, schedule `sync_code_repo_h8-alpha.sh` later in the same hour, for example minute 25:

```cron
12 * * * * /src/scripts/sync_code_repo_h7-alpha.sh >> /var/log/heurist-sync-h7-alpha.log 2>&1
25 * * * * /src/scripts/sync_code_repo_h8-alpha.sh >> /var/log/heurist-sync-h8-alpha.log 2>&1
```

This gives the h7 sync time to finish before h8 fetches and merges `h7dev`.

## Scheduling recommendation

Hourly h7 sync at minute 12 is reasonable if the server is expected to track repository changes quickly.

`sync_code_repo_h8-alpha.sh` can also run hourly, but it should not run at the same minute as the h7 sync. Run it 10–20 minutes after h7, depending on how long the h7 sync normally takes.

Recommended default:

```cron
12 * * * * /src/scripts/sync_code_repo_h7-alpha.sh >> /var/log/heurist-sync-h7-alpha.log 2>&1
25 * * * * /src/scripts/sync_code_repo_h8-alpha.sh >> /var/log/heurist-sync-h8-alpha.log 2>&1
```

If h8 is less urgent or merge conflicts create too much noise, run h8 less often, for example every 3 hours:

```cron
25 */3 * * * /src/scripts/sync_code_repo_h8-alpha.sh >> /var/log/heurist-sync-h8-alpha.log 2>&1
```

Do not schedule h8 before h7 in the same hour, because h8 depends on the latest `h7dev` state.
