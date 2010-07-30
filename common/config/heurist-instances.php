<?php

require_once('heurist-ini.php');
$db = HEURIST_COMMON_DB;	//the database which holds the database configuration information
$db_prefix = HEURIST_DB_PREFIX;	//database name prefix for instance
//error_log("in h-instance");
$uploads = HEURIST_UPLOAD_BASEPATH;
$instances = array();

require_once(dirname(__FILE__).'/../connect/db.php');

mysql_connection_db_select($db);

$res = mysql_query('select * from instances');

while ($ins = mysql_fetch_assoc($res)) {
	$instances[$ins['ins_name'] ? $ins['ins_name'] : ""] = array(
		"db" => ($ins['ins_db'] ? "`" . $ins['ins_db'] . "`" : "`" . $db_prefix  . $ins['ins_name'] ."`"),
		"userdb" => ($ins['ins_userdb'] ? "`" . $ins['ins_userdb'] . "`" : "`" . $db_prefix  . $ins['ins_name'] ."`"),
		"admingroup" => ($ins['ins_admingroup'] ? $ins['ins_admingroup'] : 1),
		"uploads" => ($ins['ins_uploads'] ? $uploads ."/". $ins['ins_uploads'] :
						$ins['ins_name'] ? $uploads ."/". $ins['ins_name'] : ""),
		"explore" => ($ins['ins_explore'] ? $ins['ins_explore'] : ""),
		"usergroup" =>($ins['ins_usergroup'] ? $ins['ins_usergroup'] : "")
		);
}

function get_all_instances() {
	global $instances;
	return $instances;
}

function define_constants($instance) {
	global $instances;
//error_log("in h-instance constants");

	if (defined('H_INSTANCE_RAN')) {
	 	if ($instance != HEURIST_INSTANCE ) error_log("trying to redefine instance from '".HEURIST_INSTANCE. "' to '".$instance."'" );
//	 	error_log("Current session instance is '".$_SESSION['heurist_last_used_instance']."'");
	 	return;
	 }

	defined('HEURIST_INSTANCE') || define('HEURIST_INSTANCE', $instance);
	define('HEURIST_INSTANCE_PREFIX', $instance == "" ? "" : $instance.".");
	defined('HOST') || define('HOST', HOST_BASE);
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

	define('H_INSTANCE_RAN','1');

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

 // determine the instance name  check 1) REQUEST  2)REFER params 3) SESSION 4) defined DEFAULT 5) scrape it from the sub-domain deprecated or empty (which is the old default)

if (@$_REQUEST["instance"]) {
	$instance = $_REQUEST["instance"];
}else if (@$_SERVER["HTTP_REFERER"] && preg_match("/.*instance=([^&]*).*/",$_SERVER["HTTP_REFERER"],$refer_instance)) {
	$instance = $refer_instance[1];
}else if (@$_SESSION["heurist_last_used_instance"]) {
	$instance = $_SESSION["heurist_last_used_instance"];
}else if (defined("HEURIST_DEFAULT_INSTANCE")) {
	$instance = HEURIST_DEFAULT_INSTANCE;
}else if (preg_match("/^([0-9]+[\.])?(([-a-z]+)[\.])?" . HOST_BASE . "/", @$_SERVER['HTTP_HOST'], $matches)) { //TODO: param instance change
	$instance = (@$matches[3]? $matches[3]: "");
} else {
	$instance = '';	// CHECKME: check to see if popup frames inherit the instance or may need to pass this in client call code
}
//error_log("in h-instance = ".$instance);

if (array_key_exists(@$instance, $instances)) {
	define_constants($instance);
}else if (defined("HEURIST_DEFAULT_INSTANCE") && array_key_exists(HEURIST_DEFAULT_INSTANCE, $instances)) {
	define_constants(HEURIST_DEFAULT_INSTANCE);
	error_log("Unable to find db row for instance = " . $instance . " . Using the default instance : " .HEURIST_DEFAULT_INSTANCE );
}else if ( array_key_exists('main', $instances)) {
	define_constants('main');
	error_log("Unable to find db row for instance = " . $instance . " or default instance = ".HEURIST_DEFAULT_INSTANCE." . Using the 'main' instance " );
}else if ( array_key_exists('default', $instances)) {
	define_constants('default');
	error_log("Unable to find db row for instance = " . $instance . " or default instance = ".HEURIST_DEFAULT_INSTANCE." . Using the 'main' instance " );
}else{
	//TODO:  need to have a default set of constants in the case of the instance not in the database table or error message.
	die('can\'t load/find configuration parameters for host :'.HOST_BASE.' and instance = '. $instance);
}

	//never ever ever ever again will I leave blank lines outside the closing php tag
?>
