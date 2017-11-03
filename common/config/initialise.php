<?php

/**
* initialise.php: sets up all the initialisaiton of Heurist
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.2
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
*
* Function list:
* - getRTDefineKeys()
* - getDTDefineKeys()
* - defineRTLocalMagic()
* - defineDTLocalMagic()
* - rectypeLocalIDLookup()
* - detailtypeLocalIDLookup()
* - testDirWriteableAndDefine()
* - returnErrorMsgPage()
*
* No Classes in this File
*
*/

/*
the standard initialisation file configIni.php is in the root directory of the Heurist
distribution and contains (mostly) blank definitions. This file should be edited to set the configuration
of your installation. Heurist will also look for the file one level up and override the values in
configIni.php (this allows the initialisation file to be included in the source code, while
a developer can specify passwords etc. without including them in the source code they distribute).

configIni.php sets the following:
$serverName - override default taken from request header SERVER_NAME for aliasing or forwarding
$dbHost - - host for the MySQL server
$dbAdminUsername, $dbAdminPassword - read/write user and password
$dbReadonlyUsername, $dbReadonlyPassword - read-only user name and password
$dbPrefix - prepended to all database names
$memcachedHost - host for the memcached server
$memcachedPort - port for the memcached server
$defaultRootFileUploadPath - root location for uploaded files/record type icons/templates etc.
$defaultFaimsModulesPath
$siteRelativeIconUploadBasePath - Document root relative pathname of a directory where Heurist can store uploaded icons
$sysAdminEmail - email address of system administrator
$infoEmail - redirect info@ emails to this address
*/

//set up system path defines

/*
*NOTE: Strict adherence is required, DO NOT CHANGE NAMING or things WILL BREAK
* filesystems treat files and directories in the same name space and list.
* PHP file apis treat a directory with or without the trailing / the same
* to avoid problems we will always add the trailing / to the end of all defined dir or path
* All root are root directories and will also have a trailing /
* NOTE: HEURIST_DOCUMENT_ROOT to be migrated.
* All URL are to have full protocol
*/

/*
HEURIST_DOMAIN       - host (domain name) WITHOUT port
HEURIST_SERVER_NAME  - host (domain name) with port  eg. heurist.sydney.edu.au:80
HEURIST_SERVER_URL   - with protocol  http://heurist.sydney.edu.au
HEURIST_CURRENT_URL  - current url

// @todo - remove:   HEURIST_SERVER_ROOT_DIR - server root eg. /var/www/html/

HEURIST_DIR          - full path to heurist    /var/www/html/h4/

HEURIST_BASE_URL     - full url     http://Heurist???.org:80/h4/  @todo - rename to HEURIST_URL
HEURIST_SITE_PATH    - /h4/       (used only in this file to make other constants)

HEURIST_UPLOAD_ROOT      - path to root filestore folder
HEURIST_UPLOAD_ROOT_URL  - url to root filestore folder    (used in this file only)
HEURIST_FILESTORE_DIR    - path to DB filestore folder
HEURIST_FILESTORE_URL    - url to DB filestore folder   (used in this file only)

HEURIST_ICON_DIR   - path
HEURIST_ICON_URL   - url
HEURIST_FILES_DIR  - path to user uploaded files
HEURIST_FILES_URL  - url to user uploaded files
HEURIST_THUMB_DIR
HEURIST_THUMB_URL
HEURIST_SMARTY_TEMPLATES_DIR
HEURIST_XSL_TEMPLATES_DIR
HEURIST_HML_DIR
HEURIST_HML_URL

HEURIST_HTML_DIR
HEURIST_HTML_URL

HEURIST_SCRATCHSPACE_DIR

HEURIST_FAIMS_DIR      - by default   HEURIST_FILESTORE_DIR/faims otherwise redefined in ini fuile

HEURIST_INDEX_BASE_URL - url for master index database, heurist.sydney.edu.au/h4
*/

require_once (dirname(__FILE__) . '/../../configIni.php'); // read in the configuration file
require_once (dirname(__FILE__) . "/../php/dbMySqlWrappers.php");

$talkToSysAdmin="Please advise your system administrator or email info - a t - HeuristNetwork.org for assistance.";

// TODO: Rationalise the duplication of constants across /php/consts.php and /common/connect/initialise.php
//       in particular this duplication of HEURIST_MIN_DB_VERSION and any other explicit constants

define('HEURIST_VERSION', $version);
define('HEURIST_MIN_DBVERSION', "1.2.0");

if (!$serverName) {
    $serverName = $_SERVER["SERVER_NAME"] . ((is_numeric(@$_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80") ? ":" . $_SERVER["SERVER_PORT"] : "");
    define('HEURIST_DOMAIN', $_SERVER["SERVER_NAME"]);
}else{
    $k = strpos($serverName,":");
    define('HEURIST_DOMAIN', ($k>0)?substr($serverName,0,$k-1):$serverName );
}

$isSecure = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    $isSecure = true;
}
elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ||
!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
    $isSecure = true;
}
$REQUEST_PROTOCOL = $isSecure ? 'https' : 'http';


define('HEURIST_SERVER_NAME', $serverName); // server host name for the configured name, eg. Heurist.sydney.edu.au
$serverBaseURL = $REQUEST_PROTOCOL . "://" . HEURIST_SERVER_NAME;
define('HEURIST_SERVER_URL', $serverBaseURL); //with protocol and port
define('HEURIST_CURRENT_URL', $serverBaseURL . $_SERVER["REQUEST_URI"]);

$installDir = getInstallationDirectory( @$_SERVER["SCRIPT_NAME"] );



// This is only used in this file to set other values
define('HEURIST_SITE_PATH', $installDir); // eg. /h4/

define('HEURIST_BASE_URL', HEURIST_SERVER_URL . HEURIST_SITE_PATH); // eg. http://heurist.sydney.edu.au/h4/

$documentRoot = @$_SERVER["DOCUMENT_ROOT"];
if( $documentRoot && substr($documentRoot, -1, 1) != '/' ) $documentRoot = $documentRoot.'/';
//define('HEURIST_SERVER_ROOT_DIR', $documentRoot); //  eg. /var/www/html/      @todo - remove
define('HEURIST_DIR', $documentRoot . HEURIST_SITE_PATH ); //  /var/www/html/h4/

// Heurist Installation which contains index of registered Heurist databases (registered DB # 1)
// DO NOT CHANGE THIS URL
define('HEURIST_INDEX_BASE_URL', "http://heurist.sydney.edu.au/heurist/");
// 21aug17: This name ommitted the underscores, so didn't reference an existing database, so prob. not used
define('HEURIST_INDEX_DBNAME', "Heurist_Master_Index");

//-------------------------------------------------------------------------- MEMCACHE AND PROXY

// set up memcached server(s) connection defines
define('MEMCACHED_HOST', isset($memcachedHost) && $memcachedHost ? $memcachedHost : "localhost");
define('MEMCACHED_PORT', isset($memcachedPort) && $memcachedPort ? $memcachedPort : "11211");
// this was a global already anyway (in getSearchResults.php)
/* NO MEMCACHE ANYMORE   $memcache = new Memcache;
// with addServer, connection is not established until actually used
// the get/set functions return FALSE on fail so we get graceful degradation for free (if no memcached server is available)
$memcache->addServer(MEMCACHED_HOST, MEMCACHED_PORT);
*/
if (@$httpProxy != '') {
    define('HEURIST_HTTP_PROXY', $httpProxy); //http address:port for proxy request
    if (@$httpProxyAuth != '') {
        define('HEURIST_HTTP_PROXY_AUTH', $httpProxyAuth); // "username:password" for proxy authorization
    }
}

//-------------------------------------------------------------------------- DATABASE

//set up database server connection defines
if ($dbHost) {
    define('HEURIST_DBSERVER_NAME', $dbHost);
} else {
    define('HEURIST_DBSERVER_NAME', "localhost"); //configure to access mysql on the same machine as the Heurist codebase

}

if (!($dbAdminUsername && $dbAdminPassword && $dbReadonlyUsername && $dbReadonlyPassword)) { //if these are not specified then we can't do anything
    returnErrorMsgPage(1, "MySql user account/password not specified. Please ask your system administrator to set in heuristConfigIni.php");
}
if(preg_match('/[^a-z_\-0-9]/i', $dbAdminPassword)){
    returnErrorMsgPage(1, "Database password missing or contains special characters. Please ask your system administrator to edit the MySQL passwords ".
        "and use a (long) alphanumeric password in the heuristConfigIni.php file.");
}

// refactor - use:  function isInValid($str) {return preg_match('[\W]', $str);}  defined in  admin/setup/dbcreate/createNewDB.php

define('ADMIN_DBUSERNAME', $dbAdminUsername); //user with all rights so we can create databases, etc.
define('ADMIN_DBUSERPSWD', $dbAdminPassword);
define('READONLY_DBUSERNAME', $dbReadonlyUsername); //readonly user for access to user and heurist databases
define('READONLY_DBUSERPSWD', $dbReadonlyPassword);
define('HEURIST_DB_PREFIX', (@$_REQUEST['prefix'] ? $_REQUEST['prefix'] : $dbPrefix));
//database name prefix which is added to db=name to compose the mysql dbname used in queries, normally hdb_


//test db connections working
$db = mysql_connect(HEURIST_DBSERVER_NAME, $dbAdminUsername, $dbAdminPassword)
or returnErrorMsgPage(1, "Unable to connect to MySQL database server (using read-write account). Please ask system administrator to check that the database server / MySQL are running.".
    "<br>&nbsp;<br>On a new system, check that web server has access to the db server (for tiered systems), MySQL access rights are correctly set, and set passwords in heuristConfigIni.php.".
    "<br>&nbsp;<br>MySQL error: " .mysql_error());
// this will only occur in isolation if the readonly password is mis-set, and generally only on new setups, so no need to give full explanation
$db = mysql_connect(HEURIST_DBSERVER_NAME, $dbReadonlyUsername, $dbReadonlyPassword)
or returnErrorMsgPage(1, "Unable to connect to MySQL database server (using read-only account). Check MySQL read-only user and password in heuristConfigIni.php, and MySQL access rights for this user.<br>");


//-------------------------------------------------------------------------- PATHS AND URLS ---------

// upload path eg. /var/www/html/HEURIST/HEURIST_FILESTORE/
if (isset($defaultRootFileUploadPath) && $defaultRootFileUploadPath && $defaultRootFileUploadPath!="") {

    if ($defaultRootFileUploadPath != "/" && !preg_match("/[^\/]\/$/", $defaultRootFileUploadPath)) { //check for trailing /
        $defaultRootFileUploadPath.= "/"; // append trailing /

    }
    if ( !strpos($defaultRootFileUploadPath,":/") && $defaultRootFileUploadPath != "/" && !preg_match("/^\/[^\/]/", $defaultRootFileUploadPath)) {
        //check for leading /
        $defaultRootFileUploadPath = "/" . $defaultRootFileUploadPath; // prepend leading /

    }
    testDirWriteableAndDefine('HEURIST_UPLOAD_ROOT', $defaultRootFileUploadPath, "File store root folder", false);

    if (!defined('HEURIST_UPLOAD_ROOT')){ //fatal error - storage folder is not defined
        returnErrorMsgPage(1, "Cannot access root filestore directory <b>". $defaultRootFileUploadPath .
            "</b><p>Either the directory does not exist (check setting in heuristConfigIni.php file), or it is not writeable by PHP (check permissions).<br>".
            "On a multi-tier service, the file server may not have restarted correctly or may not have been mounted on the web server.</p>");
    }

    if(!isset($defaultRootFileUploadURL) || $defaultRootFileUploadURL==null || $defaultRootFileUploadURL==""){
        returnErrorMsgPage(1, "You have to define Root filestore URL (absolute or relative to server URL)."
            ."<p>Define variable <b>defaultRootFileUploadURL</b> in your heuristConfigIni</p>");
    }

    if(strpos($defaultRootFileUploadURL, $REQUEST_PROTOCOL . "://")===false && strpos($defaultRootFileUploadURL, HEURIST_SERVER_URL)===false){
        if( substr($defaultRootFileUploadURL, 0, 1) != '/' ) $defaultRootFileUploadURL = "/" . $defaultRootFileUploadURL;
        $defaultRootFileUploadURL =  HEURIST_SERVER_URL . $defaultRootFileUploadURL;
    }

    define('HEURIST_UPLOAD_ROOT_URL', $defaultRootFileUploadURL);
}else{
    returnErrorMsgPage(1, "Root filestore directory is not defined. You have to define variable 'defaultRootFileUploadPath' in your heuristConfigIni.php.");
}


// set up email defines
define('HEURIST_MAIL_TO_BUG', $bugEmail?$bugEmail:'info@HeuristNetwork.org');
define('HEURIST_MAIL_TO_INFO', $infoEmail?$infoEmail:'info@HeuristNetwork.org');
define('HEURIST_MAIL_TO_ADMIN', $sysAdminEmail?$sysAdminEmail:HEURIST_MAIL_TO_INFO);


//-------------------------------------------------------------------------- DB SELECTION ---------

if (@$_REQUEST["db"]) { //if uri has DB then use it
    $dbName = $_REQUEST["db"];
} else if (@$_SERVER["HTTP_REFERER"] && preg_match("/.*db=([^&]*).*/", $_SERVER["HTTP_REFERER"], $refer_db)) { //else check refer
    //this is very unreliable!!!!
    $dbName = $refer_db[1];
}

if (!@$dbName) {
    define('HEURIST_DBNAME', '');
    if (defined("NO_DB_ALLOWED")) { //for createNewDB.php and selectDatabase.php
        return;
    } else {
        //Ambiguous database name, or no database name supplied
        returnErrorMsgPage(2);
    }

}

define('HEURIST_DBNAME', $dbName);
$dbFullName = HEURIST_DB_PREFIX . HEURIST_DBNAME;
if ($dbFullName == "") {
    returnErrorMsgPage(0, "Invalid database name - both the prefix and the database name are blank");
}

define('HEURIST_SESSION_DB_PREFIX', $dbFullName . ".");

// we have a database name so test it out
if (mysql_query("use $dbFullName")) {
    define('DATABASE', $dbFullName);
} else {
    if (defined("NO_DB_ALLOWED")) { //for createNewDB.php and selectDatabase.php
        return;
    }  else {// unknown database, tranfers message to selectDatabase.php, MySQL err includes db name
        returnErrorMsgPage(2, "Unable to open database: " . mysql_error());
    }
}

// using the database so let's get the configuration data from it's sys table
$res = mysql_query('select * from sysIdentification');
if (!$res) returnErrorMsgPage(0, "Unable to read database description from sysIdentification table. ".
    "May indicate corrupted database. ".$talkToSysAdmin." MySQL error: " . mysql_error());
$sysValues = mysql_fetch_assoc($res);

// set up user access and group table stuff
$udb = $sysValues['sys_UGrpsDatabase'];
if ($udb) {
    define('USERS_DATABASE', $udb);
} else {
    define('USERS_DATABASE', DATABASE); //use the system db for UGrp information

}

// access control logic defines
define('USERS_TABLE', 'sysUGrps');
define('USERS_ID_FIELD', 'ugr_ID');
define('USERS_USERNAME_FIELD', 'ugr_Name');
define('USERS_PASSWORD_FIELD', 'ugr_Password');
define('USERS_FIRSTNAME_FIELD', 'ugr_FirstName');
define('USERS_LASTNAME_FIELD', 'ugr_LastName');
define('USERS_ACTIVE_FIELD', 'ugr_Enabled');
define('USERS_EMAIL_FIELD', 'ugr_eMail');
define('GROUPS_TABLE', 'sysUGrps');
define('GROUPS_ID_FIELD', 'ugr_ID');
define('GROUPS_NAME_FIELD', 'ugr_Name');
define('GROUPS_TYPE_FIELD', 'ugr_Type');
define('USER_GROUPS_TABLE', 'sysUsrGrpLinks');
define('USER_GROUPS_USER_ID_FIELD', 'ugl_UserID');
define('USER_GROUPS_GROUP_ID_FIELD', 'ugl_GroupID');
define('USER_GROUPS_ROLE_FIELD', 'ugl_Role');

//-------------------------------------------------------------------------- PATHS AND URLS SPECIFIC TO DATABASE ---------

//File store for this particular instance may be redefined in the database
/* DO NOT use path defined in database
$path = @$sysValues['sys_UploadDirectory'];
if ($path) { //database override for uploading files.
$path = getRelativeFolder($path);
testDirWriteableAndDefine('HEURIST_FILESTORE_DIR', $documentRoot . $path, "File store folder"); // upload must be a full path
define('HEURIST_FILESTORE_URL', HEURIST_SERVER_URL . $path );    //full url to file store folder
}
if (!defined('HEURIST_FILESTORE_DIR')) { //store folder is not defined in DB - set it by default
*/

testDirWriteableAndDefine('HEURIST_FILESTORE_DIR', HEURIST_UPLOAD_ROOT . $dbName . '/', "File store folder", false);

if (!defined('HEURIST_FILESTORE_DIR')){ //fatal error - storage folder is not defined
    returnErrorMsgPage(1, "Cannot access filestore directory for this database: <b>". HEURIST_UPLOAD_ROOT . $dbName . '/' .
        "</b><p>Either the directory does not exist (check setting in heuristConfigIni.php file), or it is not writeable by PHP (check permissions).");

}

define('HEURIST_FILESTORE_URL', HEURIST_UPLOAD_ROOT_URL . $dbName . '/');    //full url to file store folder

// Define the site relative path for rectype icons
testDirWriteableAndDefine('HEURIST_SCRATCHSPACE_DIR', sys_get_temp_dir(), "Temporary directory"); //HEURIST_FILESTORE_DIR . "scratch-space/"
if (!defined('HEURIST_SCRATCHSPACE_DIR')) {
    returnErrorMsgPage(1, "Cannot access system temp directory <b>". sys_get_temp_dir() .
        "</b><p>Check permissions).");
}

// Define folder for File upload folder
testDirWriteableAndDefine('HEURIST_FILES_DIR', HEURIST_FILESTORE_DIR . "file_uploads/", "File upload folder");
define('HEURIST_FILES_URL', HEURIST_FILESTORE_URL . 'file_uploads/');

// Define the site relative path for rectype icons
testDirWriteableAndDefine('HEURIST_ICON_DIR', HEURIST_FILESTORE_DIR . "rectype-icons/", "Icons directory");
define('HEURIST_ICON_URL', HEURIST_FILESTORE_URL . 'rectype-icons/');

testDirWriteableAndDefine('HEURIST_THUMB_DIR', HEURIST_FILESTORE_DIR . "filethumbs/", "Thumbnails directory");
define('HEURIST_THUMB_URL', HEURIST_FILESTORE_URL . 'filethumbs/');
allowWebAccessForForlder( HEURIST_THUMB_URL );

testDirWriteableAndDefine('HEURIST_SETTING_DIR', HEURIST_FILESTORE_DIR . "settings/", "Settings directory");

testDirWriteableAndDefine('HEURIST_SMARTY_TEMPLATES_DIR', HEURIST_FILESTORE_DIR . "smarty-templates/", "Smarty Templates directory");

testDirWriteableAndDefine('HEURIST_XSL_TEMPLATES_DIR', HEURIST_FILESTORE_DIR . "xsl-templates/", "XSL templates directory");

$path = @$sysValues['sys_hmlOutputDirectory'];
if ($path) {
    $path = getRelativeFolder($path);
    testDirWriteableAndDefine('HEURIST_HML_DIR', $documentRoot.$path, "HML output directory");
}
if (!defined('HEURIST_HML_DIR')) {
    testDirWriteableAndDefine('HEURIST_HML_DIR', HEURIST_FILESTORE_DIR.'hml-output/', "HML output directory");
    define('HEURIST_HML_URL', HEURIST_FILESTORE_URL . 'hml-output/');
}
allowWebAccessForForlder( HEURIST_HML_DIR );

$path = @$sysValues['sys_htmlOutputDirectory'];
if ($path) {
    $path = getRelativeFolder($path);
    testDirWriteableAndDefine('HEURIST_HTML_DIR', $documentRoot . $path, "HTML output directory");
    define('HEURIST_HTML_URL', HEURIST_SERVER_URL . $path );
}
if (!defined('HEURIST_HTML_URL')) {
    testDirWriteableAndDefine('HEURIST_HTML_DIR', HEURIST_FILESTORE_DIR . 'html-output/', "HTML output directory");
    define('HEURIST_HTML_URL', HEURIST_FILESTORE_URL . 'html-output/');
}
allowWebAccessForForlder( HEURIST_HTML_URL );

// FAIMS MODULES
if(isset($defaultFaimsModulesPath)){
    if(file_exists($defaultFaimsModulesPath) && is_dir($defaultFaimsModulesPath)){
        define('HEURIST_FAIMS_DIR', $defaultFaimsModulesPath);
    }
}
/* should be explicitely defined
if (!defined('HEURIST_FAIMS_DIR')) {
$path = HEURIST_FILESTORE_DIR . 'faims';
if(file_exists($path) && is_dir($path)){
define('HEURIST_FAIMS_DIR', $path);
}
}*/

//--------------------------------------------------------------------------------------------------------------

// ID of Heurist System Workgroup which has special privileges - deprecated, although more generally
// group 1 on every database is the Database Managers group
define('HEURIST_SYS_GROUP_ID', 1);


if ($sysValues['sys_AllowRegistration']) {
    define('HEURIST_ALLOW_REGISTRATION', 1);
} else {
    define('HEURIST_ALLOW_REGISTRATION', 0);
}

if ($sysValues['sys_OwnerGroupID']) {
    define('HEURIST_OWNER_GROUP_ID', $sysValues['sys_OwnerGroupID']);
} else {
    define('HEURIST_OWNER_GROUP_ID', 1);
}

if (@$sysValues['sys_RestrictAccessToOwnerGroup'] > 0) {
    define('HEURIST_RESTRICT_GROUP_ID', $sysValues['sys_OwnerGroupID']);
}

if (@$sysValues['sys_SetPublicToPendingOnEdit'] == 1) {
    define('HEURIST_PUBLIC_TO_PENDING', 1);
} else {
    define('HEURIST_PUBLIC_TO_PENDING', 0);
}

define('HEURIST_NEWREC_OWNER_ID', $sysValues['sys_NewRecOwnerGrpID']);
define('HEURIST_NEWREC_ACCESS', $sysValues['sys_NewRecAccess']);
define('HEURIST_DBID', $sysValues['sys_dbRegisteredID']);
define('HEURIST_DBVERSION', "" . $sysValues['sys_dbVersion'] . "." . $sysValues['sys_dbSubVersion'] . "." . $sysValues['sys_dbSubSubVersion']);
define('HEURIST_ZOTEROSYNC', $sysValues['sys_SyncDefsWithDB']);

if (!defined('SKIP_VERSIONCHECK') && HEURIST_MIN_DBVERSION > HEURIST_DBVERSION) {

    returnErrorMsgPage(3, "Heurist Code Version " . HEURIST_VERSION . " requires Database Schema version # " .
        HEURIST_MIN_DBVERSION . " or higher. " . HEURIST_DBNAME . " has version # " . HEURIST_DBVERSION . " - ".$talkToSysAdmin);
}


// url of 3d party service that generates thumbnails for given website, set for installation in intialise.php
define('WEBSITE_THUMBNAIL_SERVICE', $websiteThumbnailService);
define('WEBSITE_THUMBNAIL_USERNAME', $websiteThumbnailUsername);
define('WEBSITE_THUMBNAIL_PASSWORD', $websiteThumbnailPassword);
define('WEBSITE_THUMBNAIL_XSIZE', $websiteThumbnailXsize);
define('WEBSITE_THUMBNAIL_YSIZE', $websiteThumbnailYsize);

// MAGIC CONSTANTS for limited set of common rectypes and their detail types
// they refer to global definition DB and IDs of rectypes/detailtypes there
define('RT_BUG_REPORT', "2-253");
define('DT_BUG_REPORT_NAME', "2-1");
define('DT_BUG_REPORT_FILE', "2-38");
define('DT_BUG_REPORT_DESCRIPTION', "2-3");
define('DT_BUG_REPORT_STEPS', "2-4");
define('DT_BUG_REPORT_EXTRA_INFO', "2-51");
define('DT_BUG_REPORT_STATUS', "2-810");
define('DT_ALL_ASSOC_FILE', '2-38');


// NOTE: These duplicate those in consts.php
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

    // Record types added by SW and SH for their extensions, no longe in core definitions, now in DB 4 HeuristToolExtensions
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
    
    
    'RT_AUTHOR_EDITOR' => array(3, 23), //Deprecated
    'RT_FACTOID' => array(3, 22), // Deprecated

    // Spatial data
    'RT_IMAGE_SOURCE' => array(2, 11), //TODO : change RT_TILED_IMAGE
    'RT_TILED_IMAGE_SOURCE' => array(2, 11), // added Ian 23/10/14 for consistency
    'RT_PLACE' => array(3, 1009),
    'RT_KML_SOURCE' => array(3, 1014),
    'RT_SHP_SOURCE' => array(3, 1017),
    'RT_GEOTIFF_SOURCE' => array(3, 1018),
    'RT_MAP_DOCUMENT' => array(3,1019), // HeuristReferenceSet DB 3: Map document, layers and queries for new map function Oct 2014
    'RT_MAP_LAYER' => array(3,1020),
    'RT_QUERY_SOURCE' => array(3,1021)

);

foreach ($rtDefines as $str => $id) {
    defineRTLocalMagic($str, $id[1], $id[0]);
}

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
    'DT_LOCATION' => array(2, 27), // TODO : change DT_PLACE_NAME with new update.
    'DT_GEO_OBJECT' => array(2, 28),
    'DT_MIME_TYPE' => array(2, 29),
    'DT_MAP_IMAGE_LAYER_SCHEMA' => array(2, 31),
    'DT_MINMUM_ZOOM_LEVEL' => array(2, 32),
    'DT_MAXIMUM_ZOOM_LEVEL' => array(2, 33),
    'DT_SERVICE_URL' => array(2, 34),
    'DT_ORIGINAL_RECORD_ID' => array(2, 36),
    'DT_FILE_RESOURCE' => array(2, 38),
    'DT_THUMBNAIL' => array(2, 39),
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
    'DT_FILE_NAME' => array(2, 62),
    'DT_FILE_FOLDER' => array(2, 63),
    'DT_FILE_EXT' => array(2, 64),
    'DT_FILE_DEVICE' => array(2, 65),
    'DT_FILE_DURATION' => array(2, 66),
    'DT_FILE_SIZE' => array(2, 67),
    'DT_FILE_MD5' => array(2, 68),
    'DT_PARENT_ENTITY' => array(2, 247),
    'DT_EDITOR' => array(3, 1013),
    'DT_OTHER_FILE' => array(3, 62), //TODO: remove from code
    'DT_LOGO_IMAGE' => array(3, 222), //TODO: remove from code
    'DT_IMAGES' => array(3, 224), //TODO: remove from code
    'DT_DOI' => array(3, 1003),
    'DT_WEBSITE_ICON' => array(3, 347), //TODO: remove from code
    'DT_ISBN' => array(3, 1011),
    'DT_ISSN' => array(3, 1032),
    'DT_JOURNAL_REFERENCE' => array(3, 1034),
    'DT_MEDIA_REFERENCE' => array(3, 508), //*******************ERROR  THIS IS MISSING
    'DT_TEI_DOCUMENT_REFERENCE' => array(3, 1045), //TODO : change DT_XML_DOCUMENT_REFERENCE with new update.
    'DT_KML_FILE' => array(3, 1044),
    'DT_KML' => array(3, 1036),
    'DT_MAP_IMAGE_LAYER_REFERENCE' => array(3, 1043),
    'DT_SHOW_IN_MAP_BG_LIST' => array(3, 679), // DEPRECATED  show image layer or kml in map background list
    'DT_ALTERNATE_NAME' => array(3, 1009),
    'DT_FULL_IMAG_URL' => array(70, 603), //TODO: remove from code
    'DT_THUMB_IMAGE_URL' => array(70, 606) // deprecated
); //TODO: add email magic numbers

foreach ($dtDefines as $str => $id) {
    defineDTLocalMagic($str, $id[1], $id[0]);
}


/**
* get an array of recType Defines for Magic Numbers used in core code.
* @global   array [$rtDefines] of define strings to concept pairs.
* @return   array of string defines for Magic Numbers
*/
function getRTDefineKeys() {
    global $rtDefines;
    return array_keys($rtDefines);
}


/**
* get an array of detailType Defines for Magic Numbers used in core code.
* @global   array [$dtDefines] of define strings to concept pairs.
* @return   array of string defines for Magic Numbers
*/
function getDTDefineKeys() {
    global $dtDefines;
    return array_keys($dtDefines);
}


/**
* bind Magic Number Constants to their local id
* @param    string [$defString] define string
* @param    int [$rtID] origin rectype id
* @param    int [$dbID] origin database id
*/
function defineRTLocalMagic($defString, $rtID, $dbID) {
    $id = rectypeLocalIDLookup($rtID, $dbID);
    if ($id) {
        define($defString, $id);
    }
}


/**
* bind Magic Number Constants to their local id
* @param    string [$defString] define string
* @param    int [$dtID] origin detailtype id
* @param    int [$dbID] origin database id
*/
function defineDTLocalMagic($defString, $dtID, $dbID) {
    $id = detailtypeLocalIDLookup($dtID, $dbID);
    if ($id) {
        define($defString, $id);
    }
}


/**
* lookup local id for a given rectype concept id pair
* @global    type description of global variable usage in a function
* @staticvar array [$RTIDs] lookup array of local ids
* @param     int [$rtID] origin rectype id
* @param     int [$dbID] origin database id (default to 2 which is reserved for coreDefinition)
* @return    int local rectype ID or null if not found
*/
function rectypeLocalIDLookup($rtID, $dbID = 2) {
    global $talkToSysAdmin;
    static $RTIDs;
    if (!$RTIDs) {
        $res = mysql_query('select rty_ID as localID,rty_OriginatingDBID as dbID,rty_IDInOriginatingDB as id from defRecTypes order by dbID');
        if (!$res) returnErrorMsgPage(0, "Unable to build internal record-type lookup table. ".$talkToSysAdmin." MySQL error: " . mysql_error());
        $RTIDs = array();
        while ($row = mysql_fetch_assoc($res)) {
            if (!@$RTIDs[$row['dbID']]) {
                $RTIDs[$row['dbID']] = array();
            }
            $RTIDs[$row['dbID']][$row['id']] = $row['localID'];
        }
    }
    return (@$RTIDs[$dbID][$rtID]>0 ? $RTIDs[$dbID][$rtID] : null);
}


/**
* lookup local id for a given detailtype concept id pair
* @global    type description of global variable usage in a function
* @staticvar array [$RTIDs] lookup array of local ids
* @param     int [$dtID] origin detailtype id
* @param     int [$dbID] origin database id (default to 2 which is reserved for coreDefinition)
* @return    int local detailtype ID or null if not found
*/
function detailtypeLocalIDLookup($dtID, $dbID = 2) {
    global $talkToSysAdmin;
    static $DTIDs;
    if (!$DTIDs) {
        $res = mysql_query('select dty_ID as localID,dty_OriginatingDBID as dbID,dty_IDInOriginatingDB as id from defDetailTypes order by dbID');
        if (!$res) returnErrorMsgPage(0, "Unable to build internal field-type lookup table. ".$talkToSysAdmin." MySQL error: " . mysql_error());
        $DTIDs = array();
        while ($row = mysql_fetch_assoc($res)) {
            if (!@$DTIDs[$row['dbID']]) {
                $DTIDs[$row['dbID']] = array();
            }
            $DTIDs[$row['dbID']][$row['id']] = $row['localID'];
        }
    }
    return (@$DTIDs[$dbID][$dtID]>0 ? $DTIDs[$dbID][$dtID] : null);
}


/**
* for directory or file path defines, test writability before creating define.
* @param    string [$defString] define string
* @param    string [$dir] string defining disk drive path
* @param    boolean [$isDocrootRelative] true will prepend document root path to $dir for testing only (default false)
* @param    boolean [$tryMakeDir] determines whether or not to attempt to make non existent directory (default false)
* @return   boolean true if successful define, false otherwise
*/
function testDirWriteableAndDefine($defString, $dir, $folderName, $tryMakeDir=true) {
    global $talkToSysAdmin;

    $info = new SplFileInfo($dir); //($isDocrootRelative ? HEURIST_DOCUMENT_ROOT . $dir : $dir));

    if ($info->isDir() && $info->isWritable()) {
        define($defString, $dir);
        return true;
    } else if (!$info->isDir() && $tryMakeDir) {
        if (@mkdir($info, 0777, true)) {
            define($defString, $dir);
            return true;
        }
    }

    return false;
}
//
// copy htaccess
//
function allowWebAccessForForlder($folder){
    if(file_exists($folder) && is_dir($folder) && !file_exists($folder.'/.htaccess')){
        $res = copy(HEURIST_DIR.'admin/setup/.htaccess_via_url', $folder.'/.htaccess');
    }
}



function getRelativeFolder($upload){
    global $documentRoot;

    if ($upload != "/" && !preg_match("/[^\/]\/$/", $upload)) { //check for trailing /
        $upload.= "/"; // append trailing /

    }
    if ($upload != "/" && !preg_match("/^\/[^\/]/", $upload)) { //check for leading /
        $upload = "/" . $upload; // prepend leading /

    }
    //if value not a subdir of DocRoot, assume it a relative path and prepend DocRoot
    if (strpos($upload, $documentRoot) !== false) { // if
        $upload = substr($upload, strlen($documentRoot));
    }
    return $upload;
}


/**
* exit to page with error information for the user
* @param    int [$critical] level of criticality 1- unable to proceed 2-possible misname of db 0-db connection problem
* @param    string [$msg] error message
*/
function returnErrorMsgPage($critical, $msg = null) {
    $redirect = null;

    if ($critical == 1) { // bad connection to MySQL server

        $msg2 = "<p>&nbsp;Heurist initialisation error<p> ".$msg?$msg:"".
        " <p><i>Please consult your system administrator for help, or email: info - a t - HeuristNetwork.org </i></p>";
        $msg2 = rawurlencode($msg2);
        $redirect = HEURIST_BASE_URL . "common/html/msgErrorMsg.html?msg=" . $msg2;

    } else if ($critical == 2) { //database not defined or cannot connect to it
        $redirect = HEURIST_BASE_URL . "common/connect/selectDatabase.php";
        if ($msg) {
            $redirect.= "?msg=" . rawurlencode($msg);
        }

    } else if ($critical == 3) { // db required upgrade

        $redirect = HEURIST_BASE_URL . "admin/setup/dbupgrade/upgradeDatabase.php?db=".HEURIST_DBNAME;

    } else {
        // gets to here if database not specified properly. This is an error if the system is set up properly, but not at
        // first initialisaiton of the system, so test for existence of databases, if none then Heurist has not been set up yet.
        // Placed here rather than up-front test to avoid having to test this in every script
        $list = mysql__getdatabases();
        if (count($list) > 0) {
            $msg2 = "<p>&nbsp;Cannot open database, but cause of error unknown.<p><br><br>".$msg?$msg:"".
            "<p><br><br><i>Please consult your system administrator for help, or email: info - a t - HeuristNetwork.org </i></p>";
            $msg2 = rawurlencode($msg2);
            $redirect = HEURIST_BASE_URL . "common/html/msgErrorMsg.html?msg=" . $msg2;
        }
    }


    if ($redirect) {

        if (defined('ISSERVICE')) { // ISSERVICE set by files (~27) which include initialise.php. 0 if returns html, otherwise 1
            echo "/* Info: it happens in " . $_SERVER['PHP_SELF'] . " */ location.replace(\"" . $redirect . "\");";
        } else {
            header("Location: " . $redirect);
        }
    }

    exit(); // it will drop through to here without an error message if the system has not been set up yet

}

function getInstallationDirectory($scriptName){

    // a pipe delimited list of the top level directories in the heurist code base root. Only change if new ones are added.
    $topDirs = "admin|applications|common|context_help|export|hapi|hclient|hserver|import|records|redirects|search|viewers|help|ext|external";

    // calculate the dir where the Heurist code is installed, for example /h4 or /h4-ij
    $installDir = preg_replace("/\/(" . $topDirs . ")\/.*/", "", $scriptName); // remove "/top level dir" and everything that follows it.
    if ($installDir == $scriptName) { // no top directories in this URI must be a root level script file or blank
        $installDir = preg_replace("/\/[^\/]*$/", "", $scriptName); // strip away everything past the last slash "/index.php" if it's there
    }

    //the subdir of the server's document directory where heurist is installed
    if ($installDir == $scriptName || $installDir == '') {
        // this should be the path difference between document root and heurist code root
        $installDir = '/';
    }else{
        $installDir = $installDir.'/';
    }
    return $installDir;
}
?>