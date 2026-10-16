# Directory: hserv/report

## Overview
This directory contains files related to generating and managing reports within the Heurist system using Smarty templating engine 

These files handle tasks such as:
- Initializing the Smarty templating engine for use in report generation.
- Executing report definitions to produce output.
- Formatting individual records for inclusion in reports.
- Managing report templates files.

## Key files
- `ReportExecute.php`: Executes reports defined by Smarty templates, fetching data, processing through Smarty, and handling various output modes.
- `ReportRecord.php`: Serves as a data provider and formatting helper for Smarty templates, providing methods to access Heurist record data.
- `ReportTemplateMgr.php`: Manages Smarty template files for Heurist reports, including listing, retrieving, saving, deleting, and import/export.
- `smartyInit.php`: Functions to initialize the Smarty engine and register Heurist-specific Smarty modifiers.
- `SmartyReportRenderer.php`: Implements `Heurist\Reports\ReportRendererInterface` with the legacy engine for the modern `/api/{db}/reports` and `/api/{db}/jobs` endpoints (single-record render, test runs and generated files, import/export conversion). The new API runs reports with the srv engine; this renderer still converts templates on import/export and is run by the engine comparison tests. The job types themselves are in `srv/Reports/Jobs`.
- A port of this engine is in `srv/Reports/Smarty` (plan 12, Phase 6). The new API (`/api/{db}/reports`, `/api/{db}/jobs`, cron schedules) always uses it; legacy URLs, CMS widgets and calculated fields use the code here. `SmartyReportRenderer` is still used by the new API for template import/export.
- `ReportRecordWriter.php`: Implements `Heurist\Reports\ReportRecordWriterInterface`: creates/deletes Custom Report records, imports the report record types from Heurist_Core_Definitions and converts `usrReportSchedule` rows into Report Schedule records.

- `debug.tpl`: Smarty template for rendering debugging information.
- `debug_html.tpl`: Smarty template for rendering debugging information.
- `template.tpl`: Smarty template file for report presentation or structure.
