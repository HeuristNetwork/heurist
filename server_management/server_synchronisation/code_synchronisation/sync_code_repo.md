# `sync_code_repo.sh` – Heurist Code Repository Synchronisation

This document describes how to set up and use `/srv/scripts/sync_code_repo.sh` to automatically:

- Sync **association code** (`h7dev-assoc`) in  
  `/var/www/html/HEURIST/h7-alpha-assoc`  
  with:
  - `upstream`: `HeuristNetwork/heurist` (branch `h7dev`)
  - `origin`: `HeuristNetwork/HN_Association` (branch `h7dev-assoc`)
- Sync **GPL code** (`h7dev`) in  
  `/var/www/html/HEURIST/h7-alpha-gpl`  
  with `HeuristNetwork/heurist` (branch `h7dev`)

Cron will run the script, log to a file, and send email with the run output.

---

## 1. Set up SSH key for GitHub and test connection

These steps are done on the **server** that will run the script and cronjob.

### 1.1 Generate an SSH key (if you don’t already have one)

Check if a key exists:

```bash
ls ~/.ssh
```

If you don’t see `id_ed25519` / `id_ed25519.pub` or `id_rsa` / `id_rsa.pub`, create a new key:

```bash
ssh-keygen -t ed25519 -C "your_email@example.com"
```

- Press **Enter** to accept the default file location (usually `~/.ssh/id_ed25519`).
- Optionally enter a passphrase (or leave empty for non-interactive use in cron).

### 1.2 Add the SSH public key to GitHub

Display your public key:

```bash
cat ~/.ssh/id_ed25519.pub
```

Copy the entire line.

On GitHub (in your browser, logged in as the correct user):

1. Go to **Settings → SSH and GPG keys → New SSH key**
2. Give it a descriptive title (e.g. `heurist-server`)
3. Paste the public key
4. Click **Add SSH key**

### 1.3 Test the SSH connection to GitHub

On the server:

```bash
ssh -T git@github.com
```

On first connect, you may see:

> The authenticity of host 'github.com' can't be established…  
> Are you sure you want to continue connecting (yes/no)?

Type `yes` and press Enter.

If everything is set up correctly, you’ll see something like:

> Hi <username>! You've successfully authenticated, but GitHub does not provide shell access.

This confirms SSH auth works and that Git commands using `git@github.com:...` will use this key.

---

## 2. Clone repositories

You need to clone two repositories on the server:

- **GPL repo:** `HeuristNetwork/heurist` → `/var/www/html/HEURIST/h7-alpha-gpl`
- **Association repo:** `HeuristNetwork/HN_Association` → `/var/www/html/HEURIST/h7-alpha-assoc`

> Run the following commands as the user that will own and run the script/cronjob
> (often `root` on a server, but could also be a dedicated user).

### 2.1 Clone `HeuristNetwork/heurist` (GPL) to `/var/www/html/HEURIST/h7-alpha-gpl`

Create the parent directory if needed:

```bash
mkdir -p /var/www/html/HEURIST
cd /var/www/html/HEURIST
```

Clone the main Heurist repository:

```bash
git clone https://github.com/HeuristNetwork/heurist.git h7-alpha-gpl
```

This creates the directory:

- `/var/www/html/HEURIST/h7-alpha-gpl`

### 2.2 Clone `HeuristNetwork/HN_Association` (ASSOC) to `/var/www/html/HEURIST/h7-alpha-assoc`

From the same base directory:

```bash
cd /var/www/html/HEURIST
git clone git@github.com:HeuristNetwork/HN_Association.git h7-alpha-assoc
```

This creates the directory:

- `/var/www/html/HEURIST/h7-alpha-assoc`

Ensure that both clones succeed without errors before continuing.

---

## 3. Create `upstream` remote

We use `upstream` to point to the main Heurist repo (`HeuristNetwork/heurist.git`).  
`origin` remains whatever Git set up when cloning:

- For **GPL repo**: `origin` = `HeuristNetwork/heurist`
- For **ASSOC repo**: `origin` = `HeuristNetwork/HN_Association`

### 3.1 Add `upstream` in association repo (`h7-alpha-assoc`)

```bash
cd /var/www/html/HEURIST/h7-alpha-assoc

git remote add upstream https://github.com/HeuristNetwork/heurist.git
```

Verify:

```bash
git remote -v
```

You should see something like:

```text
origin   git@github.com:HeuristNetwork/HN_Association.git (fetch)
origin   git@github.com:HeuristNetwork/HN_Association.git (push)
upstream https://github.com/HeuristNetwork/heurist.git (fetch)
upstream https://github.com/HeuristNetwork/heurist.git (push)
```
---

## 4. Manual check commands (before automating)

Before relying on the cronjob, you should manually verify that sync works.

### 4.1 Association repo – manual sync (`h7-alpha-assoc`)

```bash
cd /var/www/html/HEURIST/h7-alpha-assoc

# Show remotes
git remote -v

# List branches
git branch -a

# Switch to association branch
git checkout h7dev-assoc

# Fetch from origin (updates origin/h7dev-assoc)
git fetch origin
git reset --hard origin/h7dev

# Fetch from upstream (updates upstream/h7dev)
git fetch upstream

# Merge upstream/h7dev into local h7dev-assoc
git merge --no-ff upstream/h7dev -m "Automated merge from upstream/h7dev into h7dev-assoc"

# If conflicts appear, resolve them, then:
git add <fixed-files>
git commit

# Push updated branch back to origin
git push origin h7dev-assoc
```

If all of the above commands succeed **without prompting for username/password**, you’re ready for automation in this repo.

### 4.2 GPL repo – manual sync (`h7-alpha-gpl`)

```bash
cd /var/www/html/HEURIST/h7-alpha-gpl

# Show remotes
git remote -v

# List branches
git branch -a

# Switch to h7dev branch
git checkout h7dev

# Fetch latest from upstream
git fetch origin
git reset --hard origin/h7dev


```

If `--ff-only` fails with a message that histories have diverged, you’ll need to inspect/review local commits in this repo. Under normal circumstances, this repo is expected to simply track upstream.

---

## 5. Install `sync_code_repo.sh` in `/srv/scripts/` and make it executable

### 5.1 Create script directory (if needed)

```bash
mkdir -p /srv/scripts
```

### 5.2 Copy script from source code to `/srv/scripts/`

Assuming `sync_code_repo.sh` already exists in your source code (for example in a repository checkout), copy it to `/srv/scripts/`:

```bash
cp /var/www/html/HEURIST/h7-alpha-gpl/hserv/server_management/srv_scripts_sync/sync_code_repo.sh /srv/scripts/sync_code_repo.sh
```

### 5.3 Make the script executable

```bash
chmod +x /srv/scripts/sync_code_repo.sh
```

---

## 6. Cronjob setup (log file + email notifications)

We want:

- A log file on disk: `/var/log/heurist_sync.log`
- Cron to email the output of each run (success or failure) to a chosen address

> Note: For email delivery to work, the server must have a mail transfer agent (MTA) configured (e.g. `postfix`, `sendmail`, etc.).  

If you really want to run the script as root via cron, you must tell Git (for root) that these directories are safe

```bash
git config --global --add safe.directory /var/www/html/HEURIST/h7-alpha-assoc
git config --global --add safe.directory /var/www/html/HEURIST/h7-alpha-gpl
```

You can check later with:
```bash
git config --global --get-all safe.directory
```

### 6.1 Open crontab

For the user that should run the sync (commonly `root`):

```bash
crontab -e
```

### 6.2 Set `MAILTO` and add the cron entry

At the top of the file, set your email address:

```cron
MAILTO="you@example.com"
```

Then add the cron job, e.g. to run daily at 03:00:

```cron
0 3 * * * /srv/scripts/sync_code_repo.sh 2>&1 | tee -a /var/log/heurist_sync.log
```

Explanation:

- `0 3 * * *` → run at 03:00 every day  
- `/srv/scripts/sync_code_repo.sh` → executes the synchronisation script  
- `2>&1` → sends stderr into stdout  
- `tee -a /var/log/heurist_sync.log`:
  - appends all output to `/var/log/heurist_sync.log`  
  - also passes it through to cron  
- Because cron sees the output and `MAILTO` is set, it **emails the output** to `you@example.com` after each run.

So you get:

- A persistent log at `/var/log/heurist_sync.log`  
- An email summary of each run (including errors, if any)
