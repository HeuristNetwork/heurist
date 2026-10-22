# System entities in the record format

Plan 20 (§2, §10.4): system data kept in its own legacy table is given and taken
as records of a real record type (`rec_*` headers, details by field id from
concept codes). The pieces every entity shares:

- `EntityRecordMapping` — the record type and fields by concept code (a field the
  database lacks is left out), the writable columns, the term lookup; hooks for
  the value conversions. `Files/FileRecordMapping`, `Tags/TagRecordMapping`,
  `Bookmarks/BookmarkRecordMapping`, `Reminders/ReminderRecordMapping`.
- `LegacyEntityTable` — insert, update and delete of the known columns of one
  legacy table. `Records/Files/LegacyFileTable`, `Records/UserData/LegacyTagTable`.
- `EntityWriteService` — the record of a request (`{records: [one]}` too), the id
  of the path, POST for id 0 and PUT / PATCH for a change, the account check, the
  409 conflict on `rec_Modified`, warnings. `Files/FileWriteService`,
  `Tags/TagWriteService`.
- `EntityTransaction` — one transaction around a write (joins one already open).
- `EntityRecordOutput` — the `/sys` output of a mapped type as `/records` gives it: the
  `fields` selection (`_all`, headers, field ids, `rec_OwnerName`, the former keywords of
  the type), details by field id, the field metadata. `TagRecordOutput`,
  `BookmarkRecordOutput`, `ReminderRecordOutput`.
