# Tags on /sys/tag

Plan 20, Phase 4a. Tags stay in `usrTags` + `usrRecTagLinks` for good (§10.1: there
can be many more tags than records).

- `TagRecordMapping` — `tag_Text` = DT_NAME 2-1 (title, required, at most 63
  characters, unique per owner), `tag_Description` = DT_SHORT_SUMMARY 2-3 (at most
  250), the read-only Usage 2-1189 (the number of tagged records), owner =
  `rec_OwnerUGrpID`, `tag_Modified` = `rec_Modified`; record type Tag 2-1109. A database
  without these definitions gets `rec_RecTypeID` 0 and the header `rec_Usage`.
- `TagRecordOutput` — the records of `GET /sys?q={"t":"tag",...}` and
  `GET /sys/tag/{id}` (see `System/Query/_README.md`).
- `TagWriteService` — the rules of the legacy `DbUsrTags`: a user changes the tags of
  their own and of their groups (the database owner all); a tag on records is
  deleted only with `force` (409 `tag_in_use`); assign / remove / replace on the
  records the user can see; a personal tag bookmarks the record; merge within one
  owner.

| Route | Method | |
|---|---|---|
| `/sys/tag/0` | POST | add (201) |
| `/sys/tag/{id}` | PUT / PATCH / DELETE | change; delete (`force=1` for a tag on records) |
| `/sys/tag/{id}/validate` | POST | the checks only |
| `/sys/tag/{id}/merge` | POST | `{into}` |
| `/sys/tag/assign` | POST | `{records, add, remove, replace}` → `{processed, added, removed, bookmarks}` |

Tests: `tests/SystemTagTest.php [--write]`.
