# Directory: /admin/setup/dbupgrade

## Overview

This directory contains scripts and utilities for upgrading Heurist database schemas from one version to another. It includes core logic, batch upgrade tools, and specific version-to-version migration scripts.

## Key files

- `DBUpgrade.php`: Core database upgrade logic for Heurist. `doUpgradeDatabase` upgrades a database from any version to `HEURIST_MIN_DBVERSION`. In automatic mode (used by initPage.php and DBUpgradeAll.php) databases older than 1.3.14 are rejected and must be upgraded manually with `upgradeDatabase.php` (date index rebuild).
- `DBUpgradeAll.php`: Upgrades all Heurist databases on the server to schema version `HEURIST_MIN_DBVERSION` and reports databases that need manual update or failed.
- `DBUpgrade_0.0.0_to_1.0.0.sql`: SQL script for upgrading database schema from version 0.0.0 to 1.0.0.
- `DBUpgrade_1.0.0_to_1.1.0.sql`: SQL script for upgrading database schema from version 1.0.0 to 1.1.0.
- `DBUpgrade_1.0.0_to_1.1.0_old.sql`: Older SQL script for upgrading database schema from version 1.0.0 to 1.1.0.
- `DBUpgrade_1.1.0_to_1.2.0.sql`: SQL script for upgrading database schema from version 1.1.0 to 1.2.0.
- `DBUpgrade_1.1.0_to_1.2.0_dbs.sql`: SQL script for upgrading all databases from schema version 1.1.0 to 1.2.0.
- `DBUpgrade_1.1.0_to_1.2.0_old.sql`: Older SQL script for upgrading database schema from version 1.1.0 to 1.2.0.
- `DBUpgrade_1.2.0_to_1.3.0.php`: PHP script for upgrading database schema from version 1.2.0 to 1.3.0, including table modifications and additions.
- `DBUpgrade_1.2.0_to_1.3.0.sql`: SQL script for upgrading database schema from version 1.2.0 to 1.3.0.
- `DBUpgrade_1.3.0_to_1.3.19.php`: PHP script for upgrading database schema from version 1.3.0 to 1.3.19, including table modifications and additions.
- `DBUpgrade_1.4.php`: PHP script for upgrading database schema from version 1.3.19 to 1.4.0 (origin identity and sync state for records and files).
- `DBUpgrade_1.5.0_to_1.5.1.sql`: Holding pen of ideas for future structure upgrades - not executed.
- `upgradeDatabase.php`: User interface for manual (verbose) upgrade of a single Heurist database; calls `doUpgradeDatabase` in manual mode.
