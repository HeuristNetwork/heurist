# User data of records

The tables of the user data that belongs to records, named through the
`RecordSchema` table keys (`tags`, `tagLinks`, `bookmarks`...), so the access
boundary of plan 15 holds.

- `TagRepository` — tag rows with their usage, a tag by owner and text, the records of
  tags, the owners of tags.
- `LegacyTagTable` — writes of `usrTags` rows (`System/Entity/LegacyEntityTable`).
- `TagLinkTable` — assign, remove and move (merge) the links of tags and records, and
  the bookmark a personal tag gives a record (as legacy).

- `BookmarkRepository` — bookmark rows with the title of the record, the bookmark of a
  user on a record, whether the user has personal tags on a record.
- `LegacyBookmarkTable` — writes of `usrBookmarks` rows.
- `ReminderRepository` — reminder rows with the title of the record.
- `LegacyReminderTable` — writes of `usrReminders` rows.
