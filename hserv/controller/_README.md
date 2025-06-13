# Directory: hserv/controller

## Overview
This directory is responsible for handling incoming client requests, routing them to appropriate services or logic, and managing the overall control flow of the hserv application. It forms a key part of the Model-View-Controller (MVC) pattern, if applicable, or generally acts as the primary interface for request processing.

## Key files
- `FrontController.php`: Manages overall flow and delegates request to the appropriate controller.
- `ReportController.php`: Handles report-related actions such as executing, updating, listing, importing, and exporting report templates.
- `api.php`: Entry point for api requests to retrieve entity data, Heurist records, and IIIF presentations.
- `collectionController.php`: Manages user's collection of record IDs stored in SESSION.
- `databaseController.php`: Controller for manipulations with database(s).
- `entityScrud.php`: Controller to SCRUD (Search, Create, Read, Update, Delete) most database tables/entities, using functions from entityScrudSrv.php.
- `entityScrudSrv.php`: Library of functions that initializes instances of hserv/entity classes and runs requested actions.
- `fileDownload.php`: Handler to download or proxy files registered in the Heurist database (recUploadedFiles).
- `fileGet.php`: Handler to get icons and thumbs for entities, retrieve/check code folder files, and load scratch folder files (e.g., for term imports).
- `fileUpload.php`: File uploader handler.
- `iiif_presentation.php`: Handler to produce IIIF Presentation API v3 JSON output for registered Heurist files, using ExportRecordsIIIF.
- `importController.php`: Controller for CSV and KML parsing and import.
- `index.php`: Main entry point for Heurist controllers; initializes and runs the FrontController.
- `indexController.php`: Controller for requests to the Heurist_Reference_Index database.
- `progress.php`: Handles progress updates and termination for background processes.
- `recordVerify.php`: Handler for the fix record duplication routine.
- `record_batch.php`: Handler for batch updates on Heurist records (add/replace/delete details).
- `record_edit.php`: Handler for CUD (Create, Update, Delete) actions for Heurist records.
- `record_lookup.php`: Handler for third-party web service lookups (e.g., GeoName, TLCMap, BnF), acting as a proxy.
- `record_lookup_config.json`: Configuration for record lookup functionalities.
- `record_map_source.php`: Handler to produce GeoJSON from KML, CSV, DBF resources, or download original files, based on Datasource record ID.
- `record_output.php`: Handler for record searches and exporting data in various formats (JSON, CSV, KML, etc.).
- `record_search.php`: Handler for record searches, primarily outputting JSON for HRecordSet.
- `record_shp.php`: Handler for SHP+DBF files for mapping; converts to GeoJSON or provides downloads.
- `rectype_relations.php`: Determines record type relations for a database, used in network diagrams.
- `rectype_titlemask.php`: Handler for record title mask operations (generation, validation, conversion).
- `repoController.php`: Handler for external repository configuration and file manipulations.
- `saml.php`: Handles SimpleSAMLphp authentication (login/logout).
- `sys_structure.php`: Handler to retrieve database definitions, used for importing definitions from different databases. (DEPRECATED)
- `usr_info.php`: Handler to retrieve system information, user/group details, credentials, and saved searches.
