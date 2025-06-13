# Directory: hserv/records/import

## Overview
This directory contains scripts and classes for importing data into Heurist records from various sources and formats (CSV, JSON, XML and KML). As well as import IIIF annotations.

## Key files
- `ImportAnnotations.php`: Handles the import of IIIF (International Image Interoperability Framework) annotations into the Heurist system.
- `importAction.php`: Handles the data once it's in a temporary table. This includes matching incoming data with existing records, assigning IDs, validating data, and finally creating or updating records.
- `importHeurist.php`: Handles the import of records and definitions directly from Heurist exchange format files (JSON or HML/XML).
- `importParser.php`: Handles the initial stages of importing data from files, primarily CSV and KML/KMZ.
- `importSession.php`: Provides static methods to manage import session data.
