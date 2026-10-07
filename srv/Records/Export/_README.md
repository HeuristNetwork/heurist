# Record export

## Overview

Export of records into a file, run as the background job type `export`
(plan 13, heurist-explorer `docs/development/13 Export-Plan.md`). For the new
clients it replaces the legacy exporters in `hserv/records/export/`,
`export/xml/flathml.php` and `export/xml/kml.php`; those stay for the legacy UI.

There is **no streaming HTTP endpoint**: a job has limits (one export per user,
`maxJobs` per database, a time limit), Stop with `KILL QUERY`, progress, and a
result file that only its owner downloads (`GET /api/{db}/jobs/{id}/result`).
The reasons are listed in the plan ("Why there is no HTTP streaming export endpoint").

Flow (`ExportService::run`):
1. `ExportPlanner` builds the ordered id list before anything is written: the
   result of the query (or the selection, or some record types of it), cut to
   the limit, then the records the expansion rules reach (`ExpansionEngine`),
   each id once. Over `maxRecords` the export is refused.
2. Records are loaded in batches of 100 by `Data/RecordPageAssembler` (the same
   record objects as `/records`, resolved values) and passed to the writer.
3. One file is renamed to the download name; several CSV files are zipped.

Formats:
- `csv` / `tsv` — `Writer/CsvExportWriter`: one table per record type, first
  column `H-ID`; columns per record type (`"*"` for the others).
- `json` — `Writer/JsonExportWriter`: the `/records` envelope (`fields=_all`,
  `resolveDetails`) with `meta.recordTypes` and field concept codes; no record
  structure, no pagination.
- `geojson` — `Map/MapFeatureService::featuresForIds` + `Map/GeoJsonStreamWriter`
  (as `/map`); one feature per record, columns as properties.
- `kml` — `Writer/KmlExportWriter` (port of kml.php), columns as ExtendedData.
- `xml` — `Writer/HmlExportWriter`: flat HML core of flathml (no stubs, xinclude,
  file content, templates, HuNI); relationship records between exported records
  are added; `ImportHeurist::hmlToJson` reads it (tests/ExportWriterTest.php).
- `gephi` — `Writer/GexfExportWriter`: GEXF 1.2, attribute layout of
  `ExportRecordsGEPHI`; edges are the links among the exported records.

Value formats (`ValueFormatter`, `ExportColumns`): dates as is / start / range;
geo as WKT; files as URL / obfuscated id / details; pointers id / id + title; enum
outputs with the report names `term`, `code`, `conceptid`, `desc`, `internalid`
(the QSE column `ext: "id"` is read as `internalid`).

Settings: database settings file "Export" (`settings/export.json`):
`maxRecords` (default 500000) and `timeLimit` (seconds, default 600).

## Key files

- `ExportRequest.php` — parameters and their validation.
- `ExportPlanner.php` — ids (result + expansion), links among exported records.
- `ExportService.php` — batches, writers, packing.
- `ExportJob.php` — the `export` job type (`ServiceFactory::exportJobHandlers()`).
- `ExportColumns.php`, `ValueFormatter.php`, `ExportDefinitions.php` — columns,
  cell texts, definitions (names, concept codes, term descriptions).
- `ExportSettings.php` — limits.
- `Writer/*` — one writer per format; HML, GEXF and KML write through
  `Writer/XmlStreamWriter.php` (PHP XMLWriter, streamed into the file).

Tests: `php tests/ExportWriterTest.php --db=osmak_mapping --user=2 [--other=ID]`.
