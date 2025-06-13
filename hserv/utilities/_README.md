# Directory: hserv/utilities

## Overview
This directory contains a collection of utility classes, helper functions, and scripts that provide common, reusable functionalities across various parts of the hserv application. These utilities might cover tasks like data sanitization, file handling, image manipulation, email sending, database operations, and integration with external services.

## Key items
- `.htaccess_via_url`: Access control configuration file (to be copied to subfolders of database store folder).
- `DbExecuteScript.php`: Executes SQL script. (Heavily modified from bigdump.php)
- `DbExportTSV.php`: Exports entire database to TSV.
- `DbRegis.php`: Static class to perform database registration operations in the Heurist reference index database.
- `DbUtils.php`: Static class to perform various database lifecycle and utility operations.
- `DbVerify.php`: Provides methods to validate and fix database structure and data integrity issues.
- `DbVerifyURLs.php`: A class to check and validate URLs from various sources within the Heurist database.
- `Temporal.php`: Represents and manipulates temporal (date/time) data within Heurist.
- `UArchive.php`: Utility class for creating and extracting ZIP and BZ2 archives.
- `UFile.php`: This file provides a collection of global functions for tasks such as file and folder manipulation.
- `UImage.php`: Image manipulation utilities.
- `ULocale.php`: Localization utility functions for Heurist.
- `UMail.php`: This file provides a collection of global functions for sending emails.
- `USaml.php`: This file provides functions to integrate SimpleSAMLphp for Single Sign-On (SSO) capabilities.
- `USanitize.php`: Utility class for input sanitization and HTML purification within Heurist.
- `USystem.php`: Utility class for retrieving system, PHP configuration, and user environment details.
- `UploadHandler.php`: Provides the UploadHandler class for managing server-side file uploads.
- `UploadHandlerInit.php`: This script serves as the primary server-side entry point for the jQuery file upload widget.
- `captcha.php`: Stores Capture value in session "captcha_code".
- `testSimilarURLs.php`: Utility functions for finding and comparing similar URLs within the Heurist database.

- `geo/`: Contains utilities related to geographical data or mapping.
