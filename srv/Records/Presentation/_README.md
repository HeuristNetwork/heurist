# Record presentation

## Overview

Builds read-only Dataset, Map Document and Map Layer definitions from ordinary
Heurist records. It does not use `DbEntityBase` or `DbRecordTypeEntity`.

## Key files

- `PresentationRecordRepository.php` — direct Records/recDetails access.
- `DatasetPresentationService.php` — public Dataset definition.
- `MapPresentationService.php` — public Map Document and Map Layer definitions.
