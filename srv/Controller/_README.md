# Controllers

## Overview

Controllers translate sanitized HTTP parameters into calls to the modern
read-only record services. They contain no query compilation or database SQL.
The existing `/hserv/controller/api.php` and `recordQuery.php` remain the public
entry points during migration.

## Key files

- `RecordQueryController.php` — records search/retrieval response.
- `RecordPresentationController.php` — Dataset, Map Document and Map Layer reads.
- `MapDataController.php` — query-to-GeoJSON output only.
