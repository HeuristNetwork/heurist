# Directory: hserv/entity

## Overview
This directory contains PHP classes that represent data entities or models, likely corresponding to database tables. These classes typically encapsulate data and provide methods for Create, Read, Update, and Delete (CRUD) operations or other data-specific logic. Associated JSON files hold schema definitions, configurations, or initial data for these entities.
It also includes classes for particular Record type (Annotation, create ticket) management and search and list Heurist databases (they use the same base class DbEntityBase.php).

## Key files
- `DbDefCalcFunctions.php`: Provides database access and operations for the `defCalcFunctions` table, which stores definitions for calculated fields.
- `DbDefDetailTypeGroups.php`: Provides database access and operations for the `defDetailTypeGroups` table, which stores groups for detail types (field types).
- `DbDefDetailTypes.php`: Provides database access and operations for the `defDetailTypes` table, which stores definitions for detail types (field types).
- `DbDefFileExtToMimetype.php`: Provides database access and operations for the `defFileExtToMimetype` table, which maps file extensions to MIME types.
- `DbDefRecStructure.php`: Provides database access and operations for the `defRecStructure` table, which defines the structure of record types.
- `DbDefRecTypeGroups.php`: Provides database access and operations for the `defRecTypeGroups` table, which stores groups for record types.
- `DbDefRecTypes.php`: Provides database access and operations for the `defRecTypes` table, which stores definitions for record types.
- `DbDefTerms.php`: Provides database access and operations for the `defTerms` table, storing vocabulary terms for enumerated fields and relationship types.
- `DbDefVocabularyGroups.php`: Provides database access and operations for the `defVocabularyGroups` table, which stores groups for vocabularies.
- `DbEntityBase.php`: Base class for database entities, handling configuration, field management, and base CRUD operations.
- `DbEntitySearch.php`: Handles construction and execution of search queries for database entities, including parameter validation and WHERE clause building.
- `DbRecThreadedComments.php`: Provides database access and operations for the `recThreadedComments` table, storing threaded comments related to records.
- `DbRecUploadedFiles.php`: Provides database access and operations for `recUploadedFiles`, including file registration (local, remote, IIIF), thumbnails, and searching.
- `DbSysArchive.php`: Provides access to `sysArchive` table, logging historical record changes and supporting search/reversion of history.
- `DbSysDashboard.php`: Provides database access and operations for the `sysDashboard` table, storing configuration for dashboard entries.
- `DbSysGroups.php`: Provides database access for workgroups in `sysUGrps`, handling CRUD operations and user memberships/roles.
- `DbSysIdentification.php`: Provides database access for `sysIdentification` table, storing database-specific properties and settings.
- `DbSysImportFiles.php`: Provides database access for `sysImportFiles` table, storing information about file import sessions.
- `DbSysUsers.php`: Provides database access for user accounts in `sysUGrps` (where `ugr_Type` = 'user'), handling CRUD and special actions.
- `DbSysWorkflowRules.php`: Provides database access for `sysWorkflowRules` table, defining workflow rules and stages for record types.
- `DbUsrBookmarks.php`: Provides database access for `usrBookmarks` table, storing user-specific bookmarks on records (ratings, notes).
- `DbUsrRecPermissions.php`: Provides database access for `usrRecPermissions` table, storing record-level permissions for groups.
- `DbUsrReminders.php`: Provides database access for `usrReminders` table, storing user-created reminders for records.
- `DbUsrSavedSearches.php`: Provides database access for `usrSavedSearches` table, storing user-defined saved search queries.
- `DbUsrTags.php`: Provides database access for `usrTags` table, storing user-created tags applicable to records.

- `DbAnnotations.php`: Manages records with type IIIF annotations, providing functionality to search, create, update, and delete annotations.
- `DbSysBugreport.php`: Handles bug reports and contact form submissions, including creating bug records and sending email notifications.
- `DbSysDatabases.php`: Provides functionality to list databases accessible to a user.

- `{TableName}.json`: Configuration files that describes table fields and is used to create edit forms, in validation, and search (e.g., defCalcFunctions.json).
