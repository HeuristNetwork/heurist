# harvest_semantics_global.php

## 1. Purpose of script

`harvest_semantics_global.php` builds a consolidated Heurist semantic reference database by scanning registered Heurist databases through the Heurist API and importing semantic definitions into a local target database.

The script reads source definitions through the Heurist API and writes to the local target MySQL database directly via PDO.

It imports and consolidates the main definition tables:

- `defRecTypes` (`RTY`) — record type definitions
- `defDetailTypes` (`DTY`) — field/detail type definitions
- `defTerms` (`TRM`) — vocabulary and relation terms
- `defRecStructure` (`RST`) — record type field structure

The global-pass harvester differs from the older per-database harvester. It first builds a semantic map of all RTY, DTY and TRM definitions, preferring definitions from their originating databases where available. It then imports original record structures and finally adds additional field memberships found in other databases.

## 2. Important operational warnings

### 2.1 Target definition tables are cleared on first global-harvester run

If the target database does not yet contain the tracking table:

```sql
sysHeuristSemantics
```

the script treats this as the first run of the global harvester.

In that case, before importing anything, it clears all target tables whose names start with:

```text
def
```

This includes, for example:

- `defRecTypes`
- `defDetailTypes`
- `defTerms`
- `defRecStructure`
- `defTermsLinks`
- definition group tables such as `defRecTypeGroups`, `defDetailTypeGroups`, `defVocabularyGroups`

This is intentional. Without `sysHeuristSemantics`, the script has no reliable mapping between target-local IDs and semantic concept identities. Reusing a non-empty target database without this mapping can create duplicate definitions or broken references.

Do not run this script against a target database that contains manually curated definition data unless that data has been backed up or the required semantic tracking tables have already been created and populated.

### 2.2 `defTermsLinks` is imported from the source APIs

`defTerms.trm_ParentTermID` stores the explicit parent of a term. The separate
`defTermsLinks` table may contain both that explicit relationship and additional
implicit vocabulary membership links. It must therefore not be rebuilt only from
`trm_ParentTermID`.

After all terms have target-local IDs, the script requests:

```text
/api/{database}/termlinks
```

for every source database. Both `trl_ParentID` and `trl_TermID` are resolved from
source-local IDs through their semantic term identities and mapped to target-local
term IDs. Duplicate mapped relationships are suppressed.

All source pages are fetched and mapped before the target table is changed. The
script then replaces `defTermsLinks` inside the same transaction used for term
reference repair. If an API or mapping step fails, the previous target link table
is preserved by rollback.

## 3. Configuration

The script reads configuration from:

```php
harvest_concepts_to_semantic_refdb_cfg.php
```

The configuration file must return an array with two main sections:

```php
return [
    'target' => [
        'database' => 'hdb_Heurist_Concept_Definitions',
        'dbHost' => '127.0.0.1',
        'dbPort' => 3306,

        // optional; defaults may be loaded from heuristConfigIni.php
        'dbAdminUsername' => 'heurist',
        'dbAdminPassword' => '<password>',
    ],

    'sources' => [
        [
            'server' => 'https://heuristref.net/h7-alpha/',

            // optional; defaults may be loaded from heuristConfigIni.php
        ],
    ],
];
```

### 3.1 Target section

Required:

```php
'database' => 'hdb_Heurist_Concept_Definitions'
```

The script stops during initialization if `target.database` is not explicitly defined.

Optional target fields:

```php
'dbHost' => '127.0.0.1'
'dbPort' => 3306
'dbAdminUsername' => 'heurist'
'dbAdminPassword' => '<password>'
```

If `dbAdminUsername` or `dbAdminPassword` are not provided, the script attempts to load them from `heuristConfigIni.php`.

Expected variables in `heuristConfigIni.php`:

```php
$dbAdminUsername
$dbAdminPassword
$passwordForDatabaseAccess
```

### 3.2 Source section

Each source entry defines one Heurist server. The harvester retrieves registered databases from the server-level public `/api/databases` endpoint.

Required:

```php
'server' => 'https://heuristref.net/h7-alpha/'
```

Optional:

```php
'login' => '2'
'jwt' => '<pre-issued-token>'
'token' => '<pre-issued-token>'
'accessToken' => '<pre-issued-token>'
'authEndpoint' => '<custom-auth-endpoint>'
```

Source authentication parameters are no longer supported or required. Database discovery and definition searches use public API routes.

## 4. Target tracking tables

The script creates these target-only tracking tables before harvesting:

```sql
sysHeuristSemantics
sysHeuristSemanticStructures
```

### 4.1 `sysHeuristSemantics`

`sysHeuristSemantics` tracks imported semantic entities:

- `rty`
- `dty`
- `trm`

It records:

- entity type
- target-local ID
- originating DB ID
- ID in originating DB
- DB through which the definition was imported
- whether the row was imported from its origin DB or as a derived/fallback copy

`sem_TargetID` is the local ID in the target database. For example:

- `defRecTypes.rty_ID`
- `defDetailTypes.dty_ID`
- `defTerms.trm_ID`

Normal Heurist definition tables still use target-local IDs in their reference fields. Concept identity is stored in the semantic tracking table, not in normal foreign-key/reference fields.

### 4.2 `sysHeuristSemanticStructures`

`sysHeuristSemanticStructures` tracks imported record-structure memberships.

RST does not have a reliable standalone semantic concept identity for this purpose. Therefore RST is tracked by the pair:

```text
RTY concept + DTY concept
```

The target `defRecStructure` row still uses local target IDs:

```sql
rst_RecTypeID = target defRecTypes.rty_ID
rst_DetailTypeID = target defDetailTypes.dty_ID
```

The tracking table records the semantic identity of the RTY and DTY concepts, the target RST ID, and the database through which the structure was imported.

## 5. Workflow

### 5.1 Source database discovery

For each configured source server, the script calls the server-level public `/api/databases` endpoint with `details=full` and `sys_dbRegisteredID=>0`, then follows `pagination.next` until all registered databases are collected.

Discovered databases are ordered by registered ID. This increases the chance that original definitions are encountered before derived copies.

When a configured source server points to the local Heurist installation, the configured target database is skipped to avoid harvesting the consolidated target database back into itself.

### 5.2 RTY passes

The script performs two RTY passes.

First pass:

```text
Import RTY definitions only from their originating database.
```

Second pass:

```text
Import missing RTY definitions when first encountered in another database.
```

This means origin definitions win where available. Derived/fallback definitions are used only when the original database is unavailable or does not provide the definition.

### 5.3 DTY passes

The script performs the same two-pass process for DTY definitions.

First pass:

```text
Import DTY definitions only from their originating database.
```

Second pass:

```text
Import missing DTY definitions when first encountered in another database.
```

Pointer target record types are resolved through `sysHeuristSemantics` and written back as normal target-local RTY IDs in:

```sql
dty_PtrTargetRectypeIDs
```

### 5.4 TRM passes

The script performs the same two-pass process for TRM definitions.

First pass:

```text
Import TRM definitions only from their originating database.
```

Second pass:

```text
Import missing TRM definitions when first encountered in another database.
```

Terms are imported first with parent and inverse references neutralised. After all terms are known, the script repairs:

```sql
trm_ParentTermID
trm_InverseTermID
```

using semantic mappings and target-local IDs. It also imports the complete
`defTermsLinks` relationship set from the public `termlinks` API so both explicit
and implicit vocabulary links are retained.

### 5.5 Reference repair

After RTY, DTY and TRM import passes, the script performs reference repair.

It repairs:

- `dty_PtrTargetRectypeIDs`
- `dty_JsonTermIDTree`
- `trm_ParentTermID`
- `trm_InverseTermID`

Concept identities are used for matching, but the repaired values stored in definition tables are normal target-local IDs.

### 5.6 Original RST structure pass

The script scans record types in their origin databases and imports their original `defRecStructure` rows.

This imports the original structure of each record type where the original database is available.

RST rows are inserted only when the target RTY and target DTY can be resolved.

### 5.7 Additional RST structure pass

The script then scans all RST rows and adds missing RTY+DTY field memberships discovered in other databases.

This adds fields that were added to imported record types outside the original defining database.

The script does not preserve every variant of RST settings. If the target record type already has the same DTY, the existing structure row is kept and variants such as display name, requirement, repeatability or description are ignored.

New additional RST rows are appended to the end of the target record type structure and marked as:

```sql
rst_Status = 'pending'
```

## 6. Groups

By default, target groups are created from the database provenance of the imported definition.

Group types:

- record type groups in `defRecTypeGroups`
- detail type groups in `defDetailTypeGroups`
- vocabulary groups in `defVocabularyGroups`

Vocabulary groups are separated by term domain:

- `enum`
- `relation`

For definitions imported from their own origin database, the group name is:

```text
Database_Name [DB###]
```

For definitions imported as derived/fallback copies through another database, the group name is:

```text
Origin_DB_xxxx_via_Database_Name [DByyyy]
```

where:

- `xxxx` is the concept's originating DB ID
- `yyyy` is the registered DB ID where this definition row was actually found and imported from

### Heurist core definitions exception

Definitions found in, or originating from, `Heurist_Core_Definitions` are consolidated into:

```text
Heurist_Core_Definitions [DB2]
```

This avoids creating many small fallback groups for core definitions that are frequently encountered through the core database.

### Single-group mode

The script supports a single-group mode for simpler test or production target databases.

CLI examples:

```bash
php harvest_semantics_global.php --single-group=1
php harvest_semantics_global.php --isSingleGroup=1
php harvest_semantics_global.php isSingleGroup=1
```

HTTP/request examples if the script is invoked through a web request:

```text
?isSingleGroup=1
?singleGroup=1
```

The option can also be set in the config:

```php
return [
    'target' => [
        'database' => 'hdb_Heurist_Concept_Definitions',
        'isSingleGroup' => 1,
    ],
];
```

When enabled, the harvester creates/reuses one group per definition table/domain:

- `Imported definitions` in `defRecTypeGroups`
- `Imported definitions` in `defDetailTypeGroups`
- `Imported definitions enum` in `defVocabularyGroups`
- `Imported definitions rel` in `defVocabularyGroups`

## 7. Name duplication resolution

Target tables may already contain definitions with the same human-readable name.

For `RTY` and `DTY`, when inserting a definition with a duplicate name, the script appends a provenance suffix:

```text
Original Name [RTY1623-57]
Original Name [DTY1623-969]
```

The original source name is still preserved in:

- `rty_NameInOriginatingDB`
- `dty_NameInOriginatingDB`

The generated name is truncated safely to respect target column length limits.

## 8. Running the script from command line

Run from the command line:

```bash
php /path/to/admin/harvest/harvest_semantics_global.php
```

Recommended location depends on the local Heurist installation, for example:

```bash
cd /var/www/html/HEURIST/heurist/admin/harvest
php harvest_semantics_global.php
```

The script writes progress to:

```text
harvest_concepts_to_semantic_refdb.log
```

The script should normally be run by a system user that has:

- read access to `harvest_concepts_to_semantic_refdb_cfg.php`
- read access to `heuristConfigIni.php`
- MySQL write access to the configured target database
- HTTP/API access to configured source servers

## 9. Cron job entry

Example daily cron job:

```cron
15 2 * * * /usr/bin/php /var/www/html/HEURIST/heurist/admin/harvest/harvest_semantics_global.php >> /var/log/heurist_semantic_harvest_cron.log 2>&1
```

Example weekly cron job:

```cron
30 3 * * 0 /usr/bin/php /var/www/html/HEURIST/heurist/admin/harvest/harvest_semantics_global.php >> /var/log/heurist_semantic_harvest_cron.log 2>&1
```

Before adding to cron, run manually and confirm:

- the target database is correct
- the target DB can be cleared on first run, or has already been initialized by this global harvester
- source credentials work
- logs do not contain unresolved references or SQL errors
- repeat runs do not overwrite curated definitions unexpectedly

## 10. Operational notes

### 10.1 First imported version wins

The script does not automatically update existing imported RTY, DTY or TRM definitions on rerun.

Origin imports are preferred because origin passes run before derived/fallback passes. Derived imports only fill missing concepts.

This protects curated target definitions from being overwritten by later source changes or local variants in other databases.

A future improvement may add a difference table to record changed names, descriptions, settings or structure variants without applying them automatically.

### 10.2 Idempotency

The script is designed to be repeatable.

It reuses existing target rows through:

```sql
sysHeuristSemantics
```

for RTY, DTY and TRM.

It reuses existing structure rows through:

```sql
sysHeuristSemanticStructures
```

and the target pair:

```text
rst_RecTypeID + rst_DetailTypeID
```

for RST.

### 10.3 Transactions

The script performs large global passes rather than one transaction per source database.

Some operations, such as first-run cleanup and table creation, are intentionally performed before normal harvesting starts.

### 10.4 Local target protection

When a configured source server points to the local Heurist installation, the script skips the configured target database to avoid harvesting the target into itself.

## 11. Troubleshooting

### Missing `target.database`

The script stops if `target.database` is not defined in the config file.

Fix:

```php
'target' => [
    'database' => 'hdb_Heurist_Concept_Definitions',
]
```

### First run cleared the definition tables

This is expected if `sysHeuristSemantics` did not exist.

The script assumes that a target database without `sysHeuristSemantics` has no reliable semantic mapping. It therefore clears all `def*` tables before creating the tracking tables and importing definitions.

Restore from backup if the script was run against the wrong target database.

### Authentication fails

Check that source credentials are either explicitly configured or available from:

```php
$passwordForDatabaseAccess
```

in `heuristConfigIni.php`.

### `defTermsLinks` is empty

Verify that each source server supports the public endpoint:

```text
/api/{database}/termlinks
```

Check the harvest log for fetched, mapped and unresolved link counts. A link is
skipped when either source-local term ID cannot be resolved to a semantic term in
the target. Confirm that both linked terms were returned by the source `trm` API
and imported into the target.

### Duplicate term link error

Mapped links are deduplicated in memory and inserted with `INSERT IGNORE`. If a
legacy trigger inserts term links while `trm_ParentTermID` is repaired, the table
is cleared first and the complete API-derived relationship set is restored in the
same transaction.

### Unexpected external definitions imported

This can happen in derived/fallback passes.

The intended rule is:

- origin definition wins if available
- first encountered derived definition is imported only if the origin definition was not imported

Check `sysHeuristSemantics`:

```sql
SELECT *
FROM sysHeuristSemantics
WHERE sem_EntityType = 'rty'
  AND sem_IsDerivedImport = 1;
```

### Unresolved source references

If the log reports unresolved source IDs, inspect the source database for broken references in:

- `dty_JsonTermIDTree`
- `dty_PtrTargetRectypeIDs`
- `trm_ParentTermID`
- `trm_InverseTermID`
- `rst_RecTypeID`
- `rst_DetailTypeID`
