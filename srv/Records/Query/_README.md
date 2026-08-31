# Record query

## Overview

Parses Heurist query syntax, resolves semantic names, compiles parameterised SQL
and returns an ordered page of record IDs plus its total count.

## Key files

- `RecordSearchService.php` — search orchestration and chunked fallbacks.
- `QueryExecutor.php` — execution through `DatabaseInterface`.
- `SearchRequest.php`, `SearchResult.php`, `CompiledQuery.php` — data contracts.
- `Parser/RecordQueryParser.php` — syntax normalization and validation.
- `Compiler/QueryBuilder.php` — parameterised SQL facade.
