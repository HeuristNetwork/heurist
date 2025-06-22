# Directory: /admin/setup/dbupgrade

## Overview

This directory contains scripts and utilities for upgrading Heurist database schemas from one version to another. It includes core logic, batch upgrade tools, and specific version-to-version migration scripts.

## Key files

- `DBUpgrade.php`: Core database upgrade logic for Heurist.
- `DBUpgradeAll.php`: Upgrades all Heurist databases on the server to schema version 1.3.
- `DBUpgrade_0.0.0_to_1.0.0.sql`: SQL script for upgrading database schema from version 0.0.0 to 1.0.0.
- `DBUpgrade_1.0.0_to_1.1.0.sql`: SQL script for upgrading database schema from version 1.0.0 to 1.1.0.
- `DBUpgrade_1.0.0_to_1.1.0_old.sql`: Older SQL script for upgrading database schema from version 1.0.0 to 1.1.0.
- `DBUpgrade_1.1.0_to_1.2.0.sql`: SQL script for upgrading database schema from version 1.1.0 to 1.2.0.
- `DBUpgrade_1.1.0_to_1.2.0_dbs.sql`: SQL script for upgrading all databases from schema version 1.1.0 to 1.2.0.
- `DBUpgrade_1.1.0_to_1.2.0_old.sql`: Older SQL script for upgrading database schema from version 1.1.0 to 1.2.0.
- `DBUpgrade_1.2.0_to_1.3.0.php`: PHP script for upgrading database schema from version 1.2.0 to 1.3.0, including table modifications and additions.
- `DBUpgrade_1.2.0_to_1.3.0.sql`: SQL script for upgrading database schema from version 1.2.0 to 1.3.0.
- `DBUpgrade_1.3.0_to_1.3.14.php`: PHP script for upgrading database schema from version 1.3.0 to 1.3.14 (and potentially up to 1.3.18), including table modifications and additions.
- `DBUpgrade_1.3.0_to_1.4.0.sql`: SQL script for upgrading database schema from version 1.3.0 to 1.4.0.
- `upgradeDatabase.php`: Manages the user interface and process for upgrading a single Heurist database.
