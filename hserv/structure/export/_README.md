# Directory: hserv/structure/export

## Overview
This directory is dedicated to scripts that export the Heurist database structure into different formats, such as SQL or XML. This is useful for backups, migrations, or external analysis of the database schema.

## Key files
- `getDBStructureAsSQL.php`: Returns database definitions (record types, detail types, etc.) as SQL INSERT statements.
- `getDBStructureAsXML.php`: Returns database definitions (record types, detail types, etc.) as XML (Heurist Markup Language - HML).
