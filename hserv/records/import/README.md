# Heurist CSV date interpretation correction

Based on HeuristNetwork/heurist, h7dev commit b3d9bac0b65e66d119ab69c7291de45fb411240e, inspected 23 September 2026.

## Installation

The `files/` directory contains five complete production files with their original paths, plus an optional regression test. Copy the production files to the corresponding paths under your Heurist installation. `hserv/records/import/importDate.php` is NEW and must be included.

Alternatively, from the Heurist repository root:

```bash
git apply --check Heurist_CSV_date_interpretation.patch
git apply Heurist_CSV_date_interpretation.patch
```

Use the patch when you have additional local changes: it changes only the relevant sections. The complete files are based on the GitHub commit above, not on locally modified files or earlier downloadable patches.

Reload the original CSV from scratch after installing. Select `dd/mm/yyyy` for the French source. An old import session may already contain converted dates; those cannot be corrected reliably by changing the interpretation option. Old sessions did not save the chosen order; this correction defaults those sessions to day/month/year. For an old US-format source, reload it with `mm/dd/yyyy` selected.

No SQL migration is required. Installing these files does not repair existing database dates or write database records. The correction has not been deployed to your server or pushed to GitHub.

## Corrected examples

| Source | With dd/mm/yyyy selected |
|---|---|
| 01/08/878 | 0878-08-01 |
| 02/05/939 | 0939-05-02 |
| 02/10/933 | 0933-10-02 |
| 0878-08-01 | 0878-08-01 |
| 878 | 0878 (year precision retained) |
| 01/08/878 00:00:00 | 0878-08-01 00:00:00 |
| 1449? 1457? | Retained in the source table and reported as uninterpretable |
| 31/04/2024 | Reported as invalid; never rolled into May |
| 01/02/17 | Reported as ambiguous; specify 0017, 1917 or 2017 |
| 1449 to 1457 | Explicit Heurist temporal range |

The question-mark example does not establish whether the author means a range, alternatives, or something else. The correction deliberately does not select one meaning.

## What changed

1. `hserv/records/import/importDate.php` — new strict parser shared by upload preparation, validation and saving. It uses explicit components and calendar checks, not free-form PHP date guessing.
2. `hserv/records/import/importParser.php` — saves `csv_dateformat` in the import session; preparation uses the strict parser and retains uninterpretable source values.
3. `hserv/records/import/importAction.php` — uses the saved preference for later field mappings, removes the permissive validation fallback, makes invalid dates errors, checks every mapped date before record writes, and normalises every repeated date before passing it to recordSave. Encoded temporal pipes are not treated as repeat delimiters. Error values are HTML-escaped.
4. `hserv/utilities/Temporal.php` — retains explicit midnight in ISO output and human-readable output; avoids an undefined-month warning for year-only values. The generic free-form parser has not otherwise been rewritten.
5. `import/delimited/importRecordsCSV.php` — explains the selection and strict behaviour; removes a duplicate closing select tag.

`files/hserv/records/import/tests/importDateTest.php` is optional and can be run with:

```bash
php hserv/records/import/tests/importDateTest.php
```

## Interpretation rules

- The selection, not the server location/timezone, determines the order of numeric dates with the year last. Default is day/month/year; explicit US order remains available.
- An unambiguous year-first date stays year-first. Numeric `/`, `.` and `-` separators are accepted when consistent.
- Three- and four-digit historical years are padded correctly. In a full date, one- or two-digit years require explicit padding; there is no century pivot.
- Dates with an impossible month/day, mixed separators, time without a complete date, overflow hours/minutes/seconds, trailing junk, question marks or missing years are rejected.
- Supported English/French month names are parsed explicitly. Relative expressions such as today, tomorrow and next Monday are rejected for CSV import.
- Year-only and month-only precision is retained. The shared storage format represents a month as its actual first/last days.
- Explicit circa/before/after qualifiers and explicit simple ranges are represented as temporal metadata. Bounds must be possible and not reversed; uncertainty intervals may overlap.
- Supported JSON and legacy pipe temporals have their date endpoints and deviations checked. Unknown structures/tags and malformed values are rejected instead of being silently discarded. Exported numeric date indexes are recalculated.
- No fallback swaps day/month merely because the selected interpretation is invalid. Correct the format selection or the cell.
- Compact integers such as 20240101, timezone-bearing timestamps, fractional seconds, duration expressions, and unsupported free text are reported for correction. In particular, the legacy storage parser discards timezone/fraction information, so these are not silently accepted. Use a supported explicit date/time after deciding the intended timezone. Positive years beyond 9999 and BCE dates with month/day before -9999 are also rejected to avoid existing storage-parser ambiguities. BCE year-only values through six digits are supported.
- Invalid dates cannot be imported using the general “ignore errors” option. The saving preflight returns an error before any record writes for that import step, including when the validation UI is bypassed. Earlier completed steps are unaffected.

## Verification

300 assertions pass on actual PHP 8.0 and PHP 8.3 engines running via PHP-WASM. Tests cover the reported examples, DMY/MDY selection, malformed dates, leap years, uncertainty, partial dates, historical/BCE dates, times, encoded objects, repeated values, preparation, validation reports, preflight and downstream Temporal storage conversion. The importer tests invoke the actual pure methods with an in-memory result set, without bootstrapping a database.

PHP syntax checks pass for all modified production files and the new parser. `git diff --check` passes. No live MySQL import or browser workflow was run; use a small test CSV in a test database before a bulk update.

See `DATE_PARSING_AUDIT.md` for the broader findings and remaining non-CSV concerns.
