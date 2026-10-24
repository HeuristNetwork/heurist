# System/Users

## Overview

Users on `/sys/user` in the record format (plan 20, Phase 4d; heurist-explorer
`docs/development/20 System-Data-Transition-and-Files-Plan.md` §10.13). The fields are named
by DT_* constants: `UserGroupCodes.php` maps them to concept codes (user codes given 2026-10-24;
the group Admins / Members still have an empty code; a field without a code, or one the
database lacks, is left out).

## Key files

- `UserGroupCodes.php` — DT_* / RT_* / TRM_* names of users and groups => concept codes; also
  the client constants (`DefinitionSnapshotService` dbconst). Fill in the codes here.
- `UserRecordMapping.php` — sysUGrps (users) <=> record: login name, names, e-mail, password
  (written only), account status (term whose code is the ugr_Enabled value), organisation,
  department, address (City / State / Postcode lines), interests, URLs, ORCID, last login and
  login count (read only). Title "Ian Johnson (ijohnson)".
- `UserRecordOutput.php` — `/sys/user` records (logged-in users only; never the password;
  the former keyword `email`).
- `UserWriteService.php` — POST / PUT / PATCH / DELETE with the legacy DbSysUsers rules; the
  password hash, the e-mails and the delete are legacy code given as closures (ServiceFactory).

Groups: `System/Groups`. Images of users and groups: `Files/EntityImageStorage` and
`System/Entity/EntityImageService` (`/sys/{type}/{id}/image`).

## Tests

`php tests/SystemUserGroupTest.php --db=osmak_mapping --user=2 [--write]`
