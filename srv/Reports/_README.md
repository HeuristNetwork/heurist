# Reports

## Overview

Reports manager for Smarty report templates (plans 12 and 30 in heurist-explorer,
`docs/development/`). The API names reports and generated reports by record id only (plan 30).
A report is a Custom Report
record (`RT_CUSTOM_REPORT` 2-1104: name, description, template file name
`DT_FILE_NAME` 2-62, card flag `DT_IS_CARD_VIEW` 2-1182) whose template body
stays in `smarty-templates/*.tpl`, under a name set once when the record is created.
An output (`RT_REPORT_SCHEDULE` 2-1105, entity `output`, UI Generated report) points to a report
(`DT_REPORT`) and has its own query without parameters (`DT_QUERY_STRING`), language and interval
(0: on request only). Its file is `generated-reports/{record id}.{format}` (`ReportOutputStore.php`).
Template files without a record are "unregistered": they do not run until they are registered.

Report and output records are written by the common record services
(`/records/{id}`, `/report/{id}`, `/output/{id}`); `ReportEntityHandler.php` adds the
actions (`asset`, `execute`, `export`, `import`, `setup`) and the rules: the template file name, a
template with outputs is not deleted, a copy gets its own file, an output query has no parameters.
`ReportRecordWriterInterface` (`hserv\report\ReportRecordWriter`) only installs the
definitions and converts `usrReportSchedule`.

Smarty runs only through `ReportRendererInterface`. Two engines
implement the renderer: the legacy one (`hserv\report\SmartyReportRenderer`) and
the srv one (`Smarty/`, plan 12 Phase 6). The new API always uses the srv engine
(`Runtime\ServiceFactory::reportRenderer()`, called in `hserv/controller/api.php`
and `runReportSchedules.php`); the legacy renderer only converts templates on
import/export.

HTTP routes: see `ReportEntityHandler.php`. `GET /report/setup`
gives logged-in users `settings`: `javaScriptAllowed` (the database is in
`js_in_database_authorised.txt`; otherwise scripts and style blocks are removed
from report output) and `testRecordLimit`.

## Key files

- `ReportService.php` — state, template text, import, export, one record as HTML.
- `ReportRepository.php` — report and output records with visibility rules.
- `ReportOutputStore.php` — generated files by the id of the output.
- `ReportTemplateStore.php` — .tpl files: list, read, write, delete, safe names.
- `ReportPolicy.php` — logged-in/manager checks; database setting `Reports`
  (`settings/reports.json`, `allowDynamicReports`, default true when missing;
  also time limits, `maxJobs` and `generateMaxRecords`).
- `ReportRendererInterface.php` — single-record render, test runs and generated
  output on record ids (`renderIds`), import/export conversion.
- `Jobs/` — job types `report-preview` and `report-generate` (either engine).
- `Smarty/` — the srv Smarty engine; see `Smarty/_README.md`.
- `ReportEntityHandler.php` — hooks and actions of the entities report and output (plan 30).
- `ReportRecordWriterInterface.php` — install definitions.
- `ReportJobPlanner.php` — validation of the report jobs (`report-preview`,
  `report-generate`), record ids of a query, due outputs for cron.
