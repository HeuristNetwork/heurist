# Bookmarks on /sys/bookmark

Plan 20, Phase 4b (§10.9). Bookmarks stay in `usrBookmarks` until the switch (§2.1).

- `BookmarkRecordMapping` — record type Bookmark 2-1110: the bookmarked record is the
  pointer 2-13 (`bkm_RecID`), Rating 2-1190 (`bkm_Rating`, 0-5; 0 = no value), the notes
  2-4 (`bkm_Notes`), PrivateInfo 2-1192 (`bkm_PwdReminder`, encrypted). The owner is
  the user (`rec_OwnerUGrpID`), the title is the title of the record. `bkm_ZoteroID` is
  not mapped.
- `PrivateInfoCipher` — AES-256-GCM through openssl with the key of the installation,
  `$privateInfo_Secret` in `heuristConfigIni.php` (empty default in `configIni.php`).
  Stored as `enc1:` + base64(iv, tag, cipher text); a value without the prefix is legacy
  text. Without a key a note is kept as text and a save answers with the warning
  `not_encrypted`. At most 140 characters (the column has 250). The legacy code uses it
  too: `hserv/entity/DbUsrBookmarks` (read and save) and `recordSearch` (the key icon).
  **Changing the key makes the stored notes unreadable** (they are left out and listed
  in `meta.privateInfo.unreadable`).
- `BookmarkRecordOutput` — `GET /sys/bookmark[/{id}]`: the user's own bookmarks only
  (also for administrators); the note is decrypted for the owner; `meta.privateInfo`
  `{encrypted, unreadable}`.
- `BookmarkWriteService` — the owner only; one bookmark per record and user (409
  `duplicate` with `existing`); the record must be visible and is not changed later; a
  record with personal tags of the user keeps its bookmark (409 `has_personal_tags`).

| Route | Method | |
|---|---|---|
| `/sys/bookmark/0` | POST | add (201) |
| `/sys/bookmark/{id}` | PUT / PATCH / DELETE | change, delete |
| `/sys/bookmark/{id}/validate` | POST | the checks only |

Query: `{"t":"bookmark","f:record":"81"}` gives the bookmark of a record.

Tests: `tests/SystemBookmarkReminderTest.php [--write]`.
