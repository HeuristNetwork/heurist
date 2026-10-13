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
