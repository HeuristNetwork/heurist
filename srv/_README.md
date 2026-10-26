# Modern server records workflow

## Overview

The `/srv` tree contains namespaced replacements for record search, expansion,
field retrieval, presentation, aggregation, export and publication. It is
kept separate from `/hserv`; only HTTP entry points may temporarily bridge the
initialized legacy runtime into these classes.

The initial migration excludes IIIF, batch/import operations,
`MapDataSourceService`, and `MapShapefileService`. Module-neutral publication
storage is provided by `Publication/PublicationService.php`; its writable
directory and runtime identity are injected by `Runtime/ServiceFactory.php`.
Record editing (plan 14) is in `Records/Edit/`, for system record types only.

Data access (plan 15): services do not query the database tables themselves.
Definitions are read by `Definitions/DefinitionLookup`, records by
`Records/Store/RecordRepository` (writes `RecordStore`, tables `RecordSchema`),
files by `Records/Files/FileRepository`, users and groups by
`Users/UserGroupRepository`, database settings by `Runtime/DatabaseSettings`.

Dependency direction is Controller → Records → Database/Runtime. Classes below
`/srv` are loaded by namespace and must not contain `require` or `include`.

## Key files

- `Controller/RecordQueryController.php` — records HTTP request adapter.
- `Controller/RecordPresentationController.php` — Dataset, Map Document and
  Map Layer read adapter.
- `Controller/SystemQueryController.php` — mapped filter/user collection and
  item reads for the `/sys` API domain.
- `Controller/MapDataController.php` — query-to-GeoJSON read adapter.
- `Controller/PublicationController.php` — shared module publication adapter.
- `Controller/RecordEditController.php` — record writes (POST/PUT/PATCH/DELETE /records/{id}).
- `Database/DatabaseInterface.php` — database abstraction.
- `Database/MysqlDatabase.php` — MySQL implementation of the modern workflow.
- `Database/WritableDatabaseInterface.php` — writes and transactions (record editing).
- `Database/AbstractDatabase.php` — shared PDO helper for provider implementations.
- `Runtime/RuntimeContext.php` — request user/database context.
- `Records/Query/RecordSearchService.php` — top-record search orchestration.
- `Records/Expansion/ExpansionEngine.php` — linked-record path traversal.
- `Records/Data/RecordDataService.php` — batched header/detail retrieval.
- `Records/Presentation/` — read-only presentation definition services.
- `Records/Map/MapFeatureService.php` — GeoJSON feature pipeline.
- `Publication/PublicationService.php` — publication persistence and bootstrap generation.
- `Controller/ReportController.php` — reports manager (`/api/{db}/reports`).
- `Reports/` — Smarty report records, template files and engine interfaces.
- `Controller/JobController.php` — background jobs (`/api/{db}/jobs`).
- `Jobs/` — background job runner, JSON state files, Stop and time limits.
- `System/Query/` — stable system entity schemas and the shared-query adapter
  for legacy `usrSavedSearches` and `sysUGrps` storage.
- `Controller/FileController.php`, `Files/` — registered files as records
  (`/api/{db}/files`, plan 20) on `recUploadedFiles` or `fileRecords`.
- `System/Entity/` — bases of the entities kept in legacy tables and given in the
  record format: mapping (`EntityRecordMapping`), row writes (`LegacyEntityTable`),
  write steps (`EntityWriteService`), `EntityTransaction` (plan 20, §10.4). Files and
  tags are built on them.
- `Controller/SystemEditController.php`, `System/Tags/` — tags on `/api/{db}/sys/tag`
  in the record format with writes, assign and merge (plan 20, Phase 4a).
- `System/Bookmarks/`, `System/Reminders/` — bookmarks (with the encrypted private note)
  and reminders (with Send now) on `/api/{db}/sys/bookmark|reminder` (plan 20, Phase 4b).
- `Records/UserData/` — reads and writes of the user tables of records (tags and their
  links, bookmarks, reminders), through the `RecordSchema` table keys.
- `Controller/SameSiteGuard.php` — the same-site and JSON checks of the write controllers.
- `Controller/WebsiteController.php`, `Website/` — live pages of the website format v4
  (`/{db}/site/{id}`, plan 27a); `Utilities/StoredCss.php` — the save-time CSS policy.
