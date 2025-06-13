# Directory: hserv/records/search

## Overview
This directory contains scripts and classes dedicated to searching and retrieving Heurist records. It includes logic for constructing search queries, interacting with search indexes, and formatting search results. Besides, it manages the related aspects like file information and duplicate detection.

## Key files
- `composeSql.php`: Implements a query composer that translates JSON (and plain text via JSON) search criteria into SQL queries. This is crucial for dynamic search capabilities.
- `composeSqlOld.php`: Translates Heurist query (older plain text format) to SQL query.
- `recordFile.php`: Function library for `recUploadedFiles` operations, including file registration, retrieval of file info, thumbnails, and player tags.
- `recordSearch.php`: Provides a library of functions for searching Heurist records, including main search logic, faceted search, min/max value retrieval, and related record searches.
- `recordsDupes.php`: Provides functionality to find and manage duplicate records, using methods like Levenshtein distance or Metaphone for comparison.
- `relationshipData.php`: Legacy (Heurist 3) function library to retrieve and assemble details for relationship records.
