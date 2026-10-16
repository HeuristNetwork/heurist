# Record expansion

## Overview

Expands an already paginated set of top records through compact paths or rule
branches. It records edges and concrete occurrences without changing top-query
pagination.

## Key files

- `ExpansionEngine.php` — batched traversal execution.
- `ExpansionRuleParser.php` — compact path and JSON rule parsing.
- `ExpansionRequest.php`, `ExpansionResult.php` — expansion contracts.
