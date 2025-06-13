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

- `debug.tpl`: Smarty template for rendering debugging information.
- `debug_html.tpl`: Smarty template for rendering debugging information.
- `template.tpl`: Smarty template file for report presentation or structure.
