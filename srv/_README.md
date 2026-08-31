# Modern server records workflow

## Overview

The `/srv` tree contains the namespaced, read-only replacement for record
search, expansion, field retrieval, presentation, aggregation and export. It is
kept separate from `/hserv`; only HTTP entry points may temporarily bridge the
initialized legacy runtime into these classes.

The initial migration excludes IIIF, record editing, published-map writes,
batch/import operations, `MapDataSourceService`, and `MapShapefileService`.

Dependency direction is Controller → Records → Database/Runtime. Classes below
`/srv` are loaded by namespace and must not contain `require` or `include`.

## Key files

- `Controller/RecordQueryController.php` — records HTTP request adapter.
- `Controller/RecordPresentationController.php` — Dataset, Map Document and
  Map Layer read adapter.
- `Controller/MapDataController.php` — query-to-GeoJSON read adapter.
- `Database/DatabaseInterface.php` — database abstraction.
- `Database/MysqlDatabase.php` — MySQL implementation for the modern read-only workflow.
- `Database/AbstractDatabase.php` — shared PDO helper for provider implementations.
- `Runtime/RuntimeContext.php` — request user/database context.
- `Records/Query/RecordSearchService.php` — top-record search orchestration.
- `Records/Expansion/ExpansionEngine.php` — linked-record path traversal.
- `Records/Data/RecordDataService.php` — batched header/detail retrieval.
- `Records/Presentation/` — read-only presentation definition services.
- `Records/Map/MapFeatureService.php` — GeoJSON feature pipeline.
