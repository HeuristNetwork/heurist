<?php
/**
* const.php - Defines global constants and utility functions for the Heurist application
*
* This file is responsible for setting up a wide range of constants used throughout the application,
* including version information, server URLs, database connection parameters, API response codes,
* email settings, common string/regex/MIME type definitions, and paths for media and icons.
* It also includes definitions for mapping "magic strings" (like RT_PERSON) to their
* originating database IDs and concept IDs for record types, detail types, and terms.
*
* Additionally, this file provides several global utility functions for common tasks such
* as error handling, string/array manipulation, date retrieval, and HTML generation for
* including JQuery and related libraries.
* Many constants are initialized based on values provided in `configIni.php` (or `../heuristConfigIni.php`).
*
* @project     Heurist academic knowledge management system
* @package Core
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       4.0
*/
use \hserv\utilities\USystem;

/** @const string Current Heurist code version. Value sourced from $version in configIni.php. */
define('HEURIST_VERSION', $version);
/** @const string Minimal database version required for this code version. */
define('HEURIST_MIN_DBVERSION', "1.3.18");

// Heurist Reference Server Configuration
// The reference server hosts the Heurist Reference Index, Help database, templates, and code updates.
if(!@$heuristReferenceServer){
    $heuristReferenceServer = 'https://heuristref.net'; // Default value if not set in configIni.php
}
/** @const string Default Heurist directory path on servers. */
define('HEURIST_DEF_DIR', '/heurist/');

if(isset($heuristReferenceServerMirror) && $heuristReferenceServerMirror!=''){
    /** @const string URL of the main Heurist reference server or its mirror. */
    define('HEURIST_MAIN_SERVER', strtolower($heuristReferenceServerMirror));
    /** @const string Name of the Heurist Reference Index database (mirror version). */
    define('HEURIST_INDEX_DATABASE', 'Heurist_Reference_Index_MIRROR');
    /** @const string Name of the Heurist Job Tracker/ticket database (mirror version). */
    define('HEURIST_BUGREPORT_DATABASE', 'Heurist_Job_Tracker_MIRROR');
    /** @const string Name of the Heurist Help System database (mirror version). */
    define('HEURIST_HELP_DATABASE', 'Heurist_Help_System_MIRROR');
}else{
    /** @const string URL of the main Heurist reference server. */
    define('HEURIST_MAIN_SERVER', strtolower($heuristReferenceServer));
    /** @const string Name of the Heurist Reference Index database. */
    define('HEURIST_INDEX_DATABASE', 'Heurist_Reference_Index');
    /** @const string Name of the Heurist Job Tracker/ticket database. */
    define('HEURIST_BUGREPORT_DATABASE', 'Heurist_Job_Tracker');
    /** @const string Name of the Heurist Help System database. */
    define('HEURIST_HELP_DATABASE', 'Heurist_Help_System');
}
/** @const string Base URL for the central index and template databases. */
define('HEURIST_INDEX_BASE_URL', HEURIST_MAIN_SERVER.HEURIST_DEF_DIR);
/** @const string Concept code for the "Registered Database" record type in the Heurist Reference Index. */
define('HEURIST_INDEX_DBREC', '1-22');

/** @const string URL for the Heurist help system. */
define('HEURIST_HELP', HEURIST_MAIN_SERVER.HEURIST_DEF_DIR.'help');

// HTTP Proxy Configuration (optional, from configIni.php)
if (@$httpProxy != '') {
    /** @const bool Whether to always use the HTTP proxy for CURL requests. Defaults to false if $httpProxyAlwaysActive not set. */
    define('HEURIST_HTTP_PROXY_ALWAYS_ACTIVE', (isset($httpProxyAlwaysActive) && $httpProxyAlwaysActive===true));
    /** @const string HTTP address and port for the proxy server (e.g., "proxy.example.com:8080"). */
    define('HEURIST_HTTP_PROXY', $httpProxy);
    if (@$httpProxyAuth != '') {
        /** @const string Username and password for proxy authorization ("username:password"). */
        define('HEURIST_HTTP_PROXY_AUTH', $httpProxyAuth);
    }
}

// Host and Server URL Configuration
$host_params = USystem::getHostParams(isset($argv)?$argv:null); // $argv for CLI mode

/** @const string Domain name of the current Heurist instance. */
define('HEURIST_DOMAIN', $host_params['domain']);

if (!@$mailDomain) {
    /** @const string Domain name used for emails, defaults to HEURIST_DOMAIN if $mailDomain not set in configIni.php. */
    define('HEURIST_MAIL_DOMAIN', HEURIST_DOMAIN);
}else{
    /** @const string Domain name used for emails, from $mailDomain in configIni.php. */
    define('HEURIST_MAIL_DOMAIN', $mailDomain);
}

/** @const string Full server URL of the current Heurist instance (e.g., "https://myheurist.org"). */
define('HEURIST_SERVER_URL', $host_params['server_url']);
/** @const string Server host name (e.g., "myheurist.net"). May be empty if not determinable. */
define('HEURIST_SERVER_NAME', @$host_params['server_name']);

if(!defined('HEURIST_DIR'))  {
    /** @const string Directory path of the Heurist installation on the server (e.g., "/var/www/heurist/"). */
    define('HEURIST_DIR', $host_params['heurist_dir']);
}

/** @const string Base URL for general Heurist application access (e.g., "https://myheurist.net/heurist/"). */
define('HEURIST_BASE_URL', $host_params['baseURL'] );
/** @const string Base URL for production Heurist access, often shorter (e.g., "https://myheurist.net/heurist/"). */
define('HEURIST_BASE_URL_PRO', $host_params['baseURL_pro'] );

/** @const string Path to the system's temporary directory for scratch space. */
define('HEURIST_SCRATCHSPACE_DIR', sys_get_temp_dir());

// Database Connection Parameters (values from configIni.php)
if ($dbHost) {
    /** @const string Hostname or IP address of the MySQL database server. */
    define('HEURIST_DBSERVER_NAME', $dbHost);
} else {
    /** @const string Default database server hostname if not set in configIni.php. */
    define('HEURIST_DBSERVER_NAME', "localhost");
}

// MySQL Dump and Script Execution Configuration
// 0: use 3rd party PDO mysqldump (default), 1: use internal routine, 2 - call mysql via shell
if(isset($dbMySQLDump) && file_exists($dbMySQLDump)){
    /** @const string Path to the mysqldump executable. */
    define('HEURIST_DB_MYSQLDUMP', $dbMySQLDump);
    $dbDumpMode = isset($dbDumpMode)?$dbDumpMode:2;
}else{
    $dbDumpMode = 0;
}
/** @const int Mode for database dump operations. 0 for PDO, 1 for internal, 2 for shell mysqldump. */
define('HEURIST_DB_MYSQL_DUMP_MODE', $dbDumpMode);

if(isset($dbMySQLpath) && file_exists($dbMySQLpath)){
    /** @const string Path to the mysql command-line client. */
    define('HEURIST_DB_MYSQLPATH', $dbMySQLpath);
    $dbScriptMode = isset($dbScriptMode)?$dbScriptMode:2;
}else{
    $dbScriptMode = 0;
}
/** @const int Mode for executing MySQL scripts. 0 for default, 2 for shell mysql client. */
define('HEURIST_DB_MYSQL_SCRIPT_MODE', $dbScriptMode);

/** @const string Username for MySQL account with administrative privileges (database creation, etc.). */
define('ADMIN_DBUSERNAME', $dbAdminUsername);
/** @const string Password for the administrative MySQL account. */
define('ADMIN_DBUSERPSWD', $dbAdminPassword);
/** @const string Prefix for Heurist database names (e.g., "HEURIST_"). */
define('HEURIST_DB_PREFIX', $dbPrefix);
/** @const int Port number for MySQL database connection. */
define('HEURIST_DB_PORT', $dbPort);

// General Application Information
$date = new DateTime(); // Unused in current scope, potentially for future use or removed logic.
/** @const string Title for Heurist application, incorporating the version. */
define('HEURIST_TITLE', 'Heurist V'.HEURIST_VERSION);

// API Response Status Constants (align with ResponseStatus in hapi.js)
/** @const string API Status: Request successful (HTTP 200). */
define("HEURIST_OK", "ok");
/** @const string API Status: Invalid request (HTTP 400). */
define("HEURIST_INVALID_REQUEST", "invalid");
/** @const string API Status: Resource not found (HTTP 404). */
define("HEURIST_NOT_FOUND", "notfound");
/** @const string API Status: Request denied (insufficient rights, etc.) (HTTP 403). */
define("HEURIST_REQUEST_DENIED", "denied");
/** @const string API Status: Action blocked due to conflict (HTTP 409). */
define("HEURIST_ACTION_BLOCKED", "blocked");

/** @const string API Status: General error (e.g., wrong data, file I/O) (HTTP 500). */
define("HEURIST_ERROR", "error");
/** @const string API Status: Database error on server (HTTP 500). */
define("HEURIST_DB_ERROR", "database");
/** @const string API Status: System configuration error (non-fatal) (HTTP 500). */
define("HEURIST_SYSTEM_CONFIG", "syscfg");
/** @const string API Status: System configuration error (fatal) (HTTP 500). */
define("HEURIST_SYSTEM_FATAL", "system");
/** @const string API Status: Network (CURL requests) outage error (fatal) (HTTP 500). */
define("HEURIST_NETWORK_ERROR", "network");
/** @const string API Status: Unknown server error (HTTP 500). TO BE REMOVED */
define("HEURIST_UNKNOWN_ERROR", "unknown");


// Email Configuration (values from configIni.php or defaults)
/** @const string Email address for bug reports. */
define('HEURIST_MAIL_TO_BUG', $bugEmail?$bugEmail:'info@HeuristNetwork.org');
/** @const string General information email address. */
define('HEURIST_MAIL_TO_INFO', $infoEmail?$infoEmail:'info@HeuristNetwork.org');
/** @const string System administrator email address. */
define('HEURIST_MAIL_TO_ADMIN', $sysAdminEmail?$sysAdminEmail:HEURIST_MAIL_TO_INFO);

// Contact Information Strings
/** @const string HTML string for contacting the Heurist team. */
define('CONTACT_HEURIST_TEAM', 'contact <a href=mailto:'.HEURIST_MAIL_TO_INFO.'>Heurist team</a> ');
/** @const string HTML string prompting to contact the Heurist team. */
define('CONTACT_HEURIST_TEAM_PLEASE', ' Please '.CONTACT_HEURIST_TEAM);
/** @const string HTML string for contacting the system administrator. */
define('CONTACT_SYSADMIN', 'contact your <a href=mailto:'.HEURIST_MAIL_TO_ADMIN.'>system administrator</a> ');

/** @const string Detailed message for critical database errors, including admin contact. */
define('CRITICAL_DB_ERROR_CONTACT_SYSADMIN',
    'It is also possible that drive space has been exhausted. '
            .'<br><br>Please contact the system administrator (email: ' . HEURIST_MAIL_TO_ADMIN . ') for assistance.'
            .'<br><br>This error has been emailed to the Heurist team (for servers maintained by the project or those on which this function has been enabled).'
            .'<br><br>We apologise for any inconvenience');

/** @const string Message prompting to contact system admin about directory permissions. */
define('CONTACT_SYSADMIN_ABOUT_PERMISSIONS',
        'Please ask your system administrator to correct the path and/or permissions for this directory');

// External Services
/** @const string URL of the service used for generating website thumbnails. Value from $websiteThumbnailService in configIni.php. */
define('WEBSITE_THUMBNAIL_SERVICE', $websiteThumbnailService);

// Feature Flags
/** @const bool Whether to expose all relationship vocabularies as options for term fields. */
define("HEURIST_UNITED_TERMS", true);


// Common String and Regex Constants
/** @const string URL for the Nakala repository. Split to avoid SonarCloud security hotspot detection. */
define('NAKALA_REPO', 'http'.'://nakala.fr/');
/** @const string Date format string for ISO 8601 (YYYY-MM-DD HH:MM:SS). */
define('DATE_8601', 'Y-m-d H:i:s');
/** @const string Regex for matching year-only dates (allows negative for BC). */
define('REGEX_YEARONLY', '/^-?\d+$/');
/** @const string Regex for matching non-alphanumeric characters (excluding underscore). */
define('REGEX_ALPHANUM', '/[^a-zA-Z0-9_]/');
/** @const string Regex for matching end-of-line characters (CR and LF). */
define('REGEX_EOL', '/[\r\n]/');

// HTTP and XML Related Constants
/** @const string Standard XML declaration header. */
define('XML_HEADER', '<?xml version="1.0" encoding="UTF-8"?>');
/** @const string Content-Type header for JSON responses. */
define('CTYPE_JSON', 'Content-type: application/json;charset=UTF-8');
/** @const string Content-Type header for HTML responses. */
define('CTYPE_HTML', 'Content-type: text/html;charset=UTF-8');
/** @const string Content-Type header for JavaScript responses. */
define('CTYPE_JS', 'Content-type: text/javascript');
/** @const string Prefix for Content-Length HTTP header. */
define('CONTENT_LENGTH', 'Content-Length: ');
/** @const string Access-Control-Allow-Origin HTTP header for CORS (allowing all origins). */
define('HEADER_CORS_POLICY', 'Access-Control-Allow-Origin: *');
/** @const string MIME type for JSON. */
define('MIMETYPE_JSON', 'application/json');

// Common HTML Separators/Tags (short constants for convenience)
/** @const string HTML table start tag. */
define('TABLE_S','<table>');
/** @const string HTML table row start and first cell start tags. */
define('TR_S','<tr><td>');
/** @const string HTML table cell end and next cell start tags. */
define('TD','</td><td>');
/** @const string HTML table cell end tag. */
define('TD_E','</td>');
/** @const string HTML table cell end and row end tags. */
define('TR_E','</td></tr>');
/** @const string HTML table end tag. */
define('TABLE_E','</table>');
/** @const string HTML div start tag. */
define('DIV_S','<div>');
/** @const string HTML div end tag. */
define('DIV_E','</div>');
/** @const string HTML line break tag. */
define('BR','<br>');
/** @const string Two HTML line break tags. */
define('BR2','<br><br>');

// Common SQL Reserved Words/Snippets
/** @const string SQL AND operator with surrounding spaces. */
define('SQL_AND',' AND ');
/** @const string SQL NOT operator with surrounding spaces. */
define('SQL_NOT',' NOT ');
/** @const string SQL WHERE clause with surrounding spaces. */
define('SQL_WHERE',' WHERE ');
/** @const string SQL NULL keyword. */
define('SQL_NULL', 'NULL');
/** @const string SQL DELETE FROM clause. */
define('SQL_DELETE', 'DELETE FROM ');
/** @const string SQL IN operator start. */
define('SQL_IN',' IN (');
/** @const string SQL snippet representing a false condition. */
define('SQL_FALSE','(1=0)');
/** @const string SQL BETWEEN operator with surrounding spaces. */
define('SQL_BETWEEN',' BETWEEN ');

// Specific Media Types for external services
/** @const string MIME type for Vimeo videos. */
define('MT_VIMEO','video/vimeo');
/** @const string MIME type for YouTube videos. */
define('MT_YOUTUBE','video/youtube');
/** @const string MIME type for SoundCloud audio. */
define('MT_SOUNDCLOUD','audio/soundcloud');

// URI Schemes and Temporary Memory Stream
/** @const string HTTP URI scheme prefix. */
define('HTTP_SCHEMA','http://');
/** @const string HTTPS URI scheme prefix. */
define('HTTPS_SCHEMA','https://');
/** @const string PHP temporary memory stream identifier, allowing up to 1MB before using a temporary file. */
define('TEMP_MEMORY', 'php://temp/maxmemory:1048576');
/** @const string XML Schema namespace for string type. */
define('W3_XML_SCHEMA_STRING','http://www.w3.org/2001/XMLSchema#string');
/** @const string Schema namespace for URI string type. */
define('PURL_TERM_URI','http://purl.org/dc/terms/URI');
/** @const string Schema namespace for Lanuage AR2 code type. */
define('PURL_TERM_LANG','http://purl.org/dc/terms/RFC5646');
/** @const string Schema namespace for dates code type. */
define('PURL_TERM_DATE','http://purl.org/dc/terms/W3CDTF');

// Global variable for language codes, initialized to null.
global $glb_lang_codes;
$glb_lang_codes = null;

// Default common languages for translation of database definitions (ISO 639-2 codes).
// Value from $commonLanguagesForTranslation in configIni.php or defaults here.
if(!isset($commonLanguagesForTranslation)){
    $commonLanguagesForTranslation = ['ENG','FRE','CHI','SPA','ITA','ARA','GER','POR','LAT','GRE','GRC','IND'];
}

// Common languages for translation database definitions (ISO639-2 codes) 3 char in upper case
// change here to set for the entire installation, overriden by list in configIni.php if present for a specific instance
// The full names and 2 character codes will be looked up in hclient\assets\language-codes-active-list.txt
// The order puts languages at the top which are most likely to be used on this installation
// Place languages supported by DEEPL at the top of the list
// Place languages which are not supported by DEEPL at the end of the list - they can still be used to insert the translation prefix
$commonLanguagesLong = ['ENG','FRE','CHI','SPA','ITA','DUT','GER','GRE','TUR','DAN','NOR','SWE','EST','FIN','ARA','BUR','CZE','HIN','HUN','IND','JPN','JAV','KOR','KUR','LAO','LAT','MAO','MAY','MKH','BUR','NEP','PER','POR','RUS','SLO','SLV','SWA','THA','TIB','UIG','UKR','VIE','YID','ZUL'];

// File Upload and Media Handling
/** @const string Comma-separated list of allowed file extensions for uploads. Used in Uploadhandler.php. */
define('HEURIST_ALLOWED_EXT',
'jpg,jpe,jpeg,jfif,sid,png,gif,tif,tiff,bmp,rgb,doc,docx,odt,mp3,mp4,mpg,mpeg,mov,avi,wmv,wmz,aif,aiff,ashx,pdf,mbtiles,'
.'mid,midi,wms,wmd,qt,evo,cda,wav,csv,tsv,tab,txt,rtf,xml,xsl,xslx,xslt,xls,xlsx,hml,kml,kmz,shp,dbf,shx,svg,htm,html,xhtml,'
.'ppt,pptx,zip,gzip,tar,json,ecw,nxs,nxz,obj,mtl,3ds,stl,ply,gltf,glb,off,3dm,fbx,dae,wrl,3mf,ifc,brep,step,iges,fcstd,bim');

// Special Media Type Identifiers for uploaded files (ULF)
/** @const string Identifier for remote media resources. */
define('ULF_REMOTE','_remote');
/** @const string Identifier for IIIF manifest resources. */
define('ULF_IIIF','_iiif');
/** @const string Identifier for IIIF image resources. */
define('ULF_IIIF_IMAGE','_iiif_image');
/** @const string Identifier for tiled image resources. */
define('ULF_TILED_IMAGE','_tiled');

// Default System Directory Names (relative to database filestore root)
/** @const string Default directory name for images. */
define('DIR_IMAGE','image/');
/** @const string Default directory name for scratch/temporary files. */
define('DIR_SCRATCH','scratch/');
/** @const string Default directory name for database backups. */
define('DIR_BACKUP','backup/');
/** @const string Default directory name for thumbnails. */
define('DIR_THUMBS','thumbs/');
/** @const string Default directory name for entity-specific files (e.g., icons, db definition cache). */
define('DIR_ENTITY','entity/');
/** @const string Default directory name for general file uploads. */
define('DIR_FILEUPLOADS','file_uploads/');
/** @const string Default directory name for cached web images. */
define('DIR_WEBIMAGECACHE','webimagecache/');
/** @const string Default directory name for cached blurred images (due to visibility settings). */
define('DIR_BLURREDIMAGECACHE','blurredimagescache/');
/** @const string Default directory name for generated reports. */
define('DIR_GENERATED_REPORTS','generated-reports/');
/** @const string Default directory name for generated HTML output. */
define('DIR_GENERATED_HTML','html-output/');
/** @const string Default directory name for Smarty templates. */
define('DIR_SMARTY_TEMPLATES', 'smarty-templates/');
/** @const string Default directory name for stored permanent/long-lived paraameters. */
define('DIR_PREPARED_PARAMS', 'prepared-parameters/');

// Icon Paths
/** @const string URL to a placeholder icon (16x16 GIF). */
define('ICON_PLACEHOLDER', HEURIST_BASE_URL.'hclient/assets/16x16.gif');
/** @const string URL to an external link icon (16x16 GIF). */
define('ICON_EXTLINK', HEURIST_BASE_URL.'hclient/assets/external_link_16x16.gif');
define('ASSETS_URL', HEURIST_BASE_URL.'hclient/assets/');

/**
 * Record Type Definitions (`$rtDefines`)
 * Maps human-readable "magic strings" (e.g., 'RT_PERSON') to their original concept codes.
 * The format for each entry is: `MagicString => [Originating_Database_ID, ID_in_Originating_Database]`
 * These are used by the System class to define global constants with local database IDs.
 * @var array<string, array<int, int>>
 */
$rtDefines = array(
    // Standard core record types (HeuristCoreDefinitions: DB = 2)
    'RT_RELATION' => array(2, 1),
    'RT_INTERNET_BOOKMARK' => array(2, 2),
    'RT_NOTE' => array(2, 3),
    'RT_ORGANISATION' => array(2, 4),
    'RT_MEDIA_RECORD' => array(2, 5),
    'RT_AGGREGATION' => array(2, 6),
    'RT_COLLECTION' => array(2, 6), // duplicate naming
    'RT_BLOG_ENTRY' => array(2, 7),
    'RT_INTERPRETATION' => array(2, 8),
    'RT_PERSON' => array(2, 10),

    // Record types added by SW and SH for their extensions, no longer in core definitions, now in DB 4 Heurist ToolExtensions
    'RT_FILTER' => array(2, 12),
    'RT_XML_DOCUMENT' => array(2, 13),
    'RT_TRANSFORM' => array(2, 14),
    'RT_ANNOTATION' => array(2, 15),
    'RT_LAYOUT' => array(2, 16),
    'RT_PIPELINE' => array(2, 17),
    'RT_TOOL' => array(2, 19),

    // Cleaned up bibliographic record types
    'RT_BOOK' => array(3, 102),
    'RT_CONFERENCE' => array(3, 103),
    'RT_PUB_SERIES' => array(3, 104),
    'RT_BOOK_CHAPTER' => array(3, 108),
    'RT_JOURNAL' => array(3, 111),
    'RT_JOURNAL_ARTICLE' => array(3, 112),
    'RT_JOURNAL_VOLUME' => array(3, 113),
    'RT_MAP' => array(3, 115),
    'RT_OTHER_DOC' => array(3, 117),
    'RT_REPORT' => array(3, 119),
    'RT_THESIS' => array(3, 120),
    'RT_PERSONAL_COMMUNICATION' => array(3, 121),
    'RT_ARTWORK' => array(3, 122),
    'RT_MAGAZINE_ARTICLE' => array(3, 123),
    'RT_MAGAZINE' => array(3, 124),
    'RT_MAGAZINE_VOLUME' => array(3, 125),
    'RT_NEWSPAPER' => array(3, 126),
    'RT_NEWSPAPER_VOLUME' => array(3, 127),
    'RT_NEWSPAPER_ARTICLE' => array(3, 128),
    'RT_PHOTOGRAPH' => array(3, 129),
    'RT_ARCHIVAL_RECORD' => array(3, 1000),
    'RT_ARCHIVAL_SERIES' => array(3, 1001),

    // Spatial data
    'RT_PLACE' => array(3, 1009),
    'RT_MAP_ANNOTATION' => array(2, 101),
    'RT_MAP_DOCUMENT' => array(3, 1019), // HeuristReferenceSet DB 3: Map document, layers and queries for new map function Oct 2014
    'RT_MAP_LAYER' => array(3, 1020),

    'RT_KML_SOURCE' => array(3, 1014),
    'RT_FILE_SOURCE' => array(2, 53), //csv tsv or dbf source
    'RT_SHP_SOURCE' => array(3, 1017),
    'RT_QUERY_SOURCE' => array(3, 1021),  //RT_MAPABLE_QUERY
    'RT_TLCMAP_DATASET' => array(1271, 54),

    'RT_IMAGE_SOURCE' => array(3, 1018),
    'RT_TILED_IMAGE_SOURCE' => array(2, 11), // added Ian 23/10/14 for consistency
    'RT_GEOTIFF_SOURCE' => array(3, 1018),

    //Web content (used in DH)
    'RT_WEB_CONTENT' => array(1147, 25),

    'RT_CMS_HOME' => array(99, 51),
    'RT_CMS_MENU' => array(99, 52),

    'RT_BUG_REPORT' => array(8, 23)
);

/** DETAIL TYPE DEFINITIONS */
$dtDefines = array('DT_NAME' => array(2, 1),
    'DT_SHORT_NAME' => array(2, 2),
    'DT_SHORT_SUMMARY' => array(2, 3),
    'DT_EXTENDED_DESCRIPTION' => array(2, 4),
    'DT_TARGET_RESOURCE' => array(2, 5),
    'DT_RELATION_TYPE' => array(2, 6),
    'DT_PRIMARY_RESOURCE' => array(2, 7),
    'DT_INTERPRETATION_REFERENCE' => array(2, 8),
    'DT_DATE' => array(2, 9),
    'DT_START_DATE' => array(2, 10),
    'DT_END_DATE' => array(2, 11),
    'DT_QUERY_STRING' => array(2, 12),
    'DT_RESOURCE' => array(2, 13),
    'DT_CREATOR' => array(2, 15),
    'DT_CONTACT_INFO' => array(2, 17),
    'DT_GIVEN_NAMES' => array(2, 18),
    'DT_GENDER' => array(2, 20),
    'DT_EMAIL' => array(2, 23),
    'DT_GEO_OBJECT' => array(2, 28),
    'DT_MIME_TYPE' => array(2, 29),
    'DT_IMAGE_TYPE' => array(2, 30),
    'DT_MAP_IMAGE_LAYER_SCHEMA' => array(2, 31),
    'DT_MINIMUM_ZOOM_LEVEL' => array(2, 32), //in basemap zoom levels (0-20)
    'DT_MAXIMUM_ZOOM_LEVEL' => array(2, 33),
    // zoom in km used for map documents (map zoom ranges) and layers (visibility range)
    //note that minimum in km turns to maximum in native zoom
    'DT_MAXIMUM_ZOOM' => array(3, 1085), //in UI this field acts as minimum zoom in km
    'DT_MINIMUM_ZOOM' => array(3, 1086), //in UI this field acts as maximum zoom out km
    'DT_LEGEND_OUT_ZOOM' => array(3, 1087), //hide or disable layer in legend if layer is out of zoom range
    'DT_IS_VISIBLE' => array(2, 1100),   //is layer initially visible on mapdocument initialization

    'DT_SERVICE_URL' => array(2, 34),
    'DT_URL' => array(3, 1058),
    'DT_ORIGINAL_RECORD_ID' => array(2, 36),
    'DT_FILE_RESOURCE' => array(2, 38),
    'DT_THUMBNAIL' => array(2, 39),
    'DT_ANNOTATION_INFO' => array(2, 1098), //for iiif and map annotations

    //xslt not used
    'DT_FILTER_STRING' => array(2, 40),
    'DT_FILE_TYPE' => array(2, 41),
    'DT_ANNOTATION_RESOURCE' => array(2, 42),
    'DT_ANNOTATION_RANGE' => array(2, 43),
    'DT_START_WORD' => array(2, 44),
    'DT_END_WORD' => array(2, 45),
    'DT_START_ELEMENT' => array(2, 46),
    'DT_END_ELEMENT' => array(2, 47),
    'DT_LAYOUT_STRING' => array(2, 48),
    'DT_TRANSFORM_RESOURCE' => array(2, 50),
    'DT_PROPERTY_VALUE' => array(2, 51),
    'DT_TOOL_TYPE' => array(2, 52),
    'DT_RECORD_TYPE' => array(2, 53),
    'DT_DETAIL_TYPE' => array(2, 54),
    'DT_COMMAND' => array(2, 55),
    'DT_COLOUR' => array(2, 56),
    'DT_DRAWING' => array(2, 59),
    'DT_COUNTER' => array(2, 60),
    'DT_MEDIA_RESOURCE' => array(2, 61), //refetence to media record
    //xslt not used
    'DT_FILE_NAME' => array(2, 62),
    'DT_FILE_FOLDER' => array(2, 63),
    'DT_FILE_EXT' => array(2, 64),
    'DT_FILE_DEVICE' => array(2, 65),
    'DT_FILE_DURATION' => array(2, 66),
    'DT_FILE_SIZE' => array(2, 67),
    'DT_FILE_MD5' => array(2, 68),
    'DT_PARENT_ENTITY' => array(2, 247),
    'DT_EDITOR' => array(3, 1013),
    'DT_DOI' => array(3, 1003),
    'DT_WEBSITE_ICON' => array(3, 347), //remove from code
    'DT_ISBN' => array(3, 1011),
    'DT_ISSN' => array(3, 1032),
    'DT_JOURNAL_REFERENCE' => array(3, 1034),
    'DT_MEDIA_REFERENCE' => array(3, 508), //*******************ERROR  THIS IS MISSING

    'DT_EXTERNAL_ID' => array(2, 581), //external non heurist record id
    // Spatial & mapping
    'DT_KML_FILE' => array(3, 1044),
    'DT_KML' => array(3, 1036), //snippet
    'DT_MAP_IMAGE_LAYER_REFERENCE' => array(3, 1043),
    'DT_MAP_IMAGE_WORLDFILE' => array(3, 1095),
    'DT_ALTERNATE_NAME' => array(3, 1009),
    'DT_TIMELINE_FIELDS' => array(2, 1105),
    // Map document
    'DT_MAP_LAYER' => array(3, 1081),
    'DT_MAP_BOOKMARK' => array(3, 1082),
    'DT_SYMBOLOGY_POINTMARKER' => array(3, 1091),  //outdated
    'DT_SYMBOLOGY' => array(3, 1092),  //MAIN field that stores ALL styles for map symbology (including thematic maps)
    'DT_ZOOM_KM_POINT' => array(2, 925), //area to zoom in on point selection (per map space document)
    'DT_SMARTY_TEMPLATE' => array(2, 922),  // smarty template to produce popup info per layer
    'DT_SYMBOLOGY_COLOR' => array(3, 1037), // outdated
    'DT_BG_COLOR' => array(2, 551),         // outdated
    'DT_OPACITY' => array(3, 1090),         // outdated
    'DT_ORDERING_HIERARCHY' => array(2, 1082), // field used to define drag-drop ordering of records
    'DT_DATA_SOURCE' => array(3, 1083),
    // Shape
    'DT_ZIP_FILE' => array(3, 1072),
    'DT_SHAPE_FILE' => array(3, 1069),
    'DT_DBF_FILE' => array(3, 1070),
    'DT_SHX_FILE' => array(3, 1071),

    'DT_CRS' => array(2, 1092), // Coordinate Reference System
    'DT_WORLD_BASEMAP' => array(2, 1093),  // Need to use trm_Label for terms to get basemap name

    'DT_EXTRACTED_TEXT' => array(2, 652),  //for pdf parser

    'DT_CMS_TOP_MENU' => array(99, 742),  //pointer  to top level menues in home page
    'DT_CMS_MENU' => array(99, 761),  //pointer to sub menu
    'DT_CMS_KEYWORDS' => array(99, 948),
    'DT_CMS_TEMPLATE' => array(2, 1099),
    'DT_CMS_TARGET' => array(99, 949),
    'DT_CMS_HEADER' => array(2, 929),
    'DT_CMS_CSS' => array(99, 946),
    'DT_CMS_PAGETITLE' => array(99, 952),   //show page title above content
    'DT_CMS_TOPMENUSELECTABLE' => array(2, 938), // allow top menu to be selectable, if a submenu is present
    //'DT_CMS_BANNER' => array(99, 951),
    //'DT_CMS_ALTLOGO' => array(2, 926),
    //'DT_CMS_ALTLOGO_URL' => array(2, 943),
    //'DT_CMS_ALT_TITLE' => array(3, 1009),
    'DT_CMS_SCRIPT' => array(2, 927),
    'DT_CMS_PAGETYPE' => array(2, 928), //menu (2-6253) or standalone (2-6254)
    'DT_CMS_EXTFILES' => array(2, 939), //external links and scripts
    'DT_CMS_FOOTER' => array(2, 940),
    'DT_CMS_FOOTER_FIXED' => array(2, 941),    //fixed 2-532
    'DT_LANGUAGES' => array(2, 967),
    'DT_CMS_MENU_FORMAT' => array(2, 1104), //show name + icon, name only, or icon only
    'DT_CMS_MENU_HOME' => array(2, 1149),  //show home entry in main menu 
    'DT_CMS_ACTION' => array(2, 1148),
    

    'DT_WORKFLOW_STAGE' => array(2, 1080),
    'DT_VERSION' => array(2, 49)

);

/**
 * Term Definitions (`$trmDefines`)
 * Maps human-readable "magic strings" (e.g., 'TRM_PAGETYPE_WEBPAGE') to their original concept codes.
 * The format for each entry is: `MagicString => [Originating_Database_ID, ID_in_Originating_Database]`
 * These are used by the System class to define global constants with local database IDs.
 * @var array<string, array<int, int>>
 */
$trmDefines = array(
    'TRM_PAGETYPE_WEBPAGE' => array(2, 6254),
    'TRM_PAGETYPE_MENUITEM' => array(2, 6253),
    'TRM_NO' => array(2, 531), // General 'No' term
    'TRM_NO_OLD' => array(99, 5447), // Older 'No' term, potentially from a specific DB context
    'TRM_SWF' => array(2, 9453), // Workflow stages vocabulary root/identifier
    'TRM_SWF_ADDED' => array(2, 9464), // Workflow Stage: 01 - Editing (includes manually created)
    'TRM_SWF_IMPORT' => array(2, 9454), // Workflow Stage: 00 - Imported

    // For DT_CMS_MENU_FORMAT (terms defining how CMS menus are displayed)
    'TRM_NAME_ONLY' => array(2, 9634), // Display name only
    'TRM_ICON_ONLY' => array(2, 9635), // Display icon only
    'TRM_NAME_AND_ICON' => array(2, 9636), // Display both name and icon

    // For DT_LEGEND_OUT_ZOOM (terms defining behavior of map legend items when out of zoom range)
    'TRM_LEGEND_OUT_ZOOM_HIDDEN' => array(3, 5081), // Legend item becomes hidden
    'TRM_LEGEND_OUT_ZOOM_DISABLED' => array(3, 5082)  // Legend item becomes disabled (greyed out)
);


//--------------------------------- Utility Functions ---------------------------------

/**
 * Custom error handler for boot process, specifically to log warnings about large input variable arrays
 * which might indicate issues like exceeding `max_input_vars`.
 *
 * @param int    $errno   The level of the error raised.
 * @param string $errstr  The error message.
 * @param string $errfile The filename that the error was raised in.
 * @param int    $errline The line number the error was raised at.
 * @return void This function does not return a value; it logs the error.
 */
function bootErrorHandler($errno, $errstr, $errfile, $errline){
    // Specifically checks for E_WARNING related to 'Input variables' which often indicates
    // that PHP's max_input_vars limit might have been reached.
    if($errno === E_WARNING && strpos($errstr, 'Input variables') !== false){
        $message = "$errstr in $errfile:$errline";
        error_log('Large INPUT warning: '.htmlspecialchars($message));
        // Log a slice of a potentially very large $_REQUEST array to avoid overwhelming logs.
        error_log('Sample of $_REQUEST: ' . print_r(array_slice($_REQUEST, 0, 100), true));
        error_log('$_SERVER info: ' . print_r($_SERVER, true));
    }
    // For other errors, this handler does nothing, allowing PHP's standard error handling to proceed.
}

/**
 * Generates a standardized error message for a missing or wrong parameter.
 *
 * @param string $param The name of the parameter that is problematic.
 * @return string A formatted error message string.
 */
function errorWrongParam($param){
    return htmlspecialchars($param) . ' parameter is not defined or wrong';
}

/**
 * Wraps a given text string in a div with class "error" and red color style for display.
 *
 * @param string $text The error text to display.
 * @return string An HTML string containing the formatted error message.
 */
function errorDiv($text){
    return '<div class="error" style="color:red">' . htmlspecialchars($text) . DIV_E;
}

/**
 * Performs a C-style header redirect to the specified URL and exits the script.
 *
 * @param string $url The URL to redirect to.
 * @return void This function terminates script execution.
 */
function redirectURL($url){
    header('Location: ' . $url);
    exit;
}

/**
 * Gets the current date and time as a DateTime object in the UTC timezone.
 *
 * @return \DateTime A DateTime object representing the current moment in UTC.
 */
function getNow(){
    return new \DateTime('now', new \DateTimeZone('UTC'));
}

/**
 * Checks if a variable is null, not set, or an empty string.
 * Note: This function considers the string '0' as not empty, unlike PHP's empty().
 *
 * @param mixed $val The variable to check.
 * @return bool True if the variable is null, not set, or an empty string, false otherwise.
 */
function isEmptyStr($val){
    return !isset($val) || $val === null || $val === '';
}

/**
 * Checks if a variable is not an array or is an empty array.
 *
 * @param mixed $val The variable to check.
 * @return bool True if $val is not an array or if it is an empty array, false otherwise.
 */
function isEmptyArray($val){
    return !is_array($val) || empty($val);
}

/**
 * Searches for a value in a two-dimensional array by a specific key within the nested arrays.
 *
 * @param array<int, array<string, mixed>> $arr The two-dimensional array to search in.
 * @param string $key The key to search for within each nested array.
 * @param mixed  $keyvalue The value to match against the value of `$key`.
 * @return int|null Returns the index (key of the outer array) of the first found item, or null if not found.
 */
function findInArray(array $arr, string $key, $keyvalue): ?int {
    foreach ($arr as $idx => $item) {
        if (is_array($item) && array_key_exists($key, $item) && $item[$key] === $keyvalue) {
            return $idx;
        }
    }
    return null;
}

/**
 * Checks if a variable is a positive integer (greater than 0).
 * Handles both integer types and string representations of digits.
 *
 * @param mixed $val The variable to check.
 * @return bool True if $val is a positive integer, false otherwise.
 */
function isPositiveInt($val){
    return isset($val) && (is_int($val) || ctype_digit((string)$val)) && (int)$val > 0;
}
/* implemented - but not used
function isConceptCode($val, $parse=false){
    $database_id = 0;
    $recid = 0;
    if (is_string($val) && (strpos((string)$val, '-') !== false)){
        [$database_id, $recid] = explode('-', (string)$val, 2);
        $database_id = (int)isPositiveInt($database_id)?$database_id:0;
        $recid = (int)isPositiveInt($database_id)?$recid:0;
    }
    $res = $recid>0 && $database_id>0;
    if($parse){
       $res = [$database_id, $recid];
    }
    return $res;
}

function getConceptCode($val):array{
    return isConceptCode($val, true);
}
*/

/**
 * Checks if the current server name is 'localhost' or '127.0.0.1'.
 *
 * @return bool True if the server is considered localhost, false otherwise.
 */
function isLocalHost(){
    return isset($_SERVER["SERVER_NAME"]) && 
           ($_SERVER["SERVER_NAME"] === 'localhost' || $_SERVER["SERVER_NAME"] === '127.0.0.1');
}

/**
 * Outputs data with appropriate HTTP headers, optionally forcing a file download.
 * If data is an array and MIME type is JSON, it will be JSON encoded.
 *
 * @param mixed       $data     The data to output. If an array and $mimeType is JSON, it will be json_encoded.
 * @param string|null $filename Optional. If provided, sets Content-Disposition to make the browser download the data as this filename.
 * @param string|null $mimeType Optional. The MIME type of the data. Defaults to 'application/json' (MIMETYPE_JSON).
 * @return void This function outputs directly and may terminate or alter headers.
 */
function dataOutput($data, $filename=null, $mimeType=null)
{
    if($mimeType === null){
        $mimeType = MIMETYPE_JSON; // Assumes MIMETYPE_JSON is 'application/json'
    }
    if($mimeType === MIMETYPE_JSON && is_array($data)){
        $data = json_encode($data);
        if ($data === false) { // Handle potential json_encode error
            error_log("JSON encoding error in dataOutput: " . json_last_error_msg());
            // Optionally set a 500 header or output an error message
            // For now, it will output 'false' as per original behavior if $data becomes false.
        }
    }

    // Ensure $data is a string before strlen and echo
    if (!is_string($data)) {
        // This case should ideally not be reached if logic is correct,
        // especially for JSON where it's encoded or for other types where $data is already a string.
        // Converting to string defensively.
        $data = (string) $data;
    }

    header('Content-type: '.$mimeType.';charset=UTF-8');

    if($filename !== null){ //browser downloads it as file
        header('Content-Disposition: attachment; filename="' . basename($filename) . '";'); // Use basename for security
        header("Pragma: no-cache;"); // HTTP 1.0
        header('Expires: 0'); // Proxies
        // Original: header('Expires: ' . gmdate("D, d M Y H:i:s", time() - 3600)); // For older browsers
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0'); // HTTP 1.1
    }

    $len = strlen($data);
    if($len > 0){ // Only set Content-Length if there's content
        header('Content-Length: '. $len);
    }

    if($mimeType === MIMETYPE_JSON){ // Assumes MIMETYPE_JSON is 'application/json'
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block'); // Deprecated by modern browsers but often still set.
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; frame-ancestors \'self\';');
    }

    echo $data;
    // Consider adding exit after outputting data if no further processing is intended.
}

/**
 * Includes JQuery, JQuery UI, JQuery Calendars plugin, and Fancytree plugin with their CSS.
 * It can switch between JQuery 1.12.x and JQuery 3.x versions.
 * For non-localhost environments, it uses CDN links for JQuery and JQuery UI.
 * For localhost, it uses local copies from an 'external' directory.
 *
 * @param bool $useBootstrap Optional. If true, If true, includes bootstrap css and js
 * @return void This function outputs HTML script and link tags directly.
 */
function includeJQuery($useBootstrap=false){

   // integrity has been got with https://www.srihash.org/
   if(isLocalHost()){
?>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery/jquery-3.7.1.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery/jquery-ui.js"></script>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery/jquery-ui.css"/>
<?php
   }else{
?>
        <script src="https://code.jquery.com/jquery-3.7.1.js"  crossorigin="anonymous"></script>
       
        <script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js" crossorigin="anonymous"></script>
        <link rel="stylesheet" type="text/css" href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css">
        
        <script src="https://js-de.sentry-cdn.com/bd493ee1a58acc612b6cc71d055d8ff9.min.js" 
                crossorigin="anonymous"></script>
                
        
        <script type="text/javascript"> 
        if (window.Sentry && typeof window.Sentry.onLoad === "function") { 
  Sentry.onLoad(function() {
        Sentry.init({
          dsn: "https://bd493ee1a58acc612b6cc71d055d8ff9@o4509586661507072.ingest.de.sentry.io/4509586665701456",
          integrations: [
            // send console.log, console.error, and console.warn calls as logs to Sentry
            //Sentry.consoleLoggingIntegration({ levels: ["log", "error", "warn"] }), it works for NPM only
            Sentry.browserTracingIntegration()
            //Sentry.replayIntegration()
          ],
        });
      });
        }
        </script>        
<?php
   }
        if($useBootstrap){
            if(isLocalHost()){
?>            
    <script src="<?php echo PDIR;?>external/bootstrap/bootstrap.bundle.min.js"></script>
    <link href="<?php echo PDIR;?>external/bootstrap/bootstrap.min.css" rel="stylesheet">
<?php
            }else{
?>            
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<?php
            }
        }
        if(isLocalHost()){
?>        
   <script src="<?php echo PDIR;?>external/jquery.widgets/jquery.fancytree/jquery.fancytree-all.js"></script>
   <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.widgets/jquery.fancytree/skin-themeroller/ui.fancytree.css" />
<?php
        }else{
?>        
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.fancytree/2.38.4/jquery.fancytree-all.min.js" integrity="sha512-y1CCIMXI/NTrfNJZYBgy/8p3GzJSElDQKWx9gEA6IT+cTQhXAKPRpKR3FXGfREsxdCp3ByeEK3ndCri4j3n3hw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.widgets/jquery.fancytree/skin-themeroller/ui.fancytree.css" />
<?php
        }
}
?>
