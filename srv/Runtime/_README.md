# Runtime

## Overview

This folder replaces the small portion of legacy `System` state needed by the
new read-only services. It must not grow into a second general-purpose System
class or a collection of global database functions.

## Key files

- `RuntimeContext.php` — current database, user and base URL values.
- `ApiResponse.php` — standard JSON output and HTTP error mapping.
- `ErrorReporter.php` — safe logging of unexpected server errors.
- `SystemCode.php` — isolated RT/DT code resolution during migration.
- `ConceptCode.php` — direct definition concept-code lookup.
- `ServiceFactory.php` — temporary composition boundary for existing entry points.
