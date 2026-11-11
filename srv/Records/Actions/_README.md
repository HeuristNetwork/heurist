# Records/Actions

## Overview

Record actions over a set of records: the Explorer Actions tool (plan 33 in heurist-explorer,
`docs/development/33 Actions-Tool-and-Batch-Updates-Plan.md`). One background job type
`record-action` for every action, so a user runs one action at a time. A single record is
changed by `PATCH` or `DELETE /records/{id}` directly (no job; titles and calculated fields always run as a job).

Implemented: field values `field-add`, `field-replace`, `field-delete` (all values, or the values
that match a condition of the query format; replace with `addIfNone`). Every record is saved by
the field-level PATCH of plan 32 (`RecordSaveService`, one transaction per record, `force`), so
the PATCH rules stay: a record that would break them is skipped and reported, not written.
Phase 2 (plan 33 §8): `ownership` (PATCH of the header; unchanged records not written), `titles`
(`RecordSaveService::rebuildTitle`), `calculations` (`rebuildCalculated`) and `delete`
(`RecordDeleteService` with force). A delete of more than 10 records needs the server password
`$passwordForServerFunctions` (`DeleteGuard`, never stored); half of the database or more only
for the database owner; the confirmed ids are stored with the job.

Steps of the job: stage `scope` (ids of the scope by `Query/RecordScope`, at most `maxRecords`
of the Export settings, and their record types), then stage `records`. Stop and the time limit
are checked before every record. The report is the job result, also of a job that stops early
(`JobContext::setPartialResult`): the records before the current one stay changed.

## Key files

- `RecordActionJob.php` — the job type: `prepare()` checks the action and the field type, `run()` works.
- `RecordActionRequest.php` — parameters (action, scope, field, values, match, addIfNone) and the PATCH body.
- `FieldValueAction.php` — one record: field in the structure, PATCH, refusals mapped to skip reasons
  (`structure`, `rights`, `required`, `single_value`, `max`, `language_exists`) or errors.
- `RecordActionInterface.php` — one action on one record (outcome status / reason).
- `RecordWriteAction.php` — ownership, titles, calculations, delete.
- `DeleteGuard.php` — password and database-share rules of a delete.
- `RecordActionReport.php` — counts, value counts (field actions), id lists (10,000 each), first 100 error messages.

Test: `tests/RecordActionTest.php --write`.
