This directory contains files related to generating and managing reports within the Heurist system
using Smarty templating engine 

These files handle tasks such as:
- Initializing the Smarty templating engine for use in report generation.
- Executing report definitions to produce output.
- Formatting individual records for inclusion in reports.
- Managing report templates files.

Key PHP files:
- `ReportExecute.php`: Responsible for the overall execution of a report.
- `ReportRecord.php`: Data provider and formatting helper for Smarty templates
- `ReportTemplateMgr.php`: Manages publisher's templates within HEURIST_SMARTY_TEMPLATES_DIR (subfolder of database storage folder)
- `smartyInit.php`: Initializes the Smarty templating engine, which is used to render reports based on templates and data.

`debug.tpl`, `debug_html.tpl` are Smarty templates used for rendering report outputs or debugging information.
`template.tpl` - simple sample template
