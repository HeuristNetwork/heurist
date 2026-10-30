# Records

## Overview

This folder contains the record pipeline: search, expansion, data retrieval,
presentation, map feature generation, future aggregation and export, and
record editing of system record types (`Edit/`, plan 14).

## Key files

- `Query/RecordSearchService.php` — top-record search.
- `Expansion/ExpansionEngine.php` — linked-record traversal.
- `Data/RecordDataService.php` — batched record values.
- `Presentation/PresentationRecordRepository.php` — direct definition records.
- `Map/MapFeatureService.php` — GeoJSON feature production.
- `Edit/RecordSaveService.php` — insert and update of records (see `Edit/_README.md`).
- `Entity/` — record-based entities `/{entity}/{id}/{action}`: registry, hooks and actions of a
  record type (plan 27d, see `Entity/_README.md`).
- `Store/` — the record access layer (plan 15, heurist-explorer
  `docs/development/15 DONE Data-Access-Layer-Plan.md`), see below.
- `Files/FileRepository.php` — the only reader of recUploadedFiles and
  defFileExtToMimetype: `files(ids)` (information with MIME type),
  `filesByObfuscatedId(ids)`, `existing(ids)`, `hasThumbnail(file)`. The file
  condition of the search (`FieldPredicateCompiler`) moves to schema names in Phase 4.

## Record access layer (`Store/`)

Only these classes name the record tables: Records, recDetails, recLinks,
recDetailsDateIndex, usrRecPermissions, usrBookmarks, usrRecTagLinks, usrTags,
usrReminders, recForwarding, recThreadedComments, usrWorkingSubsets. Services never
query them — add a method to the repository. The search compiler writes table keys
that `Query/Compiler/SqlTables` resolves from `RecordSchema` (see `Query/_README.md`).

- `RecordSchema.php` — the table names, given on init with the current tables as
  defaults (`new RecordSchema(['records' => 'sysRecords', ...])`). The repository, the
  store and (Phase 4) the compiler take the same object.
- `RecordRepository.php` — all record reads, in batches:
  - headers: `headers(ids, columns, access?)`, `header`, `rectypeOf`, `find(rectype, ...)`,
    `rectypeCounts`;
  - values: `details(ids, fields, {order, owner, extent})` (raw rows), `values`
    (record ⇒ field ⇒ values), `editValues` (write format), `fieldUse`, `fileIds`,
    `aggregate`;
  - links: `links(ids, {side, kind, fields, anyField, relationTypes, fromTypes,
    otherTypes, distinct, order, limit})` → `[from, other, source, target, field,
    relation type, relationship record]`, `linkEnds` (candidate pre-filters);
  - access: `owners`, `accessSql(alias, access)` (the one record visibility rule),
    `valueVisible` (value visibility of output: field + `dtl_HideFromPublic`; the search does not check it),
    `visibleIds`, `editableIds`;
  - user data: `tags`, `bookmarked`; merged records: `forwarding`.
  `RecordRepository::shared($db)` (default tables) or `QueryExecutor::records()`.
- `RecordAccess.php` — the user of access checks (`fromRuntime`, `fromContext`, `guest`).
- `RecordStore.php` — all record writes (insert, update, permissions, values, delete
  cascade, copy); `records()` gives the repository on the same tables.

Pointer titles, terms and files of `/records` values are resolved after the value
read (`RecordDataService`), through the repository, `DefinitionLookup` and
`FileRepository`; value visibility is `RecordRepository::valueVisible`.
