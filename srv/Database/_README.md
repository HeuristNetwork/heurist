# Database

## Overview

This folder is the database boundary for the modern, read-only record workflow.
Application classes depend on `DatabaseInterface` and never on PDO or mysqli.
The current implementation targets the existing MySQL/MariaDB Heurist schema.

## Key files

- `DatabaseInterface.php` — minimal parameterised read contract.
- `MysqlDatabase.php` — MySQL-specific implementation for the current
  Heurist database configuration.
- `AbstractDatabase.php` — shared PDO logic used by the concrete providers.
- `DatabaseException.php` — database failure reported at the API boundary.
