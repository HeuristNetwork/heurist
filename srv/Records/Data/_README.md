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

For `fields=_all`, records the user may change also get `rec_EditGroups`,
`rec_NonOwnerVisibilityGroups`, `rec_FlagTemporary`, and values hidden from the
public as `{value, hidden:true}` (write format of plan 14, `Records/Edit`).
Website page content (RT_CMS_PAGE / DT_EXTENDED_DESCRIPTION), saved in parts when
long, is given as one value, as legacy recordSearch does.
