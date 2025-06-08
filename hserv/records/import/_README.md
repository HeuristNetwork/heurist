This directory contains files related to importing record data.

Specifically, this directory includes classes to work with import from CSV, JSON, HML (XML), and KML files.

Key files and their roles:
- `importParser.php`: Responsible for parsing source files (CSV, JSON, XML, KML) and saving the initial data into a temporary table.
- `importSession.php`: Manages sessions for imports, likely interacting with a session table like `sysImportFiles`.
- `importAction.php`: Handles the data once it's in a temporary table. This includes matching incoming data with existing records, assigning IDs, validating data, and finally creating or updating records.
- `ImportHeurist.php`: Facilitates inter-database imports, particularly using JSON or HML formats. This is often used via the `importRecords` user interface.
- `ImportAnnotations.php`: Likely handles the import of annotation data.

All these import-related classes are typically invoked and controlled by `hserv/controller/importController.php`.

(Content below was from the original _README.md)
Directory:    /hserv/records/import

Overview: This directory contains classes to work with import from csv, json, hml(xml) and kml files

importParser.php - parsing source file and save data into temp table
importSession.php - working with session table sysImportFiles
importAction.php - working with import data in temporary table: matching, assign idx, validation, create records

ImportHeurist.php - inter-database import via json or hml format (see user interface importRecords)

All classes above are called from controller: importController.php