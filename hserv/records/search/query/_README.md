# Directory: hserv/records/search/query

## Overview

This directory contains the modern Heurist record-query parser and SQL composition classes. It converts the established plain-text or JSON query language into parameterized SQL for record IDs, result counts, linked-record filtering, and sorting.

The classes in this directory only interpret queries and compose SQL. They do not execute SQL, format records, export data, or manage expansion result sets. Query execution and search workflow coordination remain in the parent `hserv/records/search` directory.

## Key files

- `QueryBuilder.php`: Public facade for query normalization and SQL composition. It builds IDs, count, and complete ID-set queries and coordinates logical groups and linked or relationship-record predicates.
- `RecordQueryParser.php`: Converts plain-text queries to the established JSON representation, normalizes decoded JSON queries, validates predicates, and determines whether a query can be delegated completely to SQL.
- `QueryValueResolver.php`: Resolves semantic record-type, field, user, and enum-term names to IDs before the main record query is composed. Field lookup is restricted through `defRecStructure` when a query level declares record types.
- `RecordPredicateCompiler.php`: Compiles record-level predicates, including record IDs and replacements, record types, titles, access, ownership, creators, bookmarks, worksets, and tags.
- `FieldPredicateCompiler.php`: Compiles detail-field predicates, including text, numeric, enum, resource, date, file, geographic, full-text, language, NULL, count, and field-visibility conditions.
- `SortCompiler.php`: Compiles deterministic ordering for record headers, typed detail fields, bookmark ratings, popularity, and fixed record sets.
- `SqlBuildContext.php`: Maintains parameter bind types and values, request/user context, and unique SQL aliases while a query is being composed.

## Responsibilities

`QueryBuilder` is the only class normally used outside this directory. The other classes are collaborators with deliberately limited responsibilities. No collaborator executes a database search or returns record data.

Field metadata and term descendants are resolved internally by `FieldPredicateCompiler` from the existing Heurist definition tables. This information is cached for the lifetime of the compiler instance. The implementation does not depend on legacy query-composer functions.

Semantic names are resolved once, before the main query. Record types match
`rty_Name` or `rty_Plural`. Field names match `dty_Name` or the record-type
specific `rst_DisplayName`; exact matches are preferred, then partial matches
are allowed. A record-type constraint at the same query level restricts field
lookup through `defRecStructure`. Names containing spaces use JSON, for example
`{"t":"Events","f:Date of event":"<100 years ago"}`; plain text supports
unspaced forms such as `t Events fGender male`. Missing and ambiguous names are
validation errors, and ambiguity messages list every matching ID and name.

Enum labels/codes and user names are also converted to IDs before candidate
probing and main SQL composition. Enum resolution is restricted to the field's
configured vocabulary, including a record-type-specific filtered term tree
when present. Field metadata and term-descendant sets are cached for one request.

Relative date values are normalized immediately before typed temporal SQL is
compiled. Supported conveniences include `today`, `yesterday`, `tomorrow`,
`month`, `year`, `last month`, `last year`, and `<number> days/weeks/months/years
ago`. Thus `{"f:9":"<100 years ago"}` is converted to an absolute date before
`Temporal` calculates the indexed comparison bounds. Bare `month` and `year`
mean the complete current month or year.

Full-text operators retain the public `@`, `@+`, and `@-` meanings. For `@+`
and `@-`, words outside InnoDB's 3–84 byte indexable range and the established
legacy stop-word list are removed. If no indexable words remain, compilation
falls back to `LIKE`; otherwise `@+` uses Boolean mode with every word required.
`@-` always discovers the positive candidate set first and excludes that set
once at record level.

An unsuffixed `f` (any-field search) is compiled as an indexed candidate-ID
`UNION ALL` over ordinary details, enum details, and linked-record titles. The
outer record query applies the resulting ID set. This deliberately avoids
correlated `EXISTS` branches, allowing MariaDB to start full-text searches from
their indexes. `@-` uses `NOT IN` around the complete positive candidate set.

Before composing the main SQL, `RecordSearchService` probes an any-field
candidate query with a limit of 5,001. Results of at most 5,000 IDs replace the
predicate with the existing IDs predicate; larger results retain the normal
inline SQL. Identical probes are cached for the duration of one search request.
This bounded optimization uses neither temporary tables nor a new public query
predicate.

The same bounded probe is applied to suffixed `integer` and `float` field
predicates. Numeric probes use the existing comparison/range compiler and are
replaced with IDs only when they return at most 5,000 candidates. NULL,
presence, date, enum, resource, and text-field predicates are unaffected.

All user values are added through `SqlBuildContext` as prepared-statement parameters. SQL identifiers and operators are selected only from validated query keywords, field metadata, and internal allowlists.

## Interaction

The modern search workflow is:

1. `RecordSearchService` receives a `SearchRequest` and passes its query to `QueryBuilder`.
2. `QueryBuilder` asks `RecordQueryParser` to convert plain text to JSON when required, normalize the query structure, and validate predicates and nesting.
3. `QueryValueResolver` traverses that normalized query before candidate probing or SQL composition. At each nested query level it resolves singular/plural record-type names, restricts field-name lookup with `defRecStructure`, resolves user names and enum labels/codes, and reports ambiguous matches with their IDs and names.
4. `QueryBuilder` creates one `SqlBuildContext` for the SQL statement. The same context is shared by every compiler so placeholder order remains identical to bind-value order.
5. `QueryBuilder` walks logical groups and delegates scalar predicates:
   - record-header and user predicates go to `RecordPredicateCompiler`;
   - detail, file, date, enum, and spatial predicates go to `FieldPredicateCompiler`.
6. `QueryBuilder` composes linked-resource and relationship-record predicates as bounded correlated `EXISTS` expressions. Nested record predicates are delegated through the same compilers and the same build context.
7. `QueryBuilder` asks `SortCompiler` to append ordering. `SortCompiler` uses `FieldPredicateCompiler` for cached field types and field-visibility rules.
8. `QueryBuilder` returns a `CompiledQuery` containing SQL, bind types, bind values, and the resolved numeric query. It does not execute the statement.
9. `QueryExecutor`, located in the parent search directory, prepares and executes the SQL.
10. `RecordSearchService` combines the IDs query and count query into `SearchResult`. Queries requiring metadata-dependent relationship resolution or paths beyond the SQL nesting limit are delegated to the existing batched expansion fallback.

The principal interaction is therefore:

```text
RecordSearchService
    |
    v
QueryBuilder <---- RecordQueryParser
    ^
    +---- QueryValueResolver
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
