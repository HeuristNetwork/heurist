<?php

$instances = array(
	"" => array(
		"db" => "`heuristdb`",
		"userdb" => "ACLAdmin",
		"usergroup" => 2,
		"admingroup" => 2,
		"uploads" => "/var/www/htdocs/uploaded-files/"),
	"nyirti" => array(
		"db" => "`heuristdb-nyirti`",
		"userdb" => "`heuristdb-nyirti`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-nyirti/"),
	"hayes" => array(
		"db" => "`heuristdb-hayes`",
		"userdb" => "`heuristdb-hayes`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-hayes/"),
	"london-higher" => array(
		"db" => "`heuristdb-london-higher`",
		"userdb" => "`heuristdb-london-higher`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-london-higher/"),
	"london-higher-b" => array(
		"db" => "`heuristdb-london-higher-b`",
		"userdb" => "`heuristdb-london-higher-b`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-london-higher-b/"),
	"kj" => array(
		"db" => "`heuristdb-kj`",
		"userdb" => "`heuristdb-kj`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-kj/"),
	"timelines" => array(
		"db" => "`heuristdb-timelines`",
		"userdb" => "`heuristdb-timelines`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-timelines/"),
    "shmaria" => array(
		"db" => "`heuristdb-shmaria`",
		"userdb" => "`heuristdb-shmaria`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-shmaria/"),
	"paradisec" => array(
		"db" => "`heuristdb-paradisec`",
		"userdb" => "`heuristdb-paradisec`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-paradisec/"),
    "swhite" => array(
		"db" => "`heuristdb-swhite`",
		"userdb" => "`heuristdb-swhite`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-swhite/"),
	"artsdigital" => array(
		"db" => "`heuristdb-artsdigital`",
		"userdb" => "`heuristdb-artsdigital`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-artsdigital/"),
	"gallipoli" => array(
		"db" => "`heuristdb-gallipoli`",
		"userdb" => "`heuristdb-gallipoli`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-gallipoli/"),
	"dos" => array(
		"db" => "`heuristdb-dos`",
		"userdb" => "`heuristdb-dos`",
		"admingroup" => 1,
		"uploads" => "/var/www/htdocs/uploaded-files-dos/")
);

function get_all_instances() {
	global $instances;
	return $instances;
}

function define_constants($instance) {
	global $instances;

	define('HEURIST_INSTANCE', $instance);
	define('HEURIST_INSTANCE_PREFIX', $instance == "" ? "" : $instance.".");

	define('UPLOAD_PATH', $instances[$instance]["uploads"]);

	if (@$instances[$instance]["usergroup"])
		define ('HEURIST_USER_GROUP_ID', $instances[$instance]["usergroup"]);
	if (@$instances[$instance]["admingroup"])
		define ('HEURIST_ADMIN_GROUP_ID', $instances[$instance]["admingroup"]);

	define('DATABASE', $instances[$instance]["db"]);
	define('USERS_DATABASE', $instances[$instance]["userdb"]);

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
}

/* Inspect the hostname to determine which instance of heurist we're operating on.
 * Possible patterns are:
 * 
 *         heuristscholar.org
 * (0-100).heuristscholar.org
 * 
 *         [instance-name].heuristscholar.org
 * (0-100).[instance-name].heuristscholar.org
 *
 */

if (preg_match("/^([0-9]+[.])?(([-a-z]+)[.])?heuristscholar.org/", @$_SERVER['HTTP_HOST'], $matches)) {
	$instance = (@$matches[3]? $matches[3]: "");
} else {
	return;
}

if (array_key_exists(@$instance, $instances)) {
	define_constants($instance);
}

?>
