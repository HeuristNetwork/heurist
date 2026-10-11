# Record data

## Overview

Retrieves headers and native or linked detail values for record IDs selected by
search and expansion. It does not parse queries or decide pagination.

## Key files

- `RecordDataService.php` — batched value and metadata retrieval. Field metadata
  and `defs` (meta.recordTypes, meta.terms) come from `Definitions/DefinitionLookup`.
- `RecordPageAssembler.php` — record objects, field metadata (also for `fields=_all`)
  and `defs` of a page; used by `/records`, `/records/{id}` and the JSON export, so all
  three give the same format (common JSON format, 2026-10-09).
- `RecordFieldSelector.php` — validates requested header/detail/path fields.
