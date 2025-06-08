This directory contains files related to record indexing, likely for search functionalities.

Specifically, the files in this directory are focused on providing indexing capabilities using Elasticsearch.

**Key Files:**
- `elasticSearch.php`: Contains the main class and functions for interacting with an Elasticsearch server, including indexing records, deleting the index, and performing status checks.
- `elasticSearchHelper.php`: Likely contains helper functions or classes to support the operations in `elasticSearch.php`.

**Original Overview (from _README.md):**
This directory contains indexing functions, specifically for Elastic Search.

Originally developed by Jan Jaap de Groot <jjedegroot@gmail.com> in 2012
but never used in the Heurist implementation due to installation of the Elastic Search
java code on the server spawning a lot of security warnings.

The aim of including Elastic search was:
1. to support fuzzy search
2. to investigate the possibility of faster facet searches (but it is probable that the Elastic search methods
will not align with the specificity of Heurist's nested graph structure searching capabilities)
