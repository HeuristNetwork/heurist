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
| `tag` | `usrTags` | `id`, `title`, `modified`, `owner` | `description`, `ownername`; query only: `record` |
| `bookmark` | `usrBookmarks` (the user's own) | `id`, `title` (= id), `modified`, `owner` | `record`, `rating` |
| `reminder` | `usrReminders` (the user's own; the database owner all) | `id`, `title` (= id), `modified`, `owner` | `record`, `startdate`, `frequency` |

**Record format (plan 20, §10.1).** A type is given in the record format once it is
mapped, in its own phase: tags from Phase 4a (`System/Tags/TagRecordOutput`):
`rec_RecTypeID` is the local id of the Tag record type (0 until the core definitions have
it), `details` are keyed by field id (text 2-1, description 2-3), `fields` is as in
`/records` (`_all`, headers, field ids, `rec_OwnerName`) and also takes the former keywords
(`owner`, `ownername`, `description`...); the number of tagged records is the Usage field
2-1189 (`rec_Usage` in a database without it). Bookmarks and reminders from Phase 4b
(`System/Bookmarks/`, `System/Reminders/`), all on `System/Entity/EntityRecordOutput`. An item
is `{records: [one], meta}`. The other types keep the output below until their phases (4c
filters, 4d users and groups). Writes: `SystemEditController`.

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
