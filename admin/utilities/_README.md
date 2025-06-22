# Directory: /admin/utilities

## Overview

This directory provides a collection of utility scripts for server and database administration, including tools for bulk emailing, cleaning up filestores, managing interaction logs, notifying users about archiving, and purging inactive database components or full-text indexes, as well as generating indexable web pages for databases.

## Key files

- `bulkEmailController.php`: Handles server-side logic for the bulk email utility.
- `bulkEmailMain.php`: Main user interface for the Heurist Bulk Email utility.
- `bulkEmailSystem.php`: Core logic for sending bulk emails in Heurist.
- `cleanupFoldersDBs.php`: Cleans up temporary files, logs, and other non-essential data from database filestore folders.
- `downloadInteractionLog.php`: Allows download of the user interaction log as a CSV file, with filtering options.
- `notifyDatabaseArchive.php`: Sends email notifications to database owners about archiving their databases.
- `purgeFullTextIndexes.php`: Removes full-text indexes from inactive databases.
- `purgeInactiveDBs.php`: Reports on or purges/archives inactive Heurist databases and their components.
- `writeIndexablePagePerDB.php`: Generates indexable HTML pages for each Heurist database.
