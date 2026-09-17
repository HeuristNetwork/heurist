<?php

/**
 * heuristConfigIni.php - Docker variant of the parent-level configuration.
 *
 * Mounted read-only at /var/www/html/HEURIST/heuristConfigIni.php by
 * docker-compose.yml (see configIni.php in the codebase for the full
 * documentation of every setting).
 *
 * Strategy: load the shipped template from movetoparent/ for all defaults,
 * then override only the values that must differ inside the container.
 * Keep this file in sync with movetoparent/heuristConfigIni.php upstream.
 */

require dirname(__FILE__) . '/heurist/movetoparent/heuristConfigIni.php';

// --- [DATABASE] -------------------------------------------------------------
// The 'db' service defined in docker-compose.yml (MySQL 8.0).
// Credentials come from the compose environment with dev-safe fallbacks.
$dbHost          = 'db';
$dbPort          = 3306;
$dbAdminUsername = getenv('HEURIST_DB_ADMIN_USERNAME') ?: 'heurist';
$dbAdminPassword = getenv('HEURIST_DB_ADMIN_PASSWORD') ?: 'heurist';

// Set these environment variables when the container is published behind a
// reverse proxy or URL subpath. Leave them unset for automatic detection.
$serverName        = getenv('HEURIST_SERVER_NAME') ?: null;
$heuristBaseURL    = getenv('HEURIST_BASE_URL') ?: null;
$heuristPublicPath = getenv('HEURIST_PUBLIC_PATH') ?: null;

// --- [FOLDERS] --------------------------------------------------------------
// Filestore inside the container (provided by the Compose data mount).
// NOTE: DocumentRoot is /var/www/html/HEURIST/, so the URL path of the
// filestore is /HEURIST_FILESTORE/ (the upstream template default assumes a
// DocumentRoot of /var/www/html/ and would produce a doubled /HEURIST/HEURIST/
// path in generated URLs).
$defaultRootFileUploadPath = '/var/www/html/HEURIST/HEURIST_FILESTORE/';
$defaultRootFileUploadURL  = 'http://localhost:8080/HEURIST_FILESTORE/';

// --- [FILESTORE WEB ACCESS] -------------------------------------------------
// The movetoparent template sets these to true (direct web-server access to
// thumbnails/uploaded files/entity data for speed). In this container Apache
// denies direct access to HEURIST_FILESTORE (see apache-heurist.conf), so all
// of these must be served through PHP (fileGet.php / downloadFile) instead.
$allowWebAccessThumbnails  = false;
$allowWebAccessUploadedFiles = false;
$allowWebAccessEntityFiles = false;

// --- [DATABASE DUMP CONFIGURATION] ------------------------------------------
// Mode 0 = PDO-based (bigdump / mysqldump-php library). This is the safe mode
// when MySQL runs in a separate container. The mysql/mysqldump CLI clients ARE
// installed in the image, so if you prefer the shell modes set both to 2
// (and $dbMySQLpath/$dbMySQLDump are already /usr/bin/... in the template).
$dbScriptMode = 0;
$dbDumpMode   = 0;

// --- [JWT AUTHENTICATION] ---------------------------------------------------
$jwt_Secret = getenv('HEURIST_JWT_SECRET') ?: 'dev-only-secret-change-me';
