# Directory: /admin/verification

## Overview

This directory contains a suite of scripts for database verification, integrity checking, data repair, and rebuilding various components. Tasks include checking URLs, saved searches, XHTML validity, managing duplicate records, converting field types, verifying file linking, finding orphaned items, fixing CMS paths, listing user accounts, and rebuilding/repairing titles, calculated fields, entry masks, and search indices.

## Key files

- `checkRecURL.php`: Verifies the validity of URLs stored in Heurist records.
- `checkSavedSearches.php`: Validates saved searches across all databases on the server.
- `checkXHTML.php`: Validates XHTML content in WYSIWYG fields.
- `combineDuplicateRecords.php`: Merges a given list of duplicate records into a single master record.
- `convertTextFieldToFileField.php`: Converts a specified text field type to a file field type across databases.
- `fieldsWithIndividualTermSelection.php`: Reports on enumeration fields using individual term selection versus vocabulary-based selection.
- `fileLinkingError.php`: Checks for errors in uploaded file records and paths.
- `findMissedFileFolder.php`: Identifies missing or non-writable Heurist filestore directories.
- `findOrphanedFileFolder.php`: Identifies filestore directories that do not have a corresponding database.
- `fixCmsAbsPaths.php`: Replaces absolute paths with relative paths in Heurist CMS records.
- `listUploadedFilesErrors.php`: Displays a detailed report and repair interface for file linking errors in a specific database.
- `listUploadedFilesMissed.php`: Reports missing physical files for registered uploaded files.
- `listUserAccounts.php`: Lists all user accounts across all databases on the server.
- `longOperationInit.php`: Wrapper for initiating long-running verification/rebuild operations.
- `orderLinksByTitle.php`: Reorders multi-valued record pointer fields alphabetically by the title of the pointed-to records.
- `rebuildCalculatedFields.php`: Rebuilds calculated fields for specified or all records.
- `rebuildEntryMasks.php`: Re-applies field entry masks to record detail values.
- `rebuildLuceneIndices.php`: Rebuilds all Lucene (Elasticsearch) indices for the current database.
- `rebuildRecordTitles.php`: Rebuilds constructed record titles based on their title masks.
- `repairUploadedFiles.php`: Server-side script to repair uploaded file entries.
- `verifyConceptCodes.php`: Checks for duplicate concept codes within each database.
- `verifyConceptCodes2.php`: Checks for missing `xxx_IDInOriginatingDB` values in definitions.
- `verifyConceptCodes3.php`: Checks for definitions lacking concept codes in registered databases.
- `verifyFieldTypes.php`: Library of functions to validate field definitions and term configurations.
- `verifyForOrigin.php`: Verifies database structures against their original definitions from core databases.
- `verifyInstallation.php`: Checks server environment and PHP extension requirements for Heurist.
- `verifyScripts.php`: A collection of administrative scripts for data and database structure verification and correction.
- `verifyValue.php`: Library of static functions for validating Heurist data values.
