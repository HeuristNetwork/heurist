#harvest_concepts_to_semantic_refdb.php

## 1. Purpose of script

`harvest_concepts_to_semantic_refdb_api.php` harvests semantic definition records from registered Heurist databases and copies them into a local semantic reference database.

The script reads source definitions through the Heurist API and writes to the local target MySQL database directly via PDO.

It harvests the main definition tables:

- `defRecTypes` (`RTY`) — record type definitions
- `defDetailTypes` (`DTY`) — field/detail type definitions
- `defTerms` (`TRM`) — vocabulary and relation terms
- `defRecStructure` (`RST`) — record type field structure

The target database is intended to become a consolidated semantic reference database, preserving source provenance through the existing `xxx_OriginatingDBID` and `xxx_IDInOriginatingDB` fields.

## 2. Configuration

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

        // optional; Access credentials may be loaded from heuristConfigIni.php
        'dbAdminUsername' => 'heurist',
        'dbAdminPassword' => '<password>',
    ],

    'sources' => [
        [
            'server' => 'https://heuristref.net/heurist/',
            // This is specified simply to load a list of DBs on the server
            'registryDatabase' => 'Heurist_Concept_Definitions',

            // optional. Access credentials may be loaded from heuristConfigIni.php
            'username' => '2',
            'password' => '<password>',
        ],
    ],
];
```

### Target section

Required:

```php
'database' => 'hdb_Heurist_Concept_Definitions'
```

The script must not run unless `target.database` is explicitly defined.

Optional target fields:

```php
'dbHost' => '127.0.0.1'
'dbPort' => 3306
'dbAdminUsername' => 'heurist'
'dbAdminPassword' => '<password>'
```

If `dbAdminUsername` or `dbAdminPassword` are not provided, the script attempts to load them from:

```php
__DIR__ . "/../../../heuristConfigIni.php"
```

Expected variables in `heuristConfigIni.php`:

```php
$dbAdminUsername
$dbAdminPassword
$passwordForDatabaseAccess
```

### Source section

Each source entry defines one Heurist server and any database on the server (which is simply used as a starting point gateway to retrieve the list of registered databases on that server).

Required:

```php
'server' => 'https://heuristref.net/h7-alpha/'
'registryDatabase' => 'Heurist_Concept_Definitions'
```

Optional:

```php
'username' => '2'
'login' => '2'
'password' => '<password>'
'jwt' => '<pre-issued-token>'
'token' => '<pre-issued-token>'
'accessToken' => '<pre-issued-token>'
'authEndpoint' => '<custom-auth-endpoint>'
```

If both source username/login and password are omitted, the script defaults to:

```php
username = '2'
password = $passwordForDatabaseAccess
```

from `heuristConfigIni.php`.

If a username/login is provided but password is omitted, the script uses:

```php
$passwordForDatabaseAccess
```

as the source password.

## 3. Short description of workflow

### 3.1 Source database discovery

For each configured source server, the script authenticates if credentials or a token are available, then asks the starting point database for registered databases with `sys_dbRegisteredID > 0`.

The target database is skipped when the source server is local.

### 3.2 Loading definitions

For each registered source database, the script loads definitions through the Heurist API.

Definitions directly defined by the source database are harvested by origin fields:

- `rty_OriginatingDBID`
- `dty_OriginatingDBID`
- `trm_OriginatingDBID`

`RST` rows are not reliably harvested by `rst_OriginatingDBID`. Instead, the script loads `RST` rows by `rst_RecTypeID`, using the record type IDs harvested from `RTY`.

This means record structure is collected for record types defined by the source database.

### 3.3 Loading groups

The script creates target groups lazily, only when a new definition is actually inserted.

Group types:

- record type groups in `defRecTypeGroups`
- detail type groups in `defDetailTypeGroups`
- vocabulary groups in `defVocabularyGroups`

Vocabulary groups are separated by term domain:

- `enum`
- `relation`

Group names include the source database registered ID, for example:

```text
My Database [DB1623]
My Database [DB1623 enum]
My Database [DB1623 rel]
```

This avoids creating hundreds of empty groups during repeat runs or for source databases that do not introduce new definitions.

### 3.4 Loading references besides definitions defined in source

The script distinguishes between:

- harvested rows: definitions directly defined by the source database
- dependency/reference rows: external definitions needed to resolve references

Dependencies are collected from:

- `RST` rows: referenced `RTY` and `DTY`
- `DTY` rows: term-tree references and pointer target record types
- `TRM` rows: parent and inverse terms

Dependency rows are fetched so that references can be resolved to existing or newly inserted target IDs.

Depending on import policy, dependency rows may be inserted/reused or used only for reference resolution. The current script maps all loaded rows through `TargetIdMap`, which can reuse existing target definitions by their provenance pair.

### 3.5 Name duplication resolution

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

### 3.6 Terms and term links

Terms are inserted in two phases.

First, terms are inserted with neutralised references:

```php
trm_ParentTermID = NULL
trm_InverseTermID = NULL
```

After all target term IDs are known, the script updates parent and inverse references.

The script also keeps `defTermsLinks` synchronized explicitly. This is needed because legacy triggers may not reliably handle all transitions, especially repeat runs or parent changes.

To avoid duplicate `trl_CompositeKey` errors, the script:

1. Updates only changed term columns.
2. Clears old parent links before changing `trm_ParentTermID`.
3. Replaces the final parent link idempotently using `INSERT IGNORE`.

## 4. Running the script from command line

Run from the command line:

```bash
php /path/to/harvest_concepts_to_semantic_refdb_api.php
```

Recommended location depends on the local Heurist installation, for example:

```bash
cd /var/www/html/HEURIST/heurist/admin/describe
php harvest_concepts_to_semantic_refdb_api.php
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

## 5. Cron job entry

Example daily cron job:

```cron
15 2 * * * /usr/bin/php /var/www/html/HEURIST/heurist/admin/describe/harvest_concepts_to_semantic_refdb_api.php >> /var/log/heurist_semantic_harvest_cron.log 2>&1
```

Example weekly cron job:

```cron
30 3 * * 0 /usr/bin/php /var/www/html/HEURIST/heurist/admin/describe/harvest_concepts_to_semantic_refdb_api.php >> /var/log/heurist_semantic_harvest_cron.log 2>&1
```

Before adding to cron, run manually and confirm:

- the target database is correct
- source credentials work
- logs do not contain unresolved references or SQL errors
- repeat run is idempotent enough for production use

## 6. Operational notes

### Idempotency

The script is designed to be repeatable.

It reuses existing target rows by provenance:

```text
xxx_OriginatingDBID + xxx_IDInOriginatingDB
```

For record structure rows, it also checks the target composite pair:

```text
rst_RecTypeID + rst_DetailTypeID
```

to avoid duplicate structure rows.

### Transactions

Each source database is processed in one database transaction.

If import fails for a source database, changes for that database are rolled back and the script continues with the next database.

### Local target protection

When a configured source server points to the local Heurist installation, the script skips the configured target database to avoid harvesting the target into itself.

## 7. Troubleshooting

### Missing `target.database`

The script must stop if `target.database` is not defined in the config file.

Fix:

```php
'target' => [
    'database' => 'hdb_Heurist_Concept_Definitions',
]
```

### Authentication fails

Check that source credentials are either explicitly configured or available from:

```php
$passwordForDatabaseAccess
```

in:

```php
__DIR__ . "/../../../heuristConfigIni.php"
```

### Duplicate term link error

If MySQL reports:

```text
Integrity constraint violation: 1062 Duplicate entry ... for key 'trl_CompositeKey'
```

check the term update logic and confirm the script contains the duplicate-link protection:

- update only changed term columns
- clear existing parent links before changing `trm_ParentTermID`
- use `INSERT IGNORE` when replacing `defTermsLinks`

### Unexpected external definitions imported

Check whether those definitions were loaded as dependencies.

The usual cause is an overly broad `RST` harvest set. `RST` rows should be fetched by:

```text
rst_RecTypeID IN harvested RTY IDs
```

not by `rst_OriginatingDBID`.

### Unresolved source references

If the log reports unresolved source IDs, inspect the source database for broken references in:

- `dty_JsonTermIDTree`
- `dty_PtrTargetRectypeIDs`
- `trm_ParentTermID`
- `trm_InverseTermID`
- `rst_RecTypeID`
- `rst_DetailTypeID`

