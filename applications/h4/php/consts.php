<?php
/**
* 
* List of system constants 
*
* (@todo ?? include this file into System.php )
* 
*/
define('HEURIST_SERVER_NAME', 'http://127.0.0.1/h4/');
//define('HEURIST_SERVER_NAME', 'http://178.170.121.118/h4/');
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
