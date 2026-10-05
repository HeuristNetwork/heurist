# Reports/Smarty

## Overview

The srv Smarty report engine (plan 12, Phase 6 in heurist-explorer,
`docs/development/12 Smarty-Reports-Plan.md`). It is a port of the legacy engine
in `hserv/report` (`ReportExecute`, `ReportRecord`, `smartyInit.php`) on top of
the srv data services. Templates written for the legacy engine run unchanged:
the same `$heurist` methods, the same record arrays (`$r.f12`, `$r.f12s`,
`$r.f12_originalvalue`, `recTitle`, ...), the same modifiers and `{wrap}`/`{out}`.

Both engines run side by side. The new API always uses this engine
(`Runtime\ServiceFactory::reportRenderer()`): `/api/{db}/reports/{id}/render`, the `report-preview` and
`report-generate` jobs (`Reports/Jobs`) and cron schedules
(`runReportSchedules.php`). Legacy URLs (`?template=`, `snippet=1`, CMS
widgets, viewers/smarty) and calculated fields keep the hserv engine.

The engine has its own compile folder `smarty-templates/compiled-srv/`, so a
template compiled by one engine is never used by the other. Template bodies of
the editor run as Smarty `string:` resources (no temporary file).

Records are loaded in batches (`Records\Data\RecordDataService`); only records
the current user may view are used.

Compatibility test: `php tests/ReportEngineCompareTest.php --db=NAME` compares
record arrays, every template of the database and a template that uses the
whole `$heurist` API. On osmak_mapping (2026-10-05) all outputs are the same.

## Differences from the legacy engine (fixed on purpose)

- Only records the user may view are returned (`getRecord` gives null, related
  and linked records are left out); the legacy engine loaded any record.
- `|translate` translates terms and file captions (the legacy modifier looked
  for a global `$smarty` and always gave the untranslated text).
- txt/csv/xml/json output keeps the content of `<body>` (the legacy regular
  expression had an invalid modifier and did nothing).
- `getRecord($id)` after `getRecord($id, false)` returns the full record.
- `{wrap}` adds "px" to a numeric width/height.
- `composeRecLink` returns the link instead of printing it (same output).
- An error in a template (also a PHP TypeError) gives an error message; the
  legacy engine caught only `\Exception`, so a TypeError stopped the whole request.

## Key files

- `SrvSmartyRenderer.php` — `ReportRendererInterface` of this engine; import and
  export of templates are passed to the legacy renderer (`ReportTemplateMgr`).
- `SmartyTemplateRunner.php` — runs a template (file or body) on record ids:
  purpose preview, file or render; error levels 0-3; returns output and error.
- `SmartyEngineFactory.php` — Smarty instance: folders, security policy, PHP
  functions as modifiers, Heurist modifiers, `{wrap}`, `{out}`, term prefilter
  `{123 \fre \eng}`.
- `ReportSecurityPolicy.php` — the security policy of templates.
- `TemplateApi.php` — the `$heurist` object (getRecord, getRelatedRecords, ...).
- `ReportRecordAssembler.php` — loads records and builds the template arrays.
- `ReportDefinitions.php` — record types, fields, structures, terms,
  translations, concept codes.
- `TemplateModifiers.php` — `|translate`, `|arraysortby`, sorting modifiers.
- `ValueFormatter.php` — `{wrap}` and `{out}`, file players.
- `OutputSanitizer.php` — fonts, HTMLPurifier (no JavaScript unless the
  database is authorised), record links, js mode.
- `ReportEnvironment.php` — installation values (URLs, folders, settings,
  user), filled by `ServiceFactory` from the legacy System.
- `LanguageCodes.php`, `ReportText.php` — language prefixes, text sanitizing.
- `debug_html.tpl` — Smarty debug console (error level 3).
