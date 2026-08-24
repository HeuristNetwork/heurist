# Directory: hserv/records/search/query

## Overview

This directory contains the modern Heurist record-query parser and SQL composition classes. It converts the established plain-text or JSON query language into parameterized SQL for record IDs, result counts, linked-record filtering, and sorting.

The classes in this directory only interpret queries and compose SQL. They do not execute SQL, format records, export data, or manage expansion result sets. Query execution and search workflow coordination remain in the parent `hserv/records/search` directory.

## Key files

- `QueryBuilder.php`: Public facade for query normalization and SQL composition. It builds IDs, count, and complete ID-set queries and coordinates logical groups and linked or relationship-record predicates.
- `RecordQueryParser.php`: Converts plain-text queries to the established JSON representation, normalizes decoded JSON queries, validates predicates, and determines whether a query can be delegated completely to SQL.
- `RecordPredicateCompiler.php`: Compiles record-level predicates, including record IDs and replacements, record types, titles, access, ownership, creators, bookmarks, worksets, and tags.
- `FieldPredicateCompiler.php`: Compiles detail-field predicates, including text, numeric, enum, resource, date, file, geographic, full-text, language, NULL, count, and field-visibility conditions.
- `SortCompiler.php`: Compiles deterministic ordering for record headers, typed detail fields, bookmark ratings, popularity, and fixed record sets.
- `SqlBuildContext.php`: Maintains parameter bind types and values, request/user context, and unique SQL aliases while a query is being composed.

## Responsibilities

`QueryBuilder` is the only class normally used outside this directory. The other classes are collaborators with deliberately limited responsibilities. No collaborator executes a database search or returns record data.

Field metadata and term descendants are resolved internally by `FieldPredicateCompiler` from the existing Heurist definition tables. This information is cached for the lifetime of the compiler instance. The implementation does not depend on legacy query-composer functions.

All user values are added through `SqlBuildContext` as prepared-statement parameters. SQL identifiers and operators are selected only from validated query keywords, field metadata, and internal allowlists.

## Interaction

The modern search workflow is:

1. `RecordSearchService` receives a `SearchRequest` and passes its query to `QueryBuilder`.
2. `QueryBuilder` asks `RecordQueryParser` to convert plain text to JSON when required, normalize the query structure, and validate predicates and nesting.
3. `QueryBuilder` creates one `SqlBuildContext` for the SQL statement. The same context is shared by every compiler so placeholder order remains identical to bind-value order.
4. `QueryBuilder` walks logical groups and delegates scalar predicates:
   - record-header and user predicates go to `RecordPredicateCompiler`;
   - detail, file, date, enum, and spatial predicates go to `FieldPredicateCompiler`.
5. `QueryBuilder` composes linked-resource and relationship-record predicates as bounded correlated `EXISTS` expressions. Nested record predicates are delegated through the same compilers and the same build context.
6. `QueryBuilder` asks `SortCompiler` to append ordering. `SortCompiler` uses `FieldPredicateCompiler` for cached field types and field-visibility rules.
7. `QueryBuilder` returns a `CompiledQuery` containing SQL, bind types, bind values, and the normalized query. It does not execute the statement.
8. `QueryExecutor`, located in the parent search directory, prepares and executes the SQL.
9. `RecordSearchService` combines the IDs query and count query into `SearchResult`. Queries requiring metadata-dependent relationship resolution or paths beyond the SQL nesting limit are delegated to the existing batched expansion fallback.

The principal interaction is therefore:

```text
RecordSearchService
    |
    v
QueryBuilder <---- RecordQueryParser
    |
    +---- RecordPredicateCompiler ----+
    |                                 |
    +---- FieldPredicateCompiler -----+---- SqlBuildContext
    |                                 |
    +---- SortCompiler ---------------+
    |
    v
CompiledQuery
    |
    v
QueryExecutor
```

## Boundaries

- `query/` parses the query language and composes parameterized SQL.
- `QueryExecutor` executes composed SQL and returns primitive IDs, rows, or scalar values.
- `RecordSearchService` coordinates searching, counting, pagination, and expansion fallback.
- `RecordDetailsByPath` retrieves details after the record search is complete.
- Record exporters format already retrieved records and must not implement query parsing.
- Legacy `composeSql.php`, `composeSqlOld.php`, and `recordSearch.php` remain outside this directory and are not modified by this refactoring.

