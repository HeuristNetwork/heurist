<?php
define('HEURIST_SERVER_NAME', @$_SERVER["SERVER_NAME"]);	// this will read the server host name for the configured name.
if (HEURIST_SERVER_NAME) {
	define('HOST_BASE', HEURIST_SERVER_NAME);	//this should be the main domain server name
}else{
	define('HOST_BASE', 'heuristscholar.org');	//put you default domain server name here
}
define('HEURIST_HOST_NAME', @$_SERVER["HTTP_HOST"]);	// this will read the server host name for the configured name.
$cnt = preg_match('/(\/[^\/]+)\//', @$_SERVER["SCRIPT_NAME"],$installDir);
if ($cnt ===1) {
	define('INSTALL_DIR', $installDir[1]);	//the subdir of the servers document directory where heurist is installed
}else{
	define('INSTALL_DIR', '/heurist');	//the subdir of the servers document directory where heurist is installed
}
define('HEURIST_DOCUMENT_ROOT',@$_SERVER["DOCUMENT_ROOT"]);
define('HEURIST_UPLOAD_BASEPATH','"/var/www/htdocs/uploaded-heurist-files/"');
define('HEURIST_SITE_PATH',INSTALL_DIR.'/');
define('HEURIST_URL_BASE','http://'.HEURIST_HOST_NAME.HEURIST_SITE_PATH);
define('HEURIST_FULL_INSTALLPATH',HEURIST_DOCUMENT_ROOT.HEURIST_SITE_PATH);
define('HEURIST_COMMON_DB', '`heurist-common`');	//this is the shared definitions and configuration database for the installation
define('HEURIST_DB_PREFIX', 'heuristdb-');	//database name prefix for instance
define('HEURIST_MAIL_TO_INFO', 'info@heuristscholar.org');	//mailto string for heurist installation issues
?>
