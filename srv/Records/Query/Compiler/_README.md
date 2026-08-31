# Query compiler

## Overview

Compiles normalized Heurist predicates into parameterised MySQL/MariaDB SQL.
Definition-name resolution uses `DatabaseInterface`; SQL execution remains in
`QueryExecutor`.

## Key files

- `QueryBuilder.php` — public compilation facade.
- `FieldPredicateCompiler.php` — detail, temporal, term, file and geo predicates.
- `RecordPredicateCompiler.php` — record headers, visibility, users and tags.
- `SortCompiler.php` — deterministic sort expressions.
- `QueryValueResolver.php` — semantic names to local IDs.
- `SqlBuildContext.php` — aliases and ordered parameters.
