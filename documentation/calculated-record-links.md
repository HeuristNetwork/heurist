# Calculated record links from storage history

The legacy record editor can attach a formula to a record-link (`resource`)
field. A formula must output one existing, non-temporary record ID of an allowed
target type, or an empty string to clear the field. Invalid output reports an
error and does not replace the stored calculation value. Multiple IDs, HTML
links, and record titles are not pointer values.
When a calculated pointer is evaluated, duplicate stored values are replaced
with its one calculated target, even if the first stored value already matches.

## Current Location configuration

For the collection database inspected for this change:

| Definition | ID |
| --- | --- |
| Item record type | 104 |
| Box record type | 115 |
| Housing Location record type | 114 |
| Current Location, single record link | 1177 |
| Storage Location, repeatable relationship marker | 1171 |
| Storage relationship types: Permanent, Temporary, Unknown | 9878, 9879, 13113 |
| Relationship Start date/time | 10 |

Create a named calculation with the contents of
[`examples/current-location.tpl`](examples/current-location.tpl). Assign it to
Current Location in both Item and Box record types through the field's Formula
selector. Keep Current Location single-valued. The rule uses outgoing
relationships whose target is a Housing Location and whose relationship type
is in the listed storage vocabulary. It does not follow Item-to-Box links or
infer an Item's location from its Box.

The formula returns the housing target of the relationship with the latest
Start date/time. Dated relationships always outrank undated ones. Only if no
candidate has a date does relationship modification time determine the result.
Equal dates use relationship modification time, then the highest relationship
record ID. For date ranges or uncertain dates, the earliest estimated bound is
used. An invalid non-empty date reports an error rather than being treated as
an undated move. End date/time is not used to exclude relationships.

The helper returns an empty string when no storage history matches. The example
formula explicitly preserves the existing Current Location in that case,
including when the last matching relationship is deleted. Records without either
storage history or a Current Location remain empty. Deleting a Housing Location
still removes pointers to that deleted record; a calculation never restores an
invalid target.
New relationship types added to the storage vocabulary must be added to the
formula's list of IDs.

## Automatic updates and rebuilds

The exact Smarty comment `heurist:refresh-on-relationship-change` in the example
opts into a local dependency rule. On a legacy relationship save, refresh marked
formulas on its previous and new source records. This includes first saves,
changed dates, targets, types and sources, and imports using `recordSave`.
Deleting a relationship or a housing target refreshes the surviving source
records, including relationship deletions reached through child-record deletion.
Only records with an opted-in formula are processed. This avoids the existing
100-record limit for automatic database-wide dependency rebuilds.

The comment replaces the formula's broad Additional record type(s) dependency
behavior. Use it only for calculations depending on outgoing relationships of
the current record. Existing formulas without the comment keep their current
dependency behavior. Editing unrelated Housing Location metadata does not change
relationship modification time and does not refresh the source calculation.

After deploying the code and assigning the formula, use Admin > Rebuild
calculation fields to populate existing Items and Boxes. The field-design flow
also opens a rebuild dialog when a calculation is assigned. Take a backup and
review changes on a staging copy first. The supplied example preserves manual
pointers for records with no matching storage history. No schema migration is required.

These relationship refresh hooks cover the legacy record editor/controller.
The new API currently permits writes only to selected system record types,
which exclude Item, Box and Relationship. Its generic calculated-pointer writer
also validates target IDs, but this relationship helper and dependency refresh
are not implemented in the new report/write engine. SQL-only mutations and
legacy update paths that bypass `recordSave`/`deleteOneRecord` require a rebuild.

## Verification

Run `php admin/verification/RelationshipCalculationTest.php` for selection tests.
Run `php admin/verification/RelationshipCalculationDatabaseTest.php
--disposable-mysql` only in an environment with an isolated disposable MySQL
server on `127.0.0.1`, with empty root password (or the test environment's
`HEURIST_MYSQL_ROOT_PASSWORD`). It creates a uniquely named test
database, installs the production schema and triggers, exercises the real Smarty
calculation writer and save/delete hooks, and removes that database on success.
It never reads the collection database's access file.
