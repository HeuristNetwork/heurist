# Record editing

## Overview

Writes of records through the new API (plan 14, heurist-explorer
`docs/development/14 Record-Editing-Plan.md`): insert, update, duplicate and
delete, ported from `hserv/records/edit/recordModify.php`. For now only the
system record types of `WritableRecordTypes` are written (reports, report
schedules, map documents, map layers and their sources, query sources, website
home and pages); other record types keep the legacy editor and `recordSave`.

Routes (`Controller/RecordEditController.php`, wired in `hserv/controller/api.php`):

| Method | Path | Action |
|---|---|---|
| POST | `/records/{id}` | insert; id 0 = next id, negative = that id (import) |
| PUT | `/records/{id}` | update, the body replaces the whole record |
| PATCH | `/records/{id}` | update, only the fields of the body |
| DELETE | `/records/{id}[?force=1]` | delete; 409 with the references unless force |
| POST | `/records/{id}/duplicate` | copy |
| POST | `/records/{id}/validate` | checks of a save, nothing written |

## Write format

The body is the record object of `GET /records/{id}` (input = output):
`rec_*` headers and `details` keyed by field id, each an array of values.

- Writable headers: `rec_ID`, `rec_RecTypeID` (insert only), `rec_OwnerUGrpID`,
  `rec_NonOwnerVisibility`, `rec_NonOwnerVisibilityGroups`, `rec_EditGroups`,
  `rec_URL`, `rec_ScratchPad`, `rec_Modified` (concurrency token, 409 when it
  differs). Read-only headers of the GET output are ignored.
- Values: compact (term id, record id, ulf_ID, text, date or temporal JSON) or
  the resolved object form (`value`, `trm_ID`, `rec_ID`, `ulf_ID`); geo
  `{geo:{type,wkt}}` or WKT; `{value, hidden:true}` = `dtl_HideFromPublic`.
- Translations: further values with a language prefix (`fre:...`) on DT_NAME,
  DT_SHORT_SUMMARY and DT_EXTENDED_DESCRIPTION (not the content of a website page).
- The legacy recordSave format (headers without `rec_`, `t:NNN` keys) is refused.

`GET /records` (with `fields=_all`) gives editors of a record `rec_EditGroups`,
`rec_NonOwnerVisibilityGroups`, `rec_FlagTemporary` and hidden values as
`{value, hidden:true}`; long website page content is joined into one value
(`Records/Data/RecordDataService`).

## Key files

- `RecordInput.php` — parses a request body in the write format.
- `RecordValidator.php` — generic checks and preparation of detail values (port of
  `_prepareDetails`); no HTTP, permission or record type list, so batch actions and
  database checks can use it. Result: `ValidationResult` (issues and recDetails rows).
- `RecordPermissionService.php` — account state, ownership and edit groups
  (`userCheckPermissions`, `recordCanChangeOwnerwhipAndAccess`).
- `RecordSaveService.php` — insert/PUT/PATCH: ownership defaults, concurrency,
  workflow stage rules (no emails), one transaction, title.
- `RecordDeleteService.php` — references check (recLinks), forced delete.
- `RecordDuplicateService.php` — copy with "Copy of " name.
- Writes are `Store/RecordStore.php`, reads `Store/RecordRepository.php` (plan 15).
- `TitleMask.php` — title from the title mask of the record type (port of legacy
  `TitleMask::fill` only; reads through the access layer of plan 15).
- `RecordTitle.php` — title from DT_NAME, else "Record #NNN" (when the mask is not
  defined, has an error or gives no text).
- `CalculatedFields.php` — runs the Smarty formulas of calculated fields on save
  (srv Smarty engine, `$r` = the record), before the title.
- `WritableRecordTypes.php` — hardcoded list of writable record types.
- `RecordEditException.php` — HTTP status, error code, issues of a failed write.

## Not ported yet

Checking/converting title masks, calculated fields of other records that depend on
the saved one, ElasticSearch, HTML output cache, workflow emails,
parent/child pointers, relationship re-titling, file upload, batch save, guest
user rights. Test: `tests/RecordEditApiTest.php --db=NAME --user=ID [--write]`.
