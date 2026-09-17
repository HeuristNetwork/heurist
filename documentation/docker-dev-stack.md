# Docker development stack for Heurist 7.x

A self-contained Docker Compose setup that runs a complete Heurist 7
development instance — PHP/Apache web application plus MySQL 8.0 database —
with the local repository bind-mounted for live editing.

## What was changed

### Commit 1: Docker development stack (new files)

| File | Purpose |
|---|---|
| `Dockerfile` | Builds the web image on `php:8.2-apache`. Installs the PHP extensions Heurist needs (`gd`, `mbstring`, `mysqli`, `pdo_mysql`, `zip`), enables `mod_rewrite`, resolves Composer dependencies (with a dev-only override for pinned smarty versions carrying security advisories), and downloads the `external_h5` / `help` support bundles from the Heurist distribution server (best-effort, each download may fail without breaking the build). |
| `docker-compose.yml` | Defines the `db` (MySQL 8.0) and `web` services, named volumes for persistent data, and the environment/credentials wiring. |
| `docker/entrypoint.sh` | Runtime bootstrapping: creates `HEURIST_FILESTORE` with web access denied, copies the `movetoparent` switchboard files into place, restores the `external`/`help` symlinks, fills gaps in the support bundles (jQuery, jQuery UI, Bootstrap, FancyTree, DataTables — the distribution tarballs omit some of these), and copies the baked-in Composer vendor tree into bind-mounted repos that don't have one. |
| `docker/heuristConfigIni.php` | Container variant of the parent-level `heuristConfigIni.php`. Loads the shipped template from `movetoparent/` for all defaults, then overrides only what must differ in the container: DB host/credentials from environment variables, filestore path/URL, direct filestore web access off (served through PHP instead), PDO-based dump modes (safe when MySQL is a separate container), and the JWT secret from the environment. |
| `docker/heurist-php.ini` | PHP settings: 256M uploads/post, 512M memory, errors logged to stderr (never printed into responses — stray output corrupts Heurist's JSON endpoints and breaks session cookie handling during database creation). |
| `docker/apache-heurist.conf` | Apache vhost. DocumentRoot is the HEURIST parent directory (the "switchboard"), matching `server_management/code_setup/virtual_host_configuration_howto.txt`. `AllowOverride All` so the codebase `.htaccess` applies; direct access to the filestore is denied. |
| `docker/apache-servername.conf` | Suppresses the Apache FQDN warning. |
| `docker/mysql-init/01-grant-heurist.sql` | Grants the `heurist` MySQL user rights on the whole `hdb_%` prefix. The official MySQL image only grants rights on `MYSQL_DATABASE`, but Heurist creates its own `hdb_*` databases at runtime. |
| `.gitignore` | Ignores `.env` (compose secrets) and the local `external` symlink. |

### Why two MySQL flags are required

MySQL 8 needs two non-default settings to run Heurist:

- `--log_bin_trust_function_creators=1` — Heurist creates stored functions and
  triggers in its databases; binary logging otherwise blocks that for
  non-SUPER users.
- `--sql-mode=STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION` — Heurist's legacy
  search SQL (e.g. `SELECT DISTINCT rec_ID ... ORDER BY rec_Title`) is
  incompatible with `ONLY_FULL_GROUP_BY`, which is in MySQL 8's default
  sql_mode. The application tries to relax it at runtime via
  `SET GLOBAL sql_mode` (`hserv/System.php`), but the restricted `heurist`
  user lacks the required privilege, so it is set at server start.

### Commit 2: sessionCheckFolder fix (`hserv/utilities/USystem.php`)

`USystem::sessionCheckFolder()` failed every session-dependent request —
including opening a database — when `session.save_path` was empty, even though
PHP then stores sessions in the system temporary directory
(`sys_get_temp_dir()`). The fix:

- An empty/null save path now falls back to `sys_get_temp_dir()`.
- The optional `N;mode;/path` argument form of `session.save_path` is parsed
  and only the final path segment is checked for writability.
- Fixes an operator-precedence bug in the save-handler check: the original
  `!ini_get('session.save_handler')=='files'` compares a boolean to a string;
  the intended test is `ini_get('session.save_handler') != 'files'`.

This is required for the Docker stack, where `session.save_path` is unset and
PHP defaults to `/tmp`.

## Layout reproduced inside the container

Same layout as `server_management/code_setup/install_heurist7.sh` creates on
a bare-metal install:

```
/var/www/html/HEURIST/
├── heurist/                  <- this repository (bind-mounted: live edits)
├── heuristConfigIni.php      <- mounted read-only from docker/heuristConfigIni.php
├── HEURIST_FILESTORE/        <- uploaded files (named volume heurist_filestore)
└── HEURIST_SUPPORT/          <- external_h5 / help bundles (named volume heurist_support)
```

## How to use it

### Prerequisites

- Docker Desktop (Windows/macOS) or Docker Engine + Compose v2 (Linux).
- Nothing else: PHP, Apache, MySQL and Composer are all inside the image.

### Start the stack

```bash
cd heurist
docker compose up -d --build     # first run builds the image (a few minutes)
```

`web` waits for the MySQL healthcheck before starting.

### First run: create a database

1. Open http://localhost:8080/ — the Heurist switchboard.
2. Follow the *new database* setup: choose a database name; it is created as
   `hdb_<name>` in MySQL using the admin credentials below.
3. Record creation, the record editor, imports and the API all work against
   that database.

The application is served at http://localhost:8080/heurist/ .

### Configuration (environment variables)

Defaults are development-safe and already work out of the box. To override,
create a `.env` next to `docker-compose.yml` (it is git-ignored):

| Variable | Default | Used by |
|---|---|---|
| `HEURIST_MYSQL_ROOT_PASSWORD` | `heurist_root` | `db` service root password |
| `HEURIST_DB_ADMIN_PASSWORD` | `heurist` | MySQL `heurist` user; passed to the web app as `$dbAdminPassword` |
| `HEURIST_JWT_SECRET` | `dev-only-secret-change-me` | JWT signing for the REST API (`hserv/controller/auth.php`) |

The MySQL admin username is fixed to `heurist` (`HEURIST_DB_ADMIN_USERNAME` in
the web environment, `MYSQL_USER` in the db environment).

### Live editing

The repository is bind-mounted at `/var/www/html/HEURIST/heurist`, so edits on
the host show up immediately — no rebuild, no container restart needed for PHP
changes. See **Updating the stack with the latest code** below for the cases
that do need a rebuild.

### Updating the stack with the latest code

How much work a code update requires depends on *what* changed:

| What changed upstream | What you need to do |
|---|---|
| PHP, JS, CSS, templates (anything under the repo) | Nothing — the bind mount picks it up on the next page load |
| `composer.json` / `composer.lock` | Re-resolve dependencies (see below) |
| `Dockerfile`, anything under `docker/`, `docker-compose.yml` | Rebuild the image: `docker compose up -d --build` |
| Support-bundle libraries (`external_h5`, `help`) | Refresh the `heurist_heurist_support` volume (see below) |

The routine update sequence is:

```bash
cd heurist
git pull                        # or git checkout <branch>
docker compose up -d --build    # recreates containers if the image changed
docker compose ps               # web should be "Up", db "Up (healthy)"
```

`--build` is cheap when nothing image-relevant changed (Docker layer cache
replays instantly); it is required when the `Dockerfile`, `docker/*` files or
`docker-compose.yml` changed.

**Composer dependencies.** The image bakes a vendor tree at build time, but at
runtime the repo's `vendor/` directory is bind-mounted over it, and the
entrypoint only copies the baked-in tree when `vendor/autoload.php` is
*missing*. A rebuild alone therefore does **not** update an existing `vendor/`.
After a `composer.json` change, either:

```bash
# Option A: resolve inside the running container (uses the live repo)
docker compose exec web sh -c 'cd /var/www/html/HEURIST/heurist &&
    composer config policy.advisories.block false &&
    composer update --no-interaction --prefer-dist --no-progress'

# Option B: start fresh — delete the local vendor tree, then rebuild;
# the entrypoint reinstalls it from the image
rm -rf vendor
docker compose up -d --build
```

(The `policy.advisories.block false` matches the build-time override for the
pinned smarty versions; see the Dockerfile comment.)

**Support bundles.** `external_h5`/`help` live in the `heurist_support`
volume, which persists across rebuilds by design, and the entrypoint only
*fills gaps* in it. If upstream ships newer bundle content, force a re-download
by removing just that volume (databases and uploaded files are untouched):

```bash
docker compose stop web
docker compose volume rm heurist_heurist_support
docker compose up -d --build
```

**Database schema changes.** Heurist runs its database autoupgrade when the
application version is newer than the one a database was created with — simply
opening the database in the UI after updating triggers the upgrade prompt.
Brand-new databases are always created with the current schema.

### Persistence and resets

| What | Where | Survives `docker compose down` |
|---|---|---|
| All Heurist databases | named volume `heurist_db_data` | yes |
| Uploaded files | named volume `heurist_heurist_filestore` | yes |
| Support bundles | named volume `heurist_heurist_support` | yes |

Wipe everything back to a clean install:

```bash
docker compose down -v   # WARNING: deletes all databases and uploaded files
```

### Logs and database access

```bash
docker compose logs web          # Apache + PHP errors (error_log = stderr)
docker compose logs db
docker exec -it heurist-db-1 mysql -uheurist -pheurist hdb_yourdb
```

### Running the tests / API smoke check

With the stack up, the REST API is available at
`http://localhost:8080/heurist/api/{dbname}/...` (see
`hserv/controller/api.php`). Login for a JWT token:

```
POST http://localhost:8080/heurist/hserv/controller/auth.php
     {"username":"...","password":"...","db":"hdb_yourdb"}
```
