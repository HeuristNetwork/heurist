# Directory: /admin/setup

## Overview

This directory contains functions used in setting up new databases.

## Subfolders

- `dbcreate/`: This directory holds SQL scripts and definition files for creating the fundamental structure, functions, procedures, triggers, and core definitions of a new Heurist database.
- `dboperations/`: This directory contains scripts for various database operations, including daily maintenance (cron jobs), database deletion, resetting demo databases, and managing welcome/notification emails.
- `dbproperties/`: This directory includes scripts for retrieving database-specific properties, such as version information and registered URLs.
- `dbupgrade/`: This directory contains scripts and utilities for upgrading Heurist database schemas from one version to another. It includes core logic, batch upgrade tools, and specific version-to-version migration scripts.
- `iconLibrary/`: This directory serves as a library for icons used for new record types.

## Key files

- `.htaccess_for_filestore`: This file determines access in the HEURIST_FILESTORE directory (and descendants)
- `.htaccess_via_url`: This file overrides default barring of access via URL to listing and files in the HEURIST_FILESTORE directory (and descendants).
