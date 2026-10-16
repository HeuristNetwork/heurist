# Heurist client-module build/deployment

This package introduces one server-side orchestration script for independent client modules:

- `heurist-map`
- `heurist-mirador4`

## Server layout

```text
/var/www/html/HEURIST/
    heurist-map/                    # dedicated main-branch checkout
    heurist-mirador4/               # dedicated main-branch checkout
    h7-alpha/hclinet/bundles/
            heurist-map/            # deployed dist, no *.map files
            heurist-mirador4/       # deployed dist, no *.map files
```

## One-time prerequisites

1. Install Git, Node.js and npm on the reference server.
2. Node must satisfy the Vite version used by the projects. For the current `heurist-map` Vite 8 build, use Node 20.19+ or 22.12+.
3. Do **not** install Vite globally. `npm ci` installs the exact project-local Vite from `package-lock.json`.
4. Ensure the cron user can clone/fetch both repositories without interactive authentication.
5. Ensure `/var/www/html/HEURIST` is writable by the build user (or run the deployment cron as the existing server-management user/root).
6. Both repositories should commit `package-lock.json` and provide a normal `npm run build` command.
7. Both repositories must contain the `deploy:heurist` command and corresponding deployment script.

The orchestration script can create the two source checkout directories itself. You do not have to clone them manually first.

## Install the orchestration script

```bash
cp build_client_modules.sh /srv/scripts/build_client_modules.sh
chmod +x /srv/scripts/build_client_modules.sh
```

Run once manually:

```bash
/srv/scripts/build_client_modules.sh
```

Then inspect:

```bash
ls -la /var/www/html/HEURIST/h7-alpha/hclinet/bundles/heurist-map
ls -la /var/www/html/HEURIST/h7-alpha/hclinet/bundles/heurist-mirador4
```

## Cron

For a nightly build, for example at 02:30:

```cron
MAILTO="you@example.com"
30 2 * * * /srv/scripts/build_client_modules.sh
```

Routine output is written to `/var/log/heurist_client_modules.log`. The script emits cron output only on failure, so `MAILTO` receives build/deployment failures rather than a message every night.

## Environment overrides

The defaults can be overridden without editing the script:

```bash
HEURIST_ROOT=/var/www/html/HEURIST
HEURIST_CLIENT_DIST_ROOT=/var/www/html/HEURIST/h7-alpha/hclient/bundles
HEURIST_MAP_REPO=https://github.com/HeuristNetwork/heurist-map.git
HEURIST_MIRADOR4_REPO=https://github.com/HeuristNetwork/heurist-mirador4.git
HEURIST_CLIENT_BRANCH=main
```

For SSH repositories, set `HEURIST_MAP_REPO` / `HEURIST_MIRADOR4_REPO` to the corresponding `git@github.com:...` URL.

## Failure behavior

- Git/npm/build errors stop the run and return non-zero.
- `npm ci` requires the committed lock file, giving deterministic dependency installation.
- Each module deployment is staged in a sibling temporary directory before replacing the previous distribution.
- If staging/copy fails, the previous deployed distribution is left intact.
- `*.map` source-map files are excluded from the production support directory.
- A lock prevents overlapping cron/manual runs when `flock` is available.

## Adding another client module later

For a future module:

1. give it a standard `npm run build` command producing `dist/`;
2. add `scripts/deploy-<module>.mjs` and `deploy:heurist`;
3. add one repository definition plus `ensure_repository` / `build_and_deploy` calls to `build_client_modules.sh`.

At that point it may be worth converting the two hard-coded module calls into a small module table, but keeping two explicit entries is clearer for the present two-module setup.
