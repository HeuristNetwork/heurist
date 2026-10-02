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
- `RelationTermResolver.php` — relationship-term closure, inverse terms and
  relationship-field constraints (vocabulary, owner and target record types).
  `relationshipLegs()` is the one definition of `rt` / `rf` / `related[:field]`
  (stored direction, stored relation types, record types of both ends) used by
  SQL compilation, chunked search, graph steps and `ExpansionEngine`, so these
  predicates compile to `EXISTS … OR EXISTS …`. Only top-level `r`/`relf` and
  over-deep nesting still need chunked execution.
- `SqlBuildContext.php` — aliases and ordered parameters.
