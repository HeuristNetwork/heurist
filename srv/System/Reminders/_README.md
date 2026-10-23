# Reminders on /sys/reminder

Plan 20, Phase 4b (§10.9). Reminders stay in `usrReminders` until the switch (§2.1);
the scheduled sending stays the legacy cron (`DbUsrReminders::batch_action`).

- `ReminderRecordMapping` — record type Reminder 2-1111: the record is the pointer 2-13
  (`rem_RecID`); the recipient is a user or a group, the pointer 2-1193 (stored in
  `rem_ToUserID` or `rem_ToWorkgroupID` by the kind of the id), or e-mail addresses 2-23
  (`rem_ToEmail`); the message 2-4 (at most 1000 characters), the start date 2-10, the
  Frequency 2-1191 (a term of the vocabulary 2-10454; stored as `once`, `daily`,
  `weekly`, `monthly`, `annually`). `rem_Nonce` is not mapped.
- `ReminderRecordOutput` — `GET /sys/reminder[/{id}]`: the user's own reminders; the
  database owner sees all.
- `ReminderWriteService` — the owner (or the database owner) changes and deletes; the
  record must be visible; exactly one recipient; the start date is today when not given;
  the frequency is `once` when not given. `send` e-mails the reminder of the body now
  without saving it (legacy "Send immediately") through the closure of
  `ServiceFactory::fromLegacySystem` (the legacy mailer).

| Route | Method | |
|---|---|---|
| `/sys/reminder/0` | POST | add (201) |
| `/sys/reminder/{id}` | PUT / PATCH / DELETE | change, delete |
| `/sys/reminder/{id}/validate` | POST | the checks only |
| `/sys/reminder/send` | POST | a reminder record → `{sent, record}`; 502 `not_sent` when the mailer fails |

Tests: `tests/SystemBookmarkReminderTest.php [--write]` (send with a fake mailer).
