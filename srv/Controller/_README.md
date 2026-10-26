# Controllers

## Overview

Controllers translate sanitized HTTP parameters into calls to the modern
read-only record services. They contain no query compilation or database SQL.
The existing `/hserv/controller/api.php` and `recordQuery.php` remain the public
entry points during migration.

## Key files

- `RecordQueryController.php` — records search/retrieval response; also one record
  (`GET /records/{id}`, `outputItem`: same record object and meta, no pagination);
  counts, values and bounds (`GET|POST /records/aggregate?op=count|rectypes|values|minmax|ranges`,
  `aggregate`/`outputAggregate`, plan 20 Phase 3). Search refuses those as `detail` values.
- `RecordPresentationController.php` — Dataset, Map Document and Map Layer reads.
- `MapDataController.php` — query-to-GeoJSON output only.
- `PublicationController.php` — map/data/timeline/graph/crosstabs publication actions.
- `WebsiteController.php` — v4 website pages and frames (`/{db}/site/...`), `/api/{db}/website` (plan 27a).
- `ReportController.php` — reports manager: list, template body, register, import/export, delete, single-record render.
- `JobController.php` — background jobs: start (answers, closes the connection, then runs), status, Stop, result.
