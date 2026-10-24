# System/Groups

## Overview

Workgroups on `/sys/group` in the record format (plan 20, Phase 4d, §10.13): name, long name
(DT_SHORT_SUMMARY), description, URLs, and the members as the repeatable pointer fields Admins
and Members (DT_GROUP_ADMINS / DT_GROUP_MEMBERS, `System/Users/UserGroupCodes`).

## Key files

- `GroupRecordMapping.php` — sysUGrps (workgroups) and sysUsrGrpLinks <=> record.
- `GroupRecordOutput.php` — `/sys/group` records; the keywords `role` (the current user's
  role) and `members` (`details.members` [{id, role}], also without the member fields).
- `GroupWriteService.php` — POST / PUT / PATCH / DELETE and `POST /sys/group/{id}/members`
  ({add, role} | {remove}) with the legacy DbSysGroups rules: any user adds a group and is
  its admin; its admins and the database owner change it; the name is unique among users
  and groups; a group keeps an admin; delete by the legacy class (closure).

## Tests

`php tests/SystemUserGroupTest.php --db=osmak_mapping --user=2 [--write]`
