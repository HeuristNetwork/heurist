# Records

## Overview

This folder contains the complete read-only record pipeline: search, expansion,
data retrieval, presentation, map feature generation, future aggregation and
streaming export.

## Key files

- `Query/RecordSearchService.php` — top-record search.
- `Expansion/ExpansionEngine.php` — linked-record traversal.
- `Data/RecordDataService.php` — batched record values.
- `Presentation/PresentationRecordRepository.php` — direct definition records.
- `Map/MapFeatureService.php` — GeoJSON feature production.
