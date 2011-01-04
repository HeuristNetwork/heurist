<?php
//TODO:  add code here to read db.ini and any other ini file tha twe come up with.
define('HEURIST_TOP_DIRS',"admin|common|export|external|hapi|help|import|records|search|tools|user|viewers");	// this is the path from the heurist code base root. Only change if file moves.
define('HEURIST_SERVER_NAME', @$_SERVER["SERVER_NAME"]);	// this will read the server host name for the configured name.

if (HEURIST_SERVER_NAME) {
	define('HOST_BASE', HEURIST_SERVER_NAME);	//this should be the main domain server name
}else{
	define('HOST_BASE', 'heuristscholar.org');	//put your default domain server name here // FIXME saw read this from db.ini
}

define('HEURIST_HOST_NAME', @$_SERVER["HTTP_HOST"]);	// this will read the server host name for the configured name.

$installDir = preg_replace("/\/(".HEURIST_TOP_DIRS.")\/.*/","",@$_SERVER["SCRIPT_NAME"]);

if($installDir == @$_SERVER["SCRIPT_NAME"]) {	// no top directories in this URI must be a root level script file or blank
	$installDir = preg_replace("/\/index.php/","",@$_SERVER["SCRIPT_NAME"]);
}
if($installDir != @$_SERVER["SCRIPT_NAME"]) {	// this should be the path difference between document root and heurist code root
	define('INSTALL_DIR', $installDir);	//the subdir of the servers document directory where heurist is installed
}else{
	define('INSTALL_DIR', '');	//the default is the document root directory
}

define('HEURIST_DEFAULT_INSTANCE','');	//default instance when the URI is abiguous about the instance
define('HEURIST_REQUEST_URI',@$_SERVER["REQUEST_URI"]);
define('HEURIST_DOCUMENT_ROOT',@$_SERVER["DOCUMENT_ROOT"]);
//define('HEURIST_UPLOAD_BASEPATH',HEURIST_DOCUMENT_ROOT.'/uploaded-heurist-files');
define('HEURIST_UPLOAD_BASEPATH','/var/www/htdocs/uploaded-heurist-files'); // FIXME saw read this from db.ini
define('HEURIST_SITE_PATH',INSTALL_DIR.'/');
define('HEURIST_RELATIVE_INSTALL_PATH',INSTALL_DIR.'/');
define('HEURIST_URL_BASE','http://'.HEURIST_HOST_NAME.HEURIST_SITE_PATH);
define('HEURIST_FULL_INSTALLPATH',HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH);
define('HEURIST_COMMON_DB', '`heurist-common`');	//this is the shared definitions and configuration database for the installation
define('HEURIST_DB_PREFIX', 'heuristdb-');	//database name prefix for instance // FIXME saw read this from db.ini
define('HEURIST_MAIL_TO_INFO', 'info@heuristscholar.org');	//mailto string for heurist installation issues
?>
