# Directory: hserv/structure/import

## Overview
This directory is responsible for scripts and functionalities related to importing Heurist database structure definitions. This might involve parsing structure files (e.g., XML, SQL) and applying them to the current database.

## Key files
- `dbsImport.php`: Handles the import of database structure definitions from another Heurist database, managing dependencies and concept code resolution.
- `importDefintions.php`: Imports a complete set of Heurist database structure definitions from a specially formatted SQL file (e.g., coreDefinitions.txt).
