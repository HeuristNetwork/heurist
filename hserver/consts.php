<?php

/**
* List of system constants
*
* (@todo ?? include this file into System.php )
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


// TODO: Rationalise th duplication of constants across /php/consts.php and /common/connect/initialise.php
//       in particualr this duplication of HEURIST_MIN_DB_VERSION and any other explicit constants

define('HEURIST_VERSION', $version);  //code version is defined congigIni.php
define('HEURIST_MIN_DBVERSION', "1.2.0");
define('HEURIST_HELP', "http://heurist.sydney.edu.au/help");
define('HEURIST_INDEX_BASE_URL', "http://heurist.sydney.edu.au/heurist/");

if (@$httpProxy != '') {
    define('HEURIST_HTTP_PROXY', $httpProxy); //http address:port for proxy request
    if (@$httpProxyAuth != '') {
        define('HEURIST_HTTP_PROXY_AUTH', $httpProxyAuth); // "username:password" for proxy authorization
    }
}

if (!@$serverName) {
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
elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
    $isSecure = true;
}
$REQUEST_PROTOCOL = $isSecure ? 'https' : 'http';


$serverBaseURL = $REQUEST_PROTOCOL . "://" . $serverName;

// calculate the dir where the Heurist code is installed, for example /h4 or /h4-ij
$topdirs = "admin|applications|common|context_help|export|hapi|hclient|hserver|import|records|redirects|search|viewers|help|ext|external"; // Upddate in 3 places if changed

$installDir = preg_replace("/\/(" . $topdirs . ")\/.*/", "", @$_SERVER["SCRIPT_NAME"]); // remove "/top level dir" and everything that follows it.
if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // no top directories in this URI must be a root level script file or blank
    $installDir = preg_replace("/\/[^\/]*$/", "", @$_SERVER["SCRIPT_NAME"]); // strip away everything past the last slash "/index.php" if it's there
}

if ($installDir == @$_SERVER["SCRIPT_NAME"]) { // this should be the path difference between document root and heurist code root
    $installDir = '/';
}else{
    $installDir = $installDir.'/';
}

define('HEURIST_CURRENT_URL', $serverBaseURL . $_SERVER["REQUEST_URI"]);
define('HEURIST_SERVER_NAME', @$serverName); // server host name for the configured name, eg. Heurist.sydney.edu.au
define('HEURIST_DIR', @$_SERVER["DOCUMENT_ROOT"] . $installDir); //  eg. /var/www/html/HEURIST @todo - read simlink (realpath)
define('HEURIST_SERVER_URL', $serverBaseURL);
define('HEURIST_BASE_URL', $serverBaseURL . $installDir ); // eg. http://heurist.sydney.edu.au/h4/

define('HEURIST_SCRATCHSPACE_DIR', sys_get_temp_dir());

if ($dbHost) {
    define('HEURIST_DBSERVER_NAME', $dbHost);
} else {
    define('HEURIST_DBSERVER_NAME', "localhost"); //configure to access mysql on the same machine as the Heurist codebase

}
/*  @todo - redirect to system config error page

if (!($dbAdminUsername && $dbAdminPassword && $dbReadonlyUsername && $dbReadonlyPassword)) { //if these are not specified then we can't do anything
returnErrorMsgPage(1, "MySql user account/password not specified. Set in configIni.php");
}
if(preg_match('/[^a-z_\-0-9]/i', $dbAdminPassword)){
//die("MySql user password contains non valid charactes. Only alphanumeric allowed. Set in configIni.php");
returnErrorMsgPage(1, "MySql user password may not contain special characters. To avoid problems down the line they are restricted to alphanumeric only. Set in configIni.php");
}
*/
define('ADMIN_DBUSERNAME', $dbAdminUsername); //user with all rights so we can create databases, etc.
define('ADMIN_DBUSERPSWD', $dbAdminPassword);
define('HEURIST_DB_PREFIX', $dbPrefix);

//---------------------------------

define('HEURIST_TITLE', 'Heurist Academic Knowledge Management System - &copy; 2005-2016 The University of Sydney.');
$talkToSysAdmin="Please advise your system administrator or email info - at - HeuristNetwork.org for assistance.";

/**
* Response status for ajax requests. See ResponseStatus in hapi.js
*/
define("HEURIST_INVALID_REQUEST", "invalid");    // The Request provided was invalid.
define("HEURIST_NOT_FOUND", "notfound");         // The requested object not found.
define("HEURIST_ERROR", "error");                // General error: wrong data, file i/o
define("HEURIST_OK", "ok");                      // The response contains a valid Result.
define("HEURIST_REQUEST_DENIED", "denied");      // The webpage is not allowed to use the service.
define("HEURIST_ACTION_BLOCKED", "blocked");     // No enough rights or action is blocked by constraints
define("HEURIST_UNKNOWN_ERROR", "unknown");      // A request could not be processed due to a server error. The request may succeed if you try again.
define("HEURIST_DB_ERROR", "database");          // A request could not be processed due to a server database error. Most probably this is BUG. Contact developers
define("HEURIST_SYSTEM_CONFIG", "syscfg");       // System not-fatal configuration error. Contact system admin
define("HEURIST_SYSTEM_FATAL", "system");        // System fatal configuration error. Contact system admin
/*
$usrTags = array(
"rty_ID"=>"i",
"rty_Name"=>"s",
"rty_OrderInGroup"=>"i",
"rty_Description"=>"s",
"rty_TitleMask"=>"s",
"rty_CanonicalTitleMask"=>"s",
"rty_Plural"=>"s",
"rty_Status"=>"s",
"rty_OriginatingDBID"=>"i",
"rty_NameInOriginatingDB"=>"s",
"rty_IDInOriginatingDB"=>"i",
"rty_NonOwnerVisibility"=>"s",
"rty_ShowInLists"=>"i",
"rty_RecTypeGroupID"=>"i",
"rty_RecTypeModelsIDs"=>"s",
"rty_FlagAsFieldset"=>"i",
"rty_ReferenceURL"=>"s",
"rty_AlternativeRecEditor"=>"s",
"rty_Type"=>"s",
"rty_ShowURLOnEditForm" =>"i",
"rty_ShowDescriptionOnEditForm" =>"i",
"rty_Modified"=>"i",
"rty_LocallyModified"=>"i"
);
*/

//---------------------------------
// set up email defines
//
define('HEURIST_MAIL_TO_BUG', $bugEmail?$bugEmail:'info@HeuristNetwork.org');
define('HEURIST_MAIL_TO_INFO', $infoEmail?$infoEmail:'info@HeuristNetwork.org');
define('HEURIST_MAIL_TO_ADMIN', $sysAdminEmail?$sysAdminEmail:HEURIST_MAIL_TO_INFO);

//---------------------------------

/** RECORD TYPE DEFINITIONS */

// NOTE: These duplicate those in initialise.php

$rtDefines = array(
    // Standard core record types (HeuristCoreDefinitions: DB = 2)
    'RT_RELATION' => array(2, 1),
    'RT_INTERNET_BOOKMARK' => array(2, 2),
    'RT_NOTE' => array(2, 3),
    'RT_MEDIA_RECORD' => array(2, 5),
    'RT_AGGREGATION' => array(2, 6),
    'RT_COLLECTION' => array(2, 6), // duplicate naming
    'RT_BLOG_ENTRY' => array(2, 7),
    'RT_INTERPRETATION' => array(2, 8),
    'RT_PERSON' => array(2, 10),

    // see also spatial data types below
    'RT_IMAGE_SOURCE' => array(2, 11), //TODO : change RT_TILED_IMAGE
    'RT_TILED_IMAGE_SOURCE' => array(2, 11), // added Ian 23/10/14 for consistency

    // Record types added by SW and SH for their extensions, no longe in core definitions, now in DB 4 Heurist ToolExtensions
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
    'RT_KML_SOURCE' => array(3, 1014),
    'RT_SHP_SOURCE' => array(3, 1017),
    'RT_GEOTIFF_SOURCE' => array(3, 1018),
    'RT_MAP_DOCUMENT' => array(3, 1019), // HeuristReferenceSet DB 3: Map document, layers and queries for new map function Oct 2014
    'RT_MAP_LAYER' => array(3, 1020),
    'RT_QUERY_SOURCE' => array(3, 1021),  //RT_MAPABLE_QUERY

    //Web content
    'RT_WEB_CONTENT' => array(1147, 25)
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
    'DT_LOCATION' => array(2, 27), // TODO : change DT_PLACE_NAME with new update.
    'DT_GEO_OBJECT' => array(2, 28),
    'DT_MIME_TYPE' => array(2, 29),
    'DT_IMAGE_TYPE' => array(2, 30),
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
    'DT_ORDER' => array(1147, 94), //order of web content - origin DH
    // Spatial & mapping
    'DT_KML_FILE' => array(3, 1044),
    'DT_KML' => array(3, 1036),
    'DT_MAP_IMAGE_LAYER_REFERENCE' => array(3, 1043),
    'DT_SHOW_IN_MAP_BG_LIST' => array(3, 679), // DEPRECATED  show image layer or kml in map background list
    'DT_ALTERNATE_NAME' => array(3, 1009),
    'DT_FULL_IMAG_URL' => array(70, 603), //TODO: remove from code
    'DT_THUMB_IMAGE_URL' => array(70, 606), // deprecated
    // Map document
    'DT_MAP_LAYER' => array(3, 1081),
    'DT_TOP_MAP_LAYER' => array(3, 1096),           //deprecated
    'DT_LONGITUDE_CENTREPOINT' => array(3, 1074),   //deprecated
    'DT_LATITUDE_CENTREPOINT' => array(3, 1075),    //deprecated
    'DT_MINOR_SPAN' => array(3, 1076),              //deprecated
    'DT_MAP_BOOKMARK' => array(3, 1082),
    'DT_MINIMUM_MAP_ZOOM' => array(3, 1077), // from Jan 2017 uses DT_MINIMUM_ZOOM and DT_MAXIMUM_ZOOM for both maps and layers
    'DT_MAXIMUM_MAP_ZOOM' => array(3, 1078), // prior Jan 2017 some databases used one, some used the other. Either now used for setting.
    'DT_SYMBOLOGY_POINTMARKER' => array(3, 1091), 
    
    'DT_DATA_SOURCE' => array(3, 1083),
    'DT_MINIMUM_ZOOM' => array(3, 1085), // from Jan 2017 uses DT_MINIMUM_ZOOM and DT_MAXIMUM_ZOOM for both maps and layers
    'DT_MAXIMUM_ZOOM' => array(3, 1086), // prior Jan 2017 some databases used one, some used the other. Either now used for setting.
    'DT_OPACITY' => array(3, 1090),
    'DT_COLOR' => array(3, 1037),
    // Shape
    'DT_ZIP_FILE' => array(3, 1072),
    'DT_SHAPE_FILE' => array(3, 1069),
    'DT_DBF_FILE' => array(3, 1070),
    'DT_SHX_FILE' => array(3, 1071)


); //TODO: add email magic numbers

//---------------------------------



?>
