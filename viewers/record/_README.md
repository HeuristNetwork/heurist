# Directory: /viewers/record

## Overview
Provides the user interface and logic for displaying Heurist record details. It handles different viewing contexts and manages the rendering of record information, including fields, relationships, and media.

## Key files
- `index.php`: Handles redirection for record viewing and file access. Determines the appropriate target script based on query parameters.
- `renderRecordData.php`: General viewer for Heurist records. Fetches and displays record details in various contexts (standard view, map popups, print).
- `viewRecord.php`: UI wrapper for viewing a Heurist record, typically embedding `renderRecordData.php` in an iframe.
