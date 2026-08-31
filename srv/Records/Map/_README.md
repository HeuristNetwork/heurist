# Map output

## Overview

Combines record search, path expansion and field retrieval into streamed
GeoJSON. File-source conversion and shapefile handling remain under `/hserv`.

## Key files

- `MapFeatureService.php` — map feature pipeline.
- `MapFieldSelector.php` — geographic path validation.
- `GeoJsonGeometryConverter.php` — WKT-to-GeoJSON conversion.
- `GeoJsonStreamWriter.php` — transactionally stages and commits a complete
  FeatureCollection, spilling large payloads to temporary storage.
