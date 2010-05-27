<?php
require_once('heurist-ini.php');
$db = HEURIST_COMMON_DB;	//the database which holds the database configuration information
$db_prefix = HEURIST_DB_PREFIX;	//database name prefix for instance

$uploads = HEURIST_UPLOAD_BASEPATH;
$instances = array();
$trace = "inst ";
require_once('/var/www/htdocs/h3/common/connect/db.php');	//FIXME: need to figure out why this doesn't work with relative paths

mysql_connection_db_select($db);

$res = mysql_query('select * from instances');

while ($ins = mysql_fetch_assoc($res)) {
	$instances[$ins['ins_name'] ? $ins['ins_name'] : ""] = array(
		"db" => ($ins['ins_db'] ? "`" . $ins['ins_db'] . "`" : "`" . $db_prefix  . $ins['ins_name'] ."`"),
		"userdb" => ($ins['ins_userdb'] ? "`" . $ins['ins_userdb'] . "`" : "`" . $db_prefix  . $ins['ins_name'] ."`"),
		"admingroup" => ($ins['ins_admingroup'] ? $ins['ins_admingroup'] : 1),
		"uploads" => ($ins['ins_uploads'] ? $uploads . $ins['ins_uploads'] ."/" : $uploads . $ins['ins_name'] ."/"),
		"explore" => ($ins['ins_explore'] ? $ins['ins_explore'] : ""),
		"usergroup" =>($ins['ins_usergroup'] ? $ins['ins_usergroup'] : "")
		);
}

function get_all_instances() {
	global $instances;
	return $instances;
}

function define_constants($instance) {
	global $trace;
	$trace .= " defineconst ";
	global $instances;
	defined('HEURIST_INSTANCE') || define('HEURIST_INSTANCE', $instance);
	define('HEURIST_INSTANCE_PREFIX', $instance == "" ? "" : $instance.".");
	defined('HOST') || define('HOST', HEURIST_INSTANCE_PREFIX . HOST_BASE);
	define('UPLOAD_PATH', $instances[$instance]["uploads"]);

	if (@$instances[$instance]["explore"]) {
		define('EXPLORE_URL', $instances[$instance]["explore"]);
	}
	if (@$instances[$instance]["usergroup"]) {
		define ('HEURIST_USER_GROUP_ID', $instances[$instance]["usergroup"]);
	}
	if (@$instances[$instance]["admingroup"]) {
		define ('HEURIST_ADMIN_GROUP_ID', $instances[$instance]["admingroup"]);
	}

	define('DATABASE', $instances[$instance]["db"]);
	define('USERS_DATABASE', $instances[$instance]["userdb"]);

	define('USERS_TABLE', 'Users');
	define('USERS_ID_FIELD', 'Id');
	define('USERS_USERNAME_FIELD', 'Username');
	define('USERS_PASSWORD_FIELD', 'Password');
	define('USERS_FIRSTNAME_FIELD', 'firstname');
	define('USERS_LASTNAME_FIELD', 'lastname');
	define('USERS_ACTIVE_FIELD', 'Active');
	define('USERS_EMAIL_FIELD', 'EMail');
	define('GROUPS_TABLE', 'Groups');
	define('GROUPS_ID_FIELD', 'grp_id');
	define('GROUPS_NAME_FIELD', 'grp_name');
	define('GROUPS_TYPE_FIELD', 'grp_type');
	define('USER_GROUPS_TABLE', 'UserGroups');
	define('USER_GROUPS_USER_ID_FIELD', 'ug_user_id');
	define('USER_GROUPS_GROUP_ID_FIELD', 'ug_group_id');
	define('USER_GROUPS_ROLE_FIELD', 'ug_role');
}

/* the constant HEURIST_INSTANCE might have been set, to indicate
 * which instance we're using.  Otherwise, the default behaviour:
 *
 * Inspect the hostname to determine which instance of heurist we're operating on.
 * Possible patterns are:
 *
 *         heuristscholar.org
 * (0-100).heuristscholar.org
 *
 *         [instance-name].heuristscholar.org
 * (0-100).[instance-name].heuristscholar.org
 *
 */

if (defined("HEURIST_INSTANCE")) {
	$instance = HEURIST_INSTANCE;
} else if (preg_match("/^([0-9]+[\.])?(([-a-z]+)[\.])?" . HOST_BASE . "/", @$_SERVER['HTTP_HOST'], $matches)) { //TODO: param instance change
	$instance = (@$matches[3]? $matches[3]: "");
} else {
	$trace .= "fall through ";
	return;	// CHECKME: check to see if popup frames inherit the instance or may need to pass this in client call code
}

if (array_key_exists(@$instance, $instances)) {
	define_constants($instance);
}else{
	$trace .= "todo ";
	//TODO:  need to have a default set of constants in the case of the instance not in the database table or error message.
}
	//never ever ever ever again will I leave blank lines outside the closing php tag
?>
