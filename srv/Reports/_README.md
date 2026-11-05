# Reports

## Overview

Reports manager for Smarty report templates (plan 12 in heurist-explorer,
`docs/development/12 DONE Smarty-Reports-Plan.md`). A report is a Custom Report
record (`RT_CUSTOM_REPORT` 2-1104: name, description, template file name
`DT_FILE_NAME` 2-62, card flag `DT_IS_CARD_VIEW` 2-1182) whose template body
stays in `smarty-templates/*.tpl`. Report Schedule records (`RT_REPORT_SCHEDULE`
2-1105) point to a report (`DT_REPORT`) and have their own query without parameters
(`DT_QUERY_STRING`). The generated file of a schedule always starts with the name of the
report (`ReportService::scheduleOutput`, at generation; the record keeps any value).
Template files without a record are "unregistered".

Report and schedule records are written by the common record services
(`/records/{id}`, `/report/{id}`, `/schedule/{id}`); `ReportEntityHandler.php` adds the
template file rules and refuses a schedule query with parameters.
`ReportRecordWriterInterface` (`hserv\report\ReportRecordWriter`) only installs the
definitions and converts `usrReportSchedule`.

Smarty runs only through `ReportRendererInterface`. Two engines
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

- `ReportService.php` — list, template text, import, export, render, generated files.
- `ReportRepository.php` — report and schedule records with visibility rules.
- `ReportTemplateStore.php` — .tpl files: list, read, write, delete, safe names.
- `ReportPolicy.php` — logged-in/manager checks; database setting `Reports`
  (`settings/reports.json`, `allowDynamicReports`, default true when missing;
  also time limits, `maxJobs` and `generateMaxRecords`).
- `ReportRendererInterface.php` — single-record render, test runs and generated
  output on record ids (`renderIds`), import/export conversion.
- `Jobs/` — job types `report-preview` and `report-generate` (either engine).
- `Smarty/` — the srv Smarty engine; see `Smarty/_README.md`.
- `ReportEntityHandler.php` — hooks of the report and schedule entities (plan 28).
- `ReportRecordWriterInterface.php` — install definitions.
- `ReportJobPlanner.php` — validation of the report jobs (`report-preview`,
  `report-generate`), record ids of a query, due schedules for cron.
