# Directory: hserv/records/export

## Overview
This directory contains scripts and classes responsible for exporting Heurist records into various data formats like CSV, JSON, GeoJSON, XML, GEPHI, IIIF, and RDF.

## Key files
- `ExportRecords.php`: Abstract base class for exporting Heurist records in various formats.
- `ExportRecordsGEOJSON.php`: Handles exporting Heurist records in GeoJSON format.
- `ExportRecordsGEPHI.php`: Handles exporting Heurist records in GEXF (Gephi XML) format, suitable for network visualization in Gephi.
- `ExportRecordsIIIF.php`: Handles exporting records in IIIF (International Image Interoperability Framework) Presentation API format.
- `ExportRecordsJSON.php`: Handles JSON export.
- `ExportRecordsRDF.php`: Handles RDF format export using the EasyRdf library.
- `ExportRecordsXML.php`: Handles XML format export.
- `RecordsExportCSV.php`: Handles CSV format export.
