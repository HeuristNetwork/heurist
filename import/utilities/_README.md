# Directory: import/utilities

## Overview
This directory provides general utility scripts for various import-related tasks, such as importing records from Heurist-specific XML/JSON formats and managing large file uploads.

## Key files
- **importRecords.js**: Provides the client-side JavaScript logic for the interface that handles importing records from Heurist HML (Heurist Markup Language - XML) or JSON formatted files.
- **importRecords.php**: UI form for importing records from HML or JSON files. This is typically used for transferring data between Heurist databases.
- **manageFilesUpload.php**: script that provides an interface for uploading single or multiple files, including large files, to designated server directories (e.g., a scratchspace). This is often a preliminary step before other import processes that operate on files already on the server.
