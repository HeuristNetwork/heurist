# Directory: /viewers/smarty

## Overview
Primarily for backward capability, this directory contains scripts related to Smarty template-based report generation, display, and publishing. Modern report functionality is generally found in `/hserv/report` and `/hclient/widgets/report`. Smarty templates offer an alternative to XSL templates and can be edited via the Heurist web interface.

## Subfolders
- None

## Key files
- `index.php`: Main entry point for Smarty-based report viewing and file access. Acts as a router to file handlers or the `ReportController`.
- `showReps.php`: Invokes the `ReportController` for displaying reports (primarily for backward compatibility).
- `updateReportOutput.php`: Handles updating and publishing of Smarty-based reports, delegating to `ReportController` (primarily for backward compatibility).
