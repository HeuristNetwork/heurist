# Date interpretation audit

## Scope

Reviewed the CSV upload form/request, `ImportParser::parseAndValidate` and `prepareDateField`, saved-session serialization, `ImportAction::validateDateField` and `performImport`, the `recordModify.php` date branch, and the shared `Temporal` preparation/formatting/range/storage methods. Also inspected the separate browser parser in `hclient/core/temporalObjectLibrary.js` and its use by `editTemporalObject.js`. The `srv/Utilities/Temporal.php` copy is separate from the `hserv` CSV path.

The correction establishes strict interpretation at the CSV boundary. It does not claim to have replaced every date parser used by Heurist's editor, search, API, HML importer or older code paths.

## Findings and treatment

| Original behaviour | Failure mode | Treatment in this correction |
|---|---|---|
| The date selector is sent during parsing but omitted from the saved import session | Later date mappings lose DMY and use Temporal's MDY default | Persist and reuse `csv_dateformat`; normalise before saving every date field |
| Upload preparation only converts columns specifically assigned the date role | A column mapped later, or repeated dates, can bypass the selected order | Normalise all mapped date values during actual import |
| `_datePrepare` removes `?` then uses `new DateTime` | `1449? 1457?` becomes a time and year with the current month/day | Reject uncertainty syntax before shared parsing; retain/report source |
| PHP fills omitted date components from the clock | `August` becomes a date in the current year and day | Require explicit year and recognised components |
| PHP accepts overflow with warnings, but the original code only catches exceptions | 31 April becomes 1 May; invalid leap days roll forward | Validate calendar components directly |
| `correctDMYorder` guesses from magnitudes, delimiter and a fixed two-digit-year cutoff | Ambiguous data may be swapped or assigned the wrong century | Honour one selected order; reject ambiguous short years in full dates |
| Any integer over 9999 may be read as a compact date | Long years or identifiers become dates; excess digits can be truncated | Reject unsupported compact/extended positive forms |
| Presence of nonzero hours/minutes/seconds determines whether time exists | Explicit 00:00 or 00:00:00 disappears | Shared formatter now tracks explicit time presence |
| Generic parsing can discard timezone and fractional-second information | Date/time meaning or precision silently changes | Reject these CSV forms until storage can preserve them |
| `Temporal::isValid` only tests for a non-null parsed structure | Invalid JSON endpoint dates, malformed objects or reversed ranges may pass | Validate allowed structure, endpoints, deviations and chronological bounds before Temporal |
| Loose circa/before/after regexes search inside arbitrary text | Fragments can be interpreted as qualifiers | Whole-input qualifier patterns with a validated date operand |
| A pipe marks both repeated values and a legacy temporal | Encoded dates can be split into fragments | Recognise an encoded temporal before repeat splitting |
| Date validation has a second unrestricted DateTime fallback | Guarding only upload preparation would not solve corruption | Remove fallback; use the same strict parser everywhere in CSV |
| Invalid dates were warnings and “ignore errors” can bypass validation | Unsafe values could still reach storage | Date errors plus a separate preflight before record writes |
| Year-only decimal indexing reads a missing month key | PHP warnings during valid year-only imports | Use a presence check |

## Reproductions with the original algorithm

With the original default order, the current code produces exactly:

```
01/08/878 -> 0878-01-08
02/05/939 -> 0939-02-05
02/10/933 -> 0933-02-10
```

Calling the original helper with order `1` instead produces the requested DMY dates. This confirms the difference between explicit order and the later default path; there is no France/server locale detection involved.

On 23 September the original parser produced:

```
1449? 1457? -> 1457-09-23 14:49
1449 1457   -> 1457-09-23 14:49
31/04/2024  -> 2024-05-01
August      -> 2026-08-23
```

The reported `1457-08-06 14:49` is consistent with the same clock-dependent parsing on 6 August. That inference does not establish when the user's import actually ran.

## Remaining concerns outside the corrected CSV path

- The generic `Temporal::_datePrepare`, `correctDMYorder` and `_parseTemporal` remain permissive for other callers. Their broad syntax is used by search and other workflows; changing it globally needs compatibility tests for those workflows. The new CSV parser prevents unsafe source strings reaching them.
- The generic parser still strips question marks, guesses centuries, accepts PHP overflow and can confuse free text with temporal qualifiers outside CSV. Merely switching its default from MDY to DMY would not solve those issues and would change unrelated callers.
- Generic JSON parsing contains coercions of decimal/array values and does not constitute full temporal-schema validation. CSV now rejects such inputs or validates the supported schema.
- Browser `TDate.parse` is a separate heuristic parser. It infers component roles and converts hour 24 to 0 without visibly advancing the date in the examined branches. JavaScript `new Date(year,month,day)` also has special behaviour for years 0–99. These are separate editor/display concerns; no browser-library rewrite is included.
- Heurist's decimal date index is day-oriented and should not be treated as a full-precision time index. This correction preserves explicit imported time text but does not redesign indexing.
- Gregorian leap-year checks are used for imported numeric dates. No automatic historical Julian/Gregorian calendar conversion is performed.
- CSV database-verification comparisons are a separate feature. This patch does not replace prior verification/reporting enhancements or claim to repair already corrupted data.

## Changed code locations

- `importParser.php`: parameter default, saved preprocessing/session settings, `prepareDateField`.
- `importAction.php`: date validation severity, `validateDateField`, new `getDateValues` and `checkImportDates`, preflight call, date repeat handling and final date assignment in `performImport`.
- `importDate.php`: `normalise`, `simple`, `temporal`, `legacyTemporal`.
- `Temporal.php`: `_dateDecimal` year-only guard, `_datePrepare` explicit-time flag, `dateToISO` and `dateToString` midnight handling.
- `importRecordsCSV.php`: date format selector markup and helper text.

The unified patch supplies exact before/after code and line context. The complete production files preserve unrelated GitHub code and documentation.
