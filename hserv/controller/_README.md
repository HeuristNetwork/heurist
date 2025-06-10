Directory:    /hserv/controller

Overview:   All files in this folder are services. They are utilized in hapi.js to obtain various data (in json format) from server side.

Files:
- FrontController.php: Manages front-end requests.
- ReportController.php: Handles report generation and management.
- api.php: General API endpoint.
- collectionController.php: Manages collections of records.
- databaseController.php: Handles database-level operations.
- entityScrud.php: SCRUD operations for entities.
- entityScrudSrv.php: Server-side component for entity SCRUD.
- fileDownload.php: Manages file downloads.
- fileGet.php: Retrieves entity assets (icon, thumbs).
- fileUpload.php: Handles file uploads.
- iiif_presentation.php: IIIF Presentation API endpoint.
- importController.php: Manages data imports.
- index.php: Main entry point for the controller.
- indexController.php: Controller for the reference index database.
- progress.php: Tracks progress of long-running operations.
- recordVerify.php: Verifies records (used in verify record duplications).
- record_batch.php: Batch operations on records.
- record_edit.php: Handles record editing.
- record_lookup.php: Looks up records.
- record_lookup_config.json: Configuration for record lookup.
- record_map_source.php: Converts kml,csv to geojson or downloads file (or zip) based on Datasource record id.
- record_output.php: Manages record search and output.
- record_search.php: Handles record searching.
- record_shp.php: Converts shp+dbf files to geojson output or downloads zip archive based on Datasource record id.
- rectype_relations.php: Manages record type relationships.
- rectype_titlemask.php: Manages record type title masks.
- repoController.php: Controller for repository operations.
- saml.php: Handles SAML authentication.
- sys_structure.php: Retrieves database structure (used in import defintions only to retrieve structure from different database).
- usr_info.php: Controller for system and user information requests.

Updated:     2025-06-10

----------------------------------------------------------------------------------------------------------------