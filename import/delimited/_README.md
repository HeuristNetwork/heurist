# Directory: import/delimited

## Overview
This directory contains scripts and classes for importing data into Heurist from delimited text files, primarily CSV (Comma Separated Values) and TSV (Tab Separated Values). It supports importing various types of definitions such as Record Types, Detail Types (fields), and Terms, as well as record data and media file references.

## Key files

-   `importBase.js`: Base JavaScript class (`HImportBase`) providing common functionality for parsing and preparing data from CSV files for various definition imports (e.g., Record Types, Detail Types).

-   `importDefDetailTypes.js`: JavaScript class (`HImportDetailTypes`) for importing Detail Types (record fields) from CSV files.
-   `importDefDetailTypes.php`: UI form for importing Detail Types from CSV.

-   `importDefRecTypes.js`: JavaScript class (`HImportRecordTypes`) for importing Record Types from CSV files.
-   `importDefRecTypes.php`: UI form for importing Record Types from CSV.

-   `importDefTerms.js`: JavaScript class (`HImportTerms`) for importing vocabularies, terms, or term translations from CSV files.
-   `importDefTerms.php`: UI form for importing terms (and vocabularies/translations) from CSV.

-   `importFileData.js`: JavaScript class (`HImportFileData`) for bulk addition or replacement of metadata for already registered files using CSV.
-   `importFileData.php`: UI form for importing file metadata from CSV.

-   `importMedia.js`: JavaScript class (`HImportMedia`) for bulk registration of new external media files (specified by URLs) from CSV.
-   `importMedia.php`: UI form for importing (registering) media files from URLs listed in a CSV.

-   `importRecordsCSV.js`: Client-side JavaScript logic for the user interface used when importing records from CSV, TSV, or KML files.
-   `importRecordsCSV.php`: Wizard style (multi-steps) UI form for importing records from CSV files.
