# Directory: export/dbbackup

## Overview
Scripts in this directory are used to set up and perform exports of Heurist database content. This typically involves generating full database dumps in HML (Heurist Markup Language - XML) format or as comprehensive ZIP archives which can include record data, structure information, and uploaded files. The `buildArchivePackagesCMD.php` script is a key command-line tool for creating these backup packages, often utilizing `flathml.php` from the `/export/xml/` directory. The `exportMyDataPopup.php` provides a user interface for administrators to initiate these exports.

## Key files
*   `buildArchivePackagesCMD.php`: Command-line utility to build database archive packages. Creates ZIP files containing database dumps (SQL, TSV, HML), uploaded files, and documentation. Designed for CLI use.
*   `exportMyDataPopup.php`: Provides a user interface for administrators to export database data. Allows export as HML, SQL dump, TSV files, or a complete ZIP/TAR archive (including uploaded files and documentation). Supports uploading archives to repositories like Nakala.

## Note
Although these functions are mostly Version 3 code, they have been modified to work with the Heurist 4 search interface. The primary mechanism involves packaging data for backup, transfer, or archival.

