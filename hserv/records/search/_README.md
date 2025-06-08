This directory contains files related to record searching and retrieval.

These files provide functionalities for constructing and executing search queries, fetching record data, and managing related aspects like file information and duplicate detection.

**Key Files:**
- `composeSql.php`: Implements a query composer that translates JSON (and plain text via JSON) search criteria into SQL queries. This is crucial for dynamic search capabilities.
- `composeSqlOld.php`: Likely an older version of the SQL composer, possibly kept for backward compatibility or reference.
- `recordSearch.php`: Contains core functions for performing record searches based on various criteria, retrieving record data, and related helper functions.
- `recordFile.php`: Manages operations related to files attached to records, such as retrieving file information or paths.
- `recordsDupes.php`: Provides functionalities for finding and managing duplicate records within the database.
- `relationshipData.php`: Handles the retrieval and processing of relationship data between records.

**Original Overview (from _README.md):**
General and specific search functions. Query composer (plain text and json to sql)

composeSql.php - json (and  plain text to json)  to sql query composer

