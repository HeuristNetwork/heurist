# Directory: export/publish

## Overview
This directory contains scripts related to managing and publishing scheduled reports. The core functionality revolves around the `usrReportSchedule` database table, allowing users to define reports (often based on Smarty templates and Heurist queries) that can be generated periodically or on demand.

## Key files
*   `loadReports.php`: AJAX backend that handles CRUD operations (Create, Read, Update, Delete) for report schedules stored in the `usrReportSchedule` table.
*   `manageReports.html`: Provides the main user interface (using jQuery DataTables) for listing, searching, and initiating actions on scheduled reports (e.g., edit, delete, run).
*   `manageReports.js`: Client-side JavaScript for `manageReports.html`. Initializes the DataTable, handles UI interactions, and communicates with `loadReports.php`.
*   `editReportSchedule.html`: HTML structure for the pop-up form used to create or edit the details of a specific report schedule.
*   `editReportSchedule.js`: Client-side JavaScript for `editReportSchedule.html`. Manages form population, validation, and saving data via `loadReports.php`.

## Note
The comment "To be replaced with widgets/entity/manageUsrReportSchedule class" indicates that this module is planned for a future rewrite or integration into a more modern widget-based architecture within Heurist. Although these functions are mostly Version 3 code, they have been modified to work with the Heurist 4 search interface.
