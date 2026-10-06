# Reports

## Overview

Reports manager for Smarty report templates (plan 12 in heurist-explorer,
`docs/development/12 Smarty-Reports-Plan.md`). A report is a Custom Report
record (`RT_CUSTOM_REPORT` 2-1104: name, description, template file name
`DT_FILE_NAME` 2-62, card flag `DT_IS_CARD_VIEW` 2-1182) whose template body
stays in `smarty-templates/*.tpl`. Report Schedule records (`RT_REPORT_SCHEDULE`
2-1105) point to a report (`DT_REPORT`) and a Query Source (`DT_DATA_SOURCE`).
Template files without a record are "unregistered".

Smarty runs only through `ReportRendererInterface`; record writes through
`ReportRecordWriterInterface` (`hserv\report\ReportRecordWriter`). Two engines
implement the renderer: the legacy one (`hserv\report\SmartyReportRenderer`) and
the srv one (`Smarty/`, plan 12 Phase 6). The new API always uses the srv engine
(`Runtime\ServiceFactory::reportRenderer()`, called in `hserv/controller/api.php`
and `runReportSchedules.php`); the legacy renderer only converts templates on
import/export.

HTTP routes: see `Controller/ReportController.php`. The list (`GET /reports`)
gives logged-in users `settings`: `javaScriptAllowed` (the database is in
`js_in_database_authorised.txt`; otherwise scripts and style blocks are removed
from report output) and `testRecordLimit`.

## Key files

- `ReportService.php` — list, create, save, register, delete, import, export, render.
- `ReportRepository.php` — report and schedule records with visibility rules.
- `ReportTemplateStore.php` — .tpl files: list, read, write, delete, safe names.
- `ReportPolicy.php` — logged-in/manager checks; database setting `Reports`
  (`settings/reports.json`, `allowDynamicReports`, default true when missing;
  also time limits, `maxJobs` and `generateMaxRecords`).
- `ReportRendererInterface.php` — single-record render, test runs and generated
  output on record ids (`renderIds`), import/export conversion.
- `Jobs/` — job types `report-preview` and `report-generate` (either engine).
- `Smarty/` — the srv Smarty engine; see `Smarty/_README.md`.
- `ReportRecordWriterInterface.php` — create/delete records, install definitions.
- `ReportJobPlanner.php` — validation of the report jobs (`report-preview`,
  `report-generate`), record ids of a query, due schedules for cron.
