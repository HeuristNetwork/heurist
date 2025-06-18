
# Directory: /hclient/widgets/record

## Overview

This directory contains a collection of widgets and associated HTML templates that provide various functionalities for interacting with and managing individual records or sets of records within the Heurist system. These actions include editing record access rights, adding new records, linking records, exporting data, managing tags and bookmarks, and finding duplicates.

## Key files

-   **recordAccess.html**: HTML structure for the widget that manages record ownership and access rights.
-   **recordAccess.js**: jQuery UI widget for managing record access and ownership, allowing changes to who owns a record and visibility settings.
-   **recordAction.html**: Basic HTML layout for widgets that extend `recordAction`, providing placeholders for scope selection, results, and progress.
-   **recordAction.js**: Base jQuery UI widget for actions performed on a scope of records, handling common functionalities like record scope selection and progress display.
-   **recordAdd.js**: jQuery UI widget for adding new records, with options for setting default parameters like record type, ownership, and access.
-   **recordAddButton.js**: jQuery UI widget that creates a simple button to add a new record, often used in CMS contexts with pre-configured defaults.
-   **recordAddLink.html**: HTML structure for the widget used to create links or relationships between records.
-   **recordAddLink.js**: jQuery UI widget for creating links (pointer fields) or relationships (relation records) between records.
-   **recordAddLinkMatch.html**: HTML structure for the widget used to create links by matching field values (Foreign Key matching).
-   **recordAddLinkMatch.js**: jQuery UI widget for creating links between records by matching values in specified text fields (Foreign Key style).
-   **recordArchive.html**: HTML structure for the widget used to find and restore archived records.
-   **recordArchive.js**: jQuery UI widget for looking up and restoring records from the system archive based on criteria like record ID, user, or date.
-   **recordBookmark.js**: jQuery UI widget for removing bookmarks and detaching associated personal tags from a selection of records.
-   **recordDataTable.html**: HTML structure for the widget used to configure visible columns for DataTables.
-   **recordDataTable.js**: jQuery UI widget for configuring columns to be displayed in a DataTable for a specific record type, including selection of fields and their properties.
-   **recordDelete.html**: HTML structure for the widget used for deleting records, including warnings and progress display.
-   **recordDelete.js**: jQuery UI widget for managing the deletion of a selected scope of records, with confirmations and handling of linked records.
-   **recordExport.html**: HTML structure for the widget used for exporting records to various formats.
-   **recordExport.js**: jQuery UI widget for exporting records to formats like XML, JSON, KML, or HML, preparing and initiating the download.
-   **recordExportCSV.html**: HTML structure for the widget used for configuring and exporting records to CSV.
-   **recordExportCSV.js**: jQuery UI widget for exporting records to CSV or other delimited text files, allowing field selection and format configuration.
-   **recordFindDuplicates.html**: HTML structure for the widget used to find and manage duplicate records.
-   **recordFindDuplicates.js**: jQuery UI widget for finding duplicate records based on selected fields and Levenshtein distance, with options to merge or ignore duplicates.
-   **recordImportAnnotations.html**: HTML structure for the widget used for importing annotations from IIIF manifests.
-   **recordImportAnnotations.js**: jQuery UI widget for importing annotations from registered IIIF manifests into Heurist annotation records.
-   **recordNotify.js**: jQuery UI widget for sending email notifications about a set of records, using the `usrReminders` widget for composition.
-   **recordRate.js**: jQuery UI widget for assigning a star rating (0-5 stars) to a scope of records via their bookmarks.
-   **recordTag.js**: jQuery UI widget for assigning or removing tags from records, or for selecting tags (e.g., for bookmarking).
-   **recordTemplate.html**: HTML structure for the widget used to generate downloadable CSV template files (header row only).
-   **recordTemplate.js**: jQuery UI widget for creating downloadable CSV template files (header row only) for a specific record type.
-   **recordTitles.js**: jQuery UI widget to trigger the server-side rebuilding of record titles based on defined patterns.
-   **recordUploadedFilesIndex.html**: HTML structure for the widget used for indexing uploaded files from server media folders.
-   **recordUploadedFilesIndex.js**: jQuery UI widget for indexing uploaded files from server media folders into the `recUploadedFiles` entity.
```
