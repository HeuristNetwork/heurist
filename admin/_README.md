# Directory: /admin

## Overview

This directory contains all the administrative interface code and server side utilities. 

Many scripts are from Heurist Vsn 3, also used by Heurist Vsn 4 and 5.

Vsn 6: In the end, all this is fairly simple code which has worked for years and has no relation to the performance and structure
of the main data entry and retrieval functions, so it has been left untouched. It is the product of many hands, but it works.

## Subfolders

- `describe/`: This directory contains scripts related to gathering, displaying, and managing database and server statistics.
- `setup/`: This directory contains functions used in setting up new databases, including model directories.
- `utilities/`: This directory provides a collection of utility scripts for server and database administration, including tools for bulk emailing, cleaning up filestores, managing interaction logs, notifying users about archiving, and purging inactive database components or full-text indexes, as well as generating indexable web pages for databases.
- `verification/`: This directory contains a suite of scripts for database verification, integrity checking, data repair, and rebuilding various components. Tasks include checking URLs, saved searches, XHTML validity, managing duplicate records, converting field types, verifying file linking, finding orphaned items, fixing CMS paths, listing user accounts, and rebuilding/repairing titles, calculated fields, entry masks, and search indices.

