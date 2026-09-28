# Automatic repository safeguards

The Design > External repositories screen holds one schedule for the database
(30 days by default), a selection of configured Nakala/Zenodo accounts and an
optional email attachment. Only database managers can save it. Any logged-in
user who opens Explore can trigger a due check.

Safeguarding is experimental. Unless the server's `$experimental` variable is
strictly `TRUE`, the section is greyed and disabled with the standard
unavailable hover message. Saving is rejected by the controller, login checks
do nothing, and an already queued CLI worker exits before depositing data.
The manual Nakala upload can still create a private pending deposit, but its
"Publish & register DOI" control is disabled with the same hover message until
the flag is enabled. The publish controller enforces this and administrator
access independently of the browser.

The browser calls `repoController.php?a=backup_check`. It returns immediately,
launching `automaticSafeguard.php` using PHP CLI if at least one selected account
is due. A per-database file lock prevents overlapping jobs, while a one-hour
attempt cooldown prevents a failing server from launching work on every login.
The CLI job fingerprints the record, uploaded-file and structure tables using
`CHECKSUM TABLE ... EXTENDED` and `SHOW CREATE TABLE`. If their contents match
the last successful upload to each selected account, no archive is made and
the date of the last successful backup remains unchanged. On large databases,
the checksum step can take considerable time and database I/O.

The job calls the existing `buildArchivePackagesCMD.php` without its skip
flags, so the complete self-documenting archive includes SQL, HML, TSV, files
and documentation. It deposits to each selected account independently:

* Zenodo: first deposit creates/publishes a restricted dataset. Subsequent
  deposits call `newversion`, replace the inherited file in the draft and
  publish it. The concept DOI stays fixed; version DOIs are recorded separately.
* Nakala: first deposit publishes a data record with a permanently embargoed
  archive file. Later files are added to the same record. Its DOI stays fixed.

The published metadata is public; safeguard files are restricted because
the full SQL dump can contain user preferences and repository API keys. The
database-wide DOI is written to `settings/DOIs.json` under `database_doi`;
per-account DOIs are in `database`. Scheduling state, last backup dates,
draft IDs, table fingerprints and the owner notice are written to
`settings/safeguard_backups.json`. API keys remain in their existing
`sysUGrps.ugr_Preferences` locations.

The job emails the database owner and saves a one-time notification displayed
at the next administrator login. A failed repository does not advance its
successful date, and will be retried after the cooldown on the next login.

## Deployment checks

1. Verify the PHP CLI binary exists at `PHP_BINDIR/php` and PHP can start
   subprocesses. The archive builder itself runs `php` on the PATH for HML.
2. Check cURL, ZIP support, PHP mail delivery, MySQL access and write access
   to the database `settings/` directory and filestore backup directory.
3. Test first and subsequent deposits with disposable Nakala test and Zenodo
   sandbox accounts before enabling production accounts. Confirm that a
   non-owner cannot download either published archive file.
4. Test a changed `recUploadedFiles` row, a changed structure row, an
   unchanged database, a failed upload, interrupted publication and a large
   email attachment.

The repository APIs have not been exercised from this development workspace:
it has no PHP binary, MySQL database or repository credentials. Keep the
feature off production until the above integration checks pass.
