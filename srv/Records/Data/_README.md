# Record data

## Overview

Retrieves headers and native or linked detail values for record IDs selected by
search and expansion. It does not parse queries or decide pagination.

## Key files

- `RecordDataService.php` — batched value and metadata retrieval.
- `RecordFieldSelector.php` — validates requested header/detail/path fields.
