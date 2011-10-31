<?php

	/*<!--
	* filename, brief description, date of creation, by whom
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	-->*/

	require_once(dirname(__FILE__).'/../../configIni.php');  // read in the configuration file

	/*
	the standard initialisation file configIni.php is in the root directory of the Heurist
	distribution and contains blank definitions. This file should be edited to set the configuration
	of your installation. If the 'blank' initialisation file is found, Heurist will look for the
	file one level up (this allows the initialisation file to be included in the source code, while
	a developer can specify passwords etc. without including them in the source code they distribute).

	configIni.php sets the following:
	$dbHost - - host for the MySQL server
	$dbAdminUsername, $dbAdminPassword - read/write user and password
	$dbReadonlyUsername, $dbReadonlyPassword - read-only user name and password
	$dbPrefix - prepended to all database names
	$defaultDBname - database to use if no ?db= specified
	$defaultRootFileUploadPath - root location for uploaded files
	$sysAdminEmail - email address of system adminstrator
	$infoEmail - redirect info@ emails to this address
	*/

	//set up system path defines
	define('HEURIST_VERSION',"3.1.1");
	// Update sub-sub-version weekly and record date 3.1.1 = 17/10/11
	define('HEURIST_MIN_DBVERSION',"1.0.0");

	define('HEURIST_TOP_DIRS',"admin|common|export|external|hapi|help|import|records|search|viewers");	// this is the path from the heurist code base root. Only change if file moves.
	define('HEURIST_SERVER_NAME', @$_SERVER["SERVER_NAME"]);	// this will read the server host name for the configured name.
	define('HEURIST_HOST_NAME', @$_SERVER["HTTP_HOST"]);	//
	define('HEURIST_DOCUMENT_ROOT',@$_SERVER["DOCUMENT_ROOT"]);

	// calculate the dir where the Heurist code is installed
	$installDir = preg_replace("/\/(".HEURIST_TOP_DIRS.")\/.*/","",@$_SERVER["SCRIPT_NAME"]);// remove "/top level dir" and everything that follows it.

	if($installDir == @$_SERVER["SCRIPT_NAME"]) {	// no top directories in this URI must be a root level script file or blank
		$installDir = preg_replace("/\/index.php/","",@$_SERVER["SCRIPT_NAME"]);// strip away the "/index.php" if it's there
		}
	if($installDir != @$_SERVER["SCRIPT_NAME"]) {	// this should be the path difference between document root and heurist code root
		define('INSTALL_DIR', $installDir);	//the subdir of the servers document directory where heurist is installed
		}else{
		define('INSTALL_DIR', '');	//the default is the document root directory
		}

	define('HEURIST_SITE_PATH',INSTALL_DIR == ''? '/' : INSTALL_DIR.'/');
	define('HEURIST_BASE_URL','http://'.HEURIST_HOST_NAME.HEURIST_SITE_PATH);

	//set up database server connection defines
	if ($dbHost) {
		define('HEURIST_DBSERVER_NAME', $dbHost);
	} else {
		define('HEURIST_DBSERVER_NAME', "localhost"); //configure to access mysql on the same machine as the Heruist codebase
		}

	if (!($dbAdminUsername && $dbAdminPassword && $dbReadonlyUsername && $dbReadonlyPassword)){ //if these are not specified then we can't do anything
		returnErrorMsgPage("MySql useracct or password not specified, Please look in configIni.php.");
	}
	define('ADMIN_DBUSERNAME',$dbAdminUsername);	//user with all rights so we can create databases, etc.
	define('ADMIN_DBUSERPSWD',$dbAdminPassword);
	define('READONLY_DBUSERNAME',$dbReadonlyUsername);	//readonly user for access to user and heurist databases
	define('READONLY_DBUSERPSWD',$dbReadonlyPassword);

	define('HEURIST_DB_PREFIX', (@$_REQUEST['prefix']? $_REQUEST['prefix'] : $dbPrefix));	//database name prefix which is added to db=name to compose the mysql dbname used in queries
	define('HEURIST_SYSTEM_DB', $dbPrefix."HeuristSystem");	//database which contains Heurist System level data
	define('HEURIST_REFERENCE_BASE_URL', "HTTP://heuristscholar.org/h3/");	//Heurist Installation which contains reference structure definition
	define('HEURIST_INDEX_BASE_URL', "HTTP://heuristscholar.org/h3/");	//Heurist Installation which contains index of registered Heurist databases
	define('HEURIST_SYS_GROUP_ID', 1);	//ID of Heurist System User Group which has special privileges
	//error_log("in initialise dbHost = $dbHost");
	//test db connect valid db
	$db = mysql_connect(HEURIST_DBSERVER_NAME, $dbAdminUsername, $dbAdminPassword) or returnErrorMsgPage("unable to connect to db server with admin acct: ".mysql_error());
	$db = mysql_connect(HEURIST_DBSERVER_NAME, $dbReadonlyUsername, $dbReadonlyPassword) or returnErrorMsgPage("unable to connect to db server with readonly acct: ".mysql_error());

	if ($defaultDBname != '') {
		define('HEURIST_DEFAULT_DBNAME',$defaultDBname);	//default dbname used when the URI is abiguous about the db
		}

	// error_log("initialise REQUEST = ".print_r($_REQUEST,true));

	if (@$_REQUEST["db"]) {
		$dbName = $_REQUEST["db"];
	}else if (@$_REQUEST["instance"]) { // saw TODO: temporary until change instance to db
		$dbName = $_REQUEST["instance"];
		// let's try the refer in case we are being called from an HTML page.
		}else if (@$_SERVER["HTTP_REFERER"] && preg_match("/.*db=([^&]*).*/",$_SERVER["HTTP_REFERER"],$refer_db)) {
		$dbName = $refer_db[1];
		// saw TODO: temporary until change instance to db
		}else if (@$_SERVER["HTTP_REFERER"] && preg_match("/.*instance=([^&]*).*/",$_SERVER["HTTP_REFERER"],$refer_instance)) {
		$dbName = $refer_instance[1];
	}
	if (!@$dbName) {
		if (@$_SESSION["heurist_last_used_dbname"]) {
			$dbName = $_SESSION["heurist_last_used_dbname"];
		}else if (defined("HEURIST_DEFAULT_DBNAME")) {
			$dbName = HEURIST_DEFAULT_DBNAME;
		} else {
			returnErrorMsgPage("ambiguous or no db name supplied");
		}
	}
	define('HEURIST_DBNAME', $dbName);
	$dbFullName = HEURIST_DB_PREFIX.HEURIST_DBNAME;
	if ($dbFullName == "") {
		returnErrorMsgPage("no db name determined");
	}
	define('HEURIST_SESSION_DB_PREFIX', $dbFullName.".");
	// we have a database name so test it out
	if (mysql_query("use $dbFullName")) {
		define('DATABASE', $dbFullName);
	} else {
		returnErrorMsgPage("unable to open db : $dbName - ".mysql_error());
	}

	// using the database so let's get the configuration data from it's sys table
	$res = mysql_query('select * from sysIdentification');
	if (!$res) returnErrorMsgPage("unable to retrieve db sys information -".mysql_error());
	$sysValues = mysql_fetch_assoc($res);

	// set up user access and group table stuff
	$udb = $sysValues['sys_UGrpsDatabase'];
	if ($udb) {
		define('USERS_DATABASE', $udb);
	}else{
		define('USERS_DATABASE',DATABASE); //use the system db for UGrp information
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

	// upload path
		if ($defaultRootFileUploadPath) {
		define('HEURIST_UPLOAD_ROOT', $defaultRootFileUploadPath);
		} else {
		define('HEURIST_UPLOAD_ROOT', HEURIST_DOCUMENT_ROOT."/uploaded-heurist-files/");
		}

	$upload = @$sysValues['sys_UploadDirectory'];
	if ($upload) {
		if (preg_match("/\/$/",$upload)) {
			$upload = preg_replace("/\/$/","",$upload);
	}
		//	error_log("upload = $upload");
		define('HEURIST_UPLOAD_DIR', $upload);// upload must be a full path
	} else {
		define('HEURIST_UPLOAD_DIR', HEURIST_UPLOAD_ROOT.$dbName);
	}

	// icon path
	define('HEURIST_ICON_ROOT', HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH."common/images/");
	define('HEURIST_ICON_DIR', HEURIST_ICON_ROOT.$dbName);

	//define cocoon record explorer URL
	if (file_exists(HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH."/viewers/relbrowser/".HEURIST_DBNAME)) {
		$browserSubDir = HEURIST_DBNAME;
	}else{
		$browserSubDir = "main";
	}

	define('EXPLORE_URL',"/cocoon".HEURIST_SITE_PATH."viewers/relbrowser/".$browserSubDir."/item/");
	// change  define('HEURIST_INSTANCE' to HEURIST_DBNAME
	define('HEURIST_INSTANCE',HEURIST_DBNAME);
	// change  define('HEURIST_INSTANCE_PREFIX' to HEURIST_SESSION_DB_PREFIX
	define('HEURIST_INSTANCE_PREFIX',HEURIST_SESSION_DB_PREFIX);
	// change HOST  HOST_BASE  to  HEURIST_HOST_NAME
	define('HOST',HEURIST_HOST_NAME);
	define('HOST_BASE',HEURIST_HOST_NAME);
	// change HEURIST_URL_BASE to HEURIST_BASE_URL
	define('HEURIST_URL_BASE',HEURIST_BASE_URL);

	if ($sysValues['sys_OwnerGroupID']){
		define ('HEURIST_OWNER_GROUP_ID', $sysValues['sys_OwnerGroupID']);
	}else{
		define ('HEURIST_OWNER_GROUP_ID', 1);
	}

	if ( @$sysValues['sys_RestrictAccessToOwnerGroup'] > 0) {
		define('HEURIST_RESTRICT_GROUP_ID',$sysValues['sys_OwnerGroupID']);
	}

	define ('HEURIST_NEWREC_OWNER_ID', $sysValues['sys_NewRecOwnerGrpID']);
	define ('HEURIST_NEWREC_ACCESS', $sysValues['sys_NewRecAccess']);
	define ('HEURIST_DBID', $sysValues['sys_dbRegisteredID']);
	define ('HEURIST_DBVERSION', "".$sysValues['sys_dbVersion'].".".$sysValues['sys_dbSubVersion'].".".$sysValues['sys_dbSubSubVersion']);
	if ( HEURIST_MIN_DBVERSION > HEURIST_DBVERSION ) {
		returnErrorMsgPage("Heurist Code Version ".HEURIST_VERSION." require database schema version of ".HEURIST_MIN_DBVERSION." or higher. ".
			HEURIST_DBNAME." has a version of ". HEURIST_DBVERSION.", please update the schema.");
	}

	define ('HEURIST_HML_PUBPATH', $sysValues['sys_hmlOutputDirectory']);
	define ('HEURIST_HTML_PUBPATH', $sysValues['sys_htmlOutputDirectory']);


	// set up email defines
	if ($infoEmail) {
		define('HEURIST_MAIL_TO_INFO', $infoEmail);	//mailto string for heurist installation issues
		}

	if ($sysAdminEmail) {
		define('HEURIST_MAIL_TO_ADMIN', $sysAdminEmail);	//mailto string for heurist installation issues
	}else if ($infoEmail){
		define('HEURIST_MAIL_TO_ADMIN', $infoEmail);	//mailto string for heurist installation issues
	}

	// url of 3d party service that generates thumbnails for given website, set for installation in intialise.php
	define('WEBSITE_THUMBNAIL_SERVICE',$websiteThumbnailService);
	define('WEBSITE_THUMBNAIL_USERNAME',$websiteThumbnailUsername);
	define('WEBSITE_THUMBNAIL_PASSWORD',$websiteThumbnailPassword);
	define('WEBSITE_THUMBNAIL_XSIZE',$websiteThumbnailXsize);
	define('WEBSITE_THUMBNAIL_YSIZE',$websiteThumbnailYsize);

	// MAGIC CONSTANTS for limited set of common rectypes and their detail types
	// they refer to global definition DB and IDs of rectypes/detailtypes there
	define('RT_BUG_REPORT',"2-216");
	define('DT_BUG_REPORT_NAME',"2-179");
	define('DT_BUG_REPORT_FILE',"2-221");
	define('DT_BUG_REPORT_DESCRIPTION',"2-303");
	define('DT_BUG_REPORT_ABSTRACT',"2-560");
	define('DT_BUG_REPORT_STATUS',"2-725");

	define('DT_ALL_ASSOC_FILE','2-221');

	$rtDefines = array(
		'RT_INTERNET_BOOKMARK' => array(2,2),
		'RT_NOTE' => array(2,3),
		'RT_JOURNAL_ARTICLE' => array(2,15),
		'RT_BOOK' => array(2,13),
		'RT_JOURNAL_VOLUME' => array(2,18),
		'RT_RELATION' => array(2,1),
		'RT_PERSON' => array(2,20),
		'RT_MEDIA_RECORD' => array(2,5),
		'RT_IMAGE_LAYER' => array(2,6),
		'RT_AUTHOR_EDITOR' => array(2,23),
		'RT_BLOG_ENTRY' => array(2,8),
		'RT_INTERPRETATION' => array(2,10),
		'RT_FACTOID' => array(2,22));

	foreach ($rtDefines as $str => $id) {
		defineRTLocalMagic($str,$id[1],$id[0]);
	}

	$dtDefines = array(
		'DT_TITLE' => array(2,1),
		'DT_GIVEN_NAMES' => array(2,33),
		'DT_ALTERNATE_NAME' => array(2,36),
		'DT_CREATOR' => array(2,19),
		'DT_EXTENDED_DESCRIPTION' => array(2,17),
		'DT_LINKED_RESOURCE' => array(2,4),
		'DT_RELATION_TYPE' => array(2,5),
		'DT_NOTES' => array(2,12),	//TODO: change dty to Short summary and remove from code
		'DT_PRIMARY_RESOURCE' => array(2,7),
		'DT_FULL_IMAG_URL' => array(2,603),	//TODO: remove from code
		'DT_THUMB_IMAGE_URL' => array(2,606),	//TODO: remove from code
		'DT_ASSOCIATED_FILE' => array(2,8),
		'DT_GEO_OBJECT' => array(2,11),
		'DT_OTHER_FILE' => array(2,62),
		'DT_LOGO_IMAGE' => array(2,222),
		'DT_THUMBNAIL' => array(2,9),
		'DT_IMAGES' => array(2,222),	//TODO: remove from code
		'DT_DATE' => array(2,16),
		'DT_START_DATE' => array(2,2),
		'DT_END_DATE' => array(2,3),
		'DT_INTERPRETATION_REFERENCE' => array(2,13),
		'DT_DOI' => array(2,99),
		'DT_WEBSITE_ICON' => array(2,347),
		'DT_ISBN' => array(2,97),
		'DT_ISSN' => array(2,108),
		'DT_JOURNAL_REFERENCE' => array(2,111),
		'DT_SHORT_SUMMARY' => array(2,12),
		'DT_MEDIA_REFERENCE' => array(2,508),
		'DT_TEI_DOCUMENT_REFERENCE' => array(2,322),
		'DT_START_ELEMENT' => array(2,539),
		'DT_END_ELEMENT' => array(2,540),
		'DT_START_WORD' => array(2,329),
		'DT_MIME_TYPE' => array(2,48),
		'DT_SERVICE_URL' => array(2,339),
		'DT_MAP_IMAGE_LAYER_SCHEMA' => array(2,585),
		'DT_KML_FILE' => array(2,552),
		'DT_TITLE_SHORT' => array(2,55),
		'DT_KML' => array(2,138),
		'DT_MINMUM_ZOOM_LEVEL' => array(2,586),
		'DT_MAP_IMAGE_LAYER_REFERENCE' => array(2,92),
		'DT_MAXIMUM_ZOOM_LEVEL' => array(2,587));

	foreach ($dtDefines as $str => $id) {
		defineDTLocalMagic($str,$id[1],$id[0]);
	}

function getRTDefineKeys(){
	global $rtDefines;
	return array_keys($rtDefines);
}

function getDTDefineKeys(){
	global $dtDefines;
	return array_keys($dtDefines);
}

function defineRTLocalMagic($defString, $rtID,$dbID) {
	$id = rectypeLocalIDLookup($rtID,$dbID);
	if ($id) {
		define($defString,$id);
	}
}

function defineDTLocalMagic($defString, $dtID,$dbID) {
	$id = detailtypeLocalIDLookup($dtID,$dbID);
	if ($id) {
		define($defString,$id);
	}
}

function rectypeLocalIDLookup($rtID,$dbID = 2) {
	static $RTIDs;
	if (!$RTIDs) {
		$res = mysql_query('select rty_ID as localID,rty_OriginatingDBID as dbID,rty_IDInOriginatingDB as id from defRecTypes order by dbID');
		if (!$res) returnErrorMsgPage("unable to build rectype ID lookup table -".mysql_error());
		$RTIDs = array();
		while ( $row = mysql_fetch_assoc($res)){
//		error_log("rt ". print_r($row,true));
			if (!@$RTIDs[$row['dbID']]){
				$RTIDs[$row['dbID']] = array();
			}
			$RTIDs[$row['dbID']][$row['id']] = $row['localID'];
		}
	}
	return (@$RTIDs[$dbID][$rtID] ? $RTIDs[$dbID][$rtID]: null);
}

function detailtypeLocalIDLookup($dtID,$dbID = 2) {
	static $DTIDs;
	if (!$DTIDs) {
		$res = mysql_query('select dty_ID as localID,dty_OriginatingDBID as dbID,dty_IDInOriginatingDB as id from defDetailTypes order by dbID');
		if (!$res) returnErrorMsgPage("unable to build detailtype ID lookup table -".mysql_error());
		$DTIDs = array();
		while ( $row = mysql_fetch_assoc($res)){
			if (!@$DTIDs[$row['dbID']]){
				$DTIDs[$row['dbID']] = array();
			}
			$DTIDs[$row['dbID']][$row['id']] = $row['localID'];
		}
	}
	return (@$DTIDs[$dbID][$dtID] ? $DTIDs[$dbID][$dtID]: null);
}

function returnErrorMsgPage($msg) {
		echo "location.replace(\"".HEURIST_BASE_URL."common/html/msgErrorMsg.html?msg=$msg\");";
		exit();
}
?>
