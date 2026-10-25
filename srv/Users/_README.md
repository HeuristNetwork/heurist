# Users

## Overview

Users and groups (sysUGrps, sysUsrGrpLinks). The classes of this folder are the only
srv code that names these tables (plan 15, heurist-explorer
`docs/development/15 DONE Data-Access-Layer-Plan.md`; the writes since plan 20 Phase 4d).
Services never query them themselves — add a method here.

## Key files

- `UserGroupRepository.php` — `shared($db)` (one instance and cache per connection);
  `names(ids)`, `name(id)`, `findByName(text)` (case-insensitive, for query values),
  `idsByName(names)`, `existing(ids)`, `enabled(userId)` (account state),
  `roles(userId)` (group => admin|member); since plan 20 Phase 4d `rows(ids, type)` (the
  columns of `COLUMNS`, never the password), `members(groupIds)`, `taken(column, value, exceptId)`.
- `LegacyUserGroupTable.php` — insert, update, delete of sysUGrps rows (the password only
  written, hashed by the caller).
- `GroupMembershipTable.php` — `set(groupId, userIds, role)`, `remove(groupId, userIds)`.

`TABLES` gives the table names to the search compiler (`{groups}`, `{memberships}`
in compiled SQL). `System/Query/SystemEntitySchemaRegistry` keeps the `/sys` entity
schemas.
