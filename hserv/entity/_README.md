Directory:    /hserv/entity

Overview: This directory contains classes that map to database tables and manage specific data entities (often following a `Db{TableName}.php` and `{TableName}.json` pattern for configuration and CRUD operations). It also includes classes for particular Record type (Annotation, Bug report) management and search and list Heurist databases.

Key Entity Classes:
- DbEntityBase.php: Base class for entity operations, providing common functionality.
- DbEntitySearch.php: Helper class for constructing and executing search queries against entities.
- DbDefCalcFunctions.php: Manages definitions for calculated functions.
- DbDefDetailTypeGroups.php: Manages definition for detail type groups.
- DbDefDetailTypes.php: Manages definitions for detail types.
- DbDefFileExtToMimetype.php: Manages definitions for file extension to MIME type mappings.
- DbDefRecStructure.php: Manages record structure definitions.
- DbDefRecTypeGroups.php: Manages definition for record type groups.
- DbDefRecTypes.php: Manages record type definitions.
- DbDefTerms.php: Manages term definitions.
- DbDefVocabularyGroups.php: Manages definition for vocabulary groups.
- DbRecThreadedComments.php: Manages threaded comment entities.
- DbRecUploadedFiles.php: Manages uploaded file entities.
- DbSysArchive.php: Manages system archive entities.
- DbSysDashboard.php: Manages system dashboard configurations.
- DbSysGroups.php: Manages system user groups.
- DbSysIdentification.php: Manages system identification settings.
- DbSysImportFiles.php: Manages imported file entities for the system.
- DbSysUsers.php: Manages system user accounts.
- DbSysWorkflowRules.php: Manages system workflow rules.
- DbUsrBookmarks.php: Manages user bookmark entities.
- DbUsrRecPermissions.php: Manages user record permissions.
- DbUsrReminders.php: Manages user reminder entities.
- DbUsrSavedSearches.php: Manages user saved search entities.
- DbUsrTags.php: Manages user tag entities.

- DbAnnotations.php: Manages Annotation Heurist records.
- DbSysBugreport.php: Handles bug reports (Record in Heurist bug tracker database) and contact form submissions.
- DbSysDatabases.php: Lists databases accessible to a user



Generic File Patterns:
- db{TableName}.php: Class for a particular database table {TableName}. Many specific entity classes follow this pattern (e.g., DbAnnotations.php).
- {TableName}.json: Configuration file that describes table fields and is used to create edit forms, in validation, and search (e.g., defCalcFunctions.json).

Updated:     2025-06-10

-------------------------------------------------------------------------------------------------------------------------------------
