# Modern server records workflow

## Overview

The `/srv` tree contains namespaced replacements for record search, expansion,
field retrieval, presentation, aggregation, export and publication. It is
kept separate from `/hserv`; only HTTP entry points may temporarily bridge the
initialized legacy runtime into these classes.

The initial migration excludes IIIF, record editing, batch/import operations,
`MapDataSourceService`, and `MapShapefileService`. Module-neutral publication
storage is provided by `Publication/PublicationService.php`; its writable
directory and runtime identity are injected by `Runtime/ServiceFactory.php`.

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
- `Database/DatabaseInterface.php` — database abstraction.
- `Database/MysqlDatabase.php` — MySQL implementation for the modern read-only workflow.
- `Database/AbstractDatabase.php` — shared PDO helper for provider implementations.
- `Runtime/RuntimeContext.php` — request user/database context.
- `Records/Query/RecordSearchService.php` — top-record search orchestration.
- `Records/Expansion/ExpansionEngine.php` — linked-record path traversal.
- `Records/Data/RecordDataService.php` — batched header/detail retrieval.
- `Records/Presentation/` — read-only presentation definition services.
- `Records/Map/MapFeatureService.php` — GeoJSON feature pipeline.
- `Publication/PublicationService.php` — publication persistence and bootstrap generation.
- `System/Query/` — stable system entity schemas and the shared-query adapter
  for legacy `usrSavedSearches` and `sysUGrps` storage.
