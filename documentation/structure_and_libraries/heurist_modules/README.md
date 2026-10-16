# Directory: /documentation/structure_and_libraries/heurist_modules

## Overview

This directory documents independent Heurist client-side modules developed by the Heurist team from July 2026.

These modules isolate significant blocks of client-side functionality from the main Heurist codebase. Each module has its own source repository, dependencies, tests and build process, and is compiled into production-ready JavaScript/CSS for use by Heurist.

The modules communicate with the main Heurist application through stable integration boundaries:

- the Heurist OpenAPI for database and presentation data;
- a small Heurist wrapper/host bridge where integration with the main client application is required, for example runtime configuration, session-aware behaviour, events, preferences or editing callbacks.

Keeping these modules independent avoids mixing their dependency trees and build tooling into the main Heurist repository, while still allowing them to be used as normal Heurist components.

As of August 2026 there are two independent modules:

- `heurist-map`
- `heurist-mirador4`

Further client-side modules are expected to follow the same model.

## Source repositories

Editable source code is maintained in separate repositories under the `HeuristNetwork` GitHub organisation.

On HeuristRef.net the source checkouts are:

```text
/var/www/html/HEURIST/heurist-map
/var/www/html/HEURIST/heurist-mirador4
```

The `main` branches of these repositories are synchronized and built automatically on the reference server.

The compiled distributions are not edited directly.

## Build and deployment on HeuristRef.net

`build_client_modules.sh`, located in:

```text
/server_management/server_synchronisation/heurist_modules/
```

synchronizes the module repositories, installs their npm dependencies, builds them, and deploys their compiled `dist/` output into the current alpha Heurist codebase:

```text
/var/www/html/HEURIST/h7-alpha/hclient/bundles/
```

with one subdirectory per module:

```text
/var/www/html/HEURIST/h7-alpha/hclient/bundles/heurist-map
/var/www/html/HEURIST/h7-alpha/hclient/bundles/heurist-mirador4
```

The build uses each module's committed `package-lock.json` and project-local dependencies. Vite is installed by `npm ci`; it is not installed globally.

The reference-server build is intended to run automatically, so deployment does not depend on a developer remembering to compile and copy files manually.

## Use by Heurist

The generated files in `/hclient/bundles/<module>` are the runtime distributions used by the Heurist client.

The editable module source code remains in the separate repositories. The generated bundle directories are deployment artefacts and are not the authoritative source for development.

This keeps the source projects independent while presenting the compiled modules as an integral part of the Heurist client codebase at runtime.

## Distribution to other Heurist servers

The standard `update_heurist.sh` script in the HeuristRef.net distribution area synchronizes Heurist code from the reference server to other Heurist installations.

Because the compiled client modules are deployed under:

```text
hclient/bundles/
```

they are synchronized as part of the normal Heurist code update. No separate client-module installation step is required on ordinary Heurist servers.

This is intentional: although the modules are developed independently, their compiled runtime distributions evolve together with the Heurist client code.

Third-party external libraries such as Leaflet remain subject to the existing `update_heurist.sh` rules for external dependencies and may be treated differently when a code-only update is requested.

## Repository access and automated builds

The reference server uses GitHub SSH access for the module repositories:

```text
git@github.com:HeuristNetwork/heurist-map.git
git@github.com:HeuristNetwork/heurist-mirador4.git
```

`build_client_modules.sh` can create the source checkout directories itself. On the first run it clones the repositories; on later runs it synchronizes them to `origin/main`.

For each module the standard build contract is:

```text
npm ci
npm run deploy:heurist
```

Each module-specific deployment script appends its module name to the distribution root supplied by `build_client_modules.sh`.

The current default distribution root is:

```text
/var/www/html/HEURIST/h7-alpha/hclient/bundles
```

## Maintenance model

The important compatibility boundary is the API/integration contract, not the internal implementation of a client module.

For example, if a new MapLayer property is introduced, the corresponding Heurist server-side output and `heurist-map` must be updated consistently. Internal changes within `heurist-map` do not require changes to the main Heurist client unless the wrapper/public integration contract itself changes.

The intended dependency direction is:

```text
Heurist server/API and host integration contract
        ↓
independent client module
        ↓
compiled runtime distribution in hclient/bundles
```

This minimizes backward interference with the main Heurist code while still allowing the modules to evolve with changes in Heurist database structures and APIs.

## Build documentation

For the detailed reference-server build and deployment process, see:

```text
/server_management/server_synchronisation/heurist_modules/README.md
```
