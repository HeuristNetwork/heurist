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
- `Compiler/SqlTables.php` — table names of the compiled SQL (plan 15).

## Table names in compiled SQL

The compilers build one statement as text and never write table names. They write
table keys — `{records}`, `{details}`, `{links}`, `{dateIndex}`, `{permissions}`,
`{bookmarks}`, `{tagLinks}`, `{tags}`, `{forwarding}`, `{worksets}`, `{fieldTypes}`,
`{structure}`, `{terms}`, `{translations}`, `{rectypes}`, `{files}`, `{groups}`,
`{memberships}` — and the builders resolve them when they make the final SQL
(`QueryBuilder::sql()`, also used by `FieldValueCounter`/`Range`/`Buckets`;
`SystemQueryBuilder`). The names come from `RecordSchema` (given to the
`QueryBuilder` constructor, default: the current tables), `DefinitionLookup::TABLES`,
`FileRepository::TABLES` and `UserGroupRepository::TABLES`. `CompiledQuery::$sql`
always has the real names.

Record visibility is `RecordRepository::accessSql` (the one rule);
`RecordPredicateCompiler::appendAccessConditions` binds its values. Data the compilers
need while compiling comes from the access classes (`DefinitionLookup`,
`UserGroupRepository`, `RecordRepository::forwarding`).
