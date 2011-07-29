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
	$upload = $sysValues['sys_UploadDirectory'];
	if ($upload) {
		//	error_log("upload = $upload");
		define('HEURIST_UPLOAD_PATH', $upload);// upload must be a full path
		}else{
		if ($defaultRootFileUploadPath) {
			define('HEURIST_UPLOAD_PATH', $defaultRootFileUploadPath.$dbName."/");
		} else {
			define('HEURIST_UPLOAD_PATH', HEURIST_DOCUMENT_ROOT."/uploaded-heurist-files/$dbName/");
		}
	}

	//define cocoon record explorer URL
	if (file_exists(HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH."/viewers/relbrowser/".HEURIST_DBNAME)) {
		$browserSubDir = HEURIST_DBNAME;
	}else{
		$browserSubDir = "main";
	}

	define('EXPLORE_URL',"/cocoon".HEURIST_SITE_PATH."viewers/relbrowser/".$browserSubDir."/item/");
	//TODO : change define 'UPLOAD_PATH' to HEURIST_UPLOAD_PATH for now duplicate
	define('UPLOAD_PATH',HEURIST_UPLOAD_PATH);
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

	// set up email defines
	if ($infoEmail) {
		define('HEURIST_MAIL_TO_INFO', $infoEmail);	//mailto string for heurist installation issues
		}

	if ($sysAdminEmail) {
		define('HEURIST_MAIL_TO_ADMIN', $sysAdminEmail);	//mailto string for heurist installation issues
		}else if ($infoEmail){
		define('HEURIST_MAIL_TO_ADMIN', $infoEmail);	//mailto string for heurist installation issues
		}
function returnErrorMsgPage($msg) {
		echo "location.replace(\"".HEURIST_BASE_URL."common/html/msgErrorMsg.html?msg=$msg\");";
		exit();
}
?>
