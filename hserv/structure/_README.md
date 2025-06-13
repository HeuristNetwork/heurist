# Directory: hserv/structure

## Overview
This directory is concerned with the definition, management, and manipulation of the Heurist database structure. This includes entities like record types, fields, vocabularies, and their relationships.

## Key files
- `ConceptCode.php`: Static utility class for translating between local Heurist database IDs and global Concept IDs for definitions (Terms, DetailTypes, RecordTypes).
- `dbsSavedSearches.php`: Functions library to work with the `usrSavedSearches` table. (To be replaced by entity/DbUsrSavedSearches).
- `dbsTerms.php`: Provides an in-memory interface for accessing and manipulating Heurist term taxonomies (vocabularies).
- `dbsUsersGroups.php`: Library for managing Users/Groups (from `sysUGrps` table) and User Preferences (from SESSION).

## Subfolders
- `edit/`: Manages aspects of database structure editing.
- `export/`: Manages aspects of database structure exporting.
- `import/`: Manages aspects of database structure importing.
- `search/`: Provides functionalities for searching or querying database structure elements.
 