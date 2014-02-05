<?php
/**
* 
* List of system constants 
*
* (@todo ?? include this file into System.php )
* 
*/
define('HEURIST_VERSION', $version);
define('HEURIST_MIN_DBVERSION', "1.1.0");
define('HEURIST_HELP', "http://heuristscholar.org/help");

if (!$serverName) {
    $serverName = $_SERVER["SERVER_NAME"] . ((is_numeric(@$_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80") ? ":" . $_SERVER["SERVER_PORT"] : "");
}

$serverBaseURL = ((array_key_exists("HTTPS", $_SERVER) && $_SERVER["HTTPS"] == "on") ? "https://" : "http://") . $serverName;

// calculate the dir where the Heurist code is installed, for example /h3 or /h3-ij
$topdirs = "admin|applications|common|export|external|hapi|help|import|records|search|viewers";

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
define('HEURIST_SERVER_NAME', $serverName); // server host name for the configured name, eg. heuristscholar.org
define('HEURIST_DOCUMENT_ROOT', @$_SERVER["DOCUMENT_ROOT"].'/'.$installDir); //  eg. /var/www/htdocs
define('HEURIST_BASE_URL', $serverBaseURL . $installDir . 'applications/h4/'); // eg. http://heuristscholar.org/h3/
define('HEURIST_BASE_URL_OLD', $serverBaseURL . $installDir ); // access to old app


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

define('HEURIST_TITLE', 'Heurist new UI');

/**
* Response status for ajax requests. See ResponseStatus in hapi.js
*/
define("HEURIST_INVALID_REQUEST", "invalid");    // The Request provided was invalid.
define("HEURIST_NOT_FOUND", "notfound");         // The requested object not found.
define("HEURIST_OK", "ok");                      // The response contains a valid Result.
define("HEURIST_REQUEST_DENIED", "denied");      // The webpage is not allowed to use the service.
define("HEURIST_UNKNOWN_ERROR", "unknown");      // A request could not be processed due to a server error. The request may succeed if you try again.
define("HEURIST_DB_ERROR", "database");          // A request could not be processed due to a server database error. Most probably this is BUG. Contact developers
define("HEURIST_SYSTEM_FATAL", "system");        // System fatal configuration. Contact system admin
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
?>
