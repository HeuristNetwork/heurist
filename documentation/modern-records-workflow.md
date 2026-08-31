# Modern read-only records workflow

## Scope

The `/srv` package is the permanent, namespaced implementation of read-only
record search, linked-record expansion, field retrieval, Dataset/Map
presentation and query-to-GeoJSON output. It uses PDO through
`Heurist\Database\DatabaseInterface` and does not depend on mysqli or the legacy
`System` object inside application services.

The migration intentionally excludes IIIF routes, editing, batch operations,
published-map writes, timeline export, `MapDataSourceService`,
`MapShapefileService`, and file-source conversion.

## Active workflows

### Records

`api.php` or `recordQuery.php` → `RecordQueryController` → `QueryBuilder` →
`RecordSearchService` → optional `ExpansionEngine` → `RecordDataService`.

### Presentation definitions

`api.php` → `RecordPresentationController` → `DatasetPresentationService` or
`MapPresentationService` → `PresentationRecordRepository`.

Presentation records are read directly from `Records` and `recDetails`; the
new repository does not inherit from `DbEntityBase` or `DbRecordTypeEntity`.

### Map features

`api.php` → `MapDataController` → `MapFeatureService` → search, expansion and
data retrieval → `GeoJsonStreamWriter`.

The legacy controller still owns `/time` and `/map/data/{id}`.

## Namespace and loading

Composer maps `Heurist\` to `/srv`. Existing `hserv\` loading remains separate,
so both autoloaders can coexist. Files below `/srv` never load other class files
with `require` or `include`.

## Temporary legacy boundary

`ServiceFactory::fromLegacySystem()` is the only intended bridge from the
currently initialized legacy runtime. It copies database name, user ID, group
IDs, access flags and base URL into `RuntimeContext`, then creates a new PDO
connection from existing Heurist configuration constants.

`SystemCode` temporarily isolates RT/DT constants. `ConceptCode` performs direct
definition-table lookup without static `System` state. These boundaries can be
replaced independently when the remaining server initialization is modernized.

## Database portability

PDO removes dependency on the mysqli API, but current generated SQL deliberately
targets MySQL/MariaDB and the existing Heurist tables. MySQL full-text, spatial
and optimizer-specific expressions must be isolated before another database
driver can execute the workflow.

## Future packages

`Records/Aggregation` and `Records/Export` are documented placeholders. Their
classes will be implemented after aggregation semantics are agreed, reusing the
same search, expansion, field selection and streaming boundaries.
