# Users

## Overview

Users and groups (sysUGrps, sysUsrGrpLinks). `UserGroupRepository.php` is the only
srv class that reads these tables (plan 15, heurist-explorer
`docs/development/15 Data-Access-Layer-Plan.md`). Services never query them
themselves — add a method here.

## Key files

- `UserGroupRepository.php` — `shared($db)` (one instance and cache per connection);
  `names(ids)`, `name(id)`, `findByName(text)` (case-insensitive, for query values),
  `idsByName(names)`, `existing(ids)`, `enabled(userId)` (account state),
  `roles(userId)` (group => admin|member).

`TABLES` gives the table names to the search compiler (`{groups}`, `{memberships}`
in compiled SQL). `System/Query/SystemEntitySchemaRegistry` keeps the `/sys` entity
schemas.
