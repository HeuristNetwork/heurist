# System query mappings

`SystemQueryController` exposes legacy system entities through the same query,
`SearchRequest`, `SearchResult`, universal header and pagination conventions as
the records API. `RecordQueryParser` remains the shared language parser;
`SystemQueryBuilder` resolves predicates through the selected schema instead of
through record/detail definitions.

| Public type | Storage | Stable headers | Logical fields |
| --- | --- | --- | --- |
| `filter` | `usrSavedSearches` | `id`, `title`, `modified`, `owner` | `query` |
| `user` | `sysUGrps` (`ugr_Type="user"`) | `id`, `title`, `modified` | `email` |
| `group` | `sysUGrps` (`ugr_Type="workgroup"`) | `id`, `title`, `modified` | `role` |

`role` is virtual: the current user's membership of each group from
`sysUsrGrpLinks.ugl_Role` (`admin`, `member`, otherwise `none`).

Visibility of `user` and `group`: database administrators (the database owner
or members of *Database Managers*) see every user and group; any other user
sees only themselves and the groups they belong to; guests see nothing.

Logical fields accept either `query`/`email` or `f:query`/`f:email`. Output
always uses `rec_ID`, `rec_RecTypeID` and `rec_Title`; requested logical values
are returned under `details` using their stable names.

When a legacy entity moves to `sysRecords/sysDetails`, change its registry
mapping while retaining the public type, keywords and response contract.
