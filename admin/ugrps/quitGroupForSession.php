<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

if (! is_logged_in()) {
	header("Location: " . HEURIST_URL_BASE . "common/connect/login.php?db=".HEURIST_DBNAME);
	return;
}
if (! is_admin()) {
		 print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body><div class=wrap><div id=errorMsg><span>You do not have sufficient privileges to access this page</span><p><a href=".HEURIST_URL_BASE."common/connect/login.php?logout=1&amp;db=".HEURIST_DBNAME." target='_top'>Log out</a></p></div></div></body></html>";
		return;
	}

if ($_COOKIE['heurist-sessionid']) session_id($_COOKIE['heurist-sessionid']);
session_start();

if (@$_REQUEST["a"]) {
	$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["user_access"][$_REQUEST["a"]] = "user";
	session_write_close();
}
if (@$_REQUEST["g"]) {
	unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["user_access"][$_REQUEST["g"]]);
	session_write_close();
}

?>
<html>
 <head>
  <title>Modify group/admin status for session</title>
 </head>
 <body>
  <p>This page allows you to exit a group, or relinquish administrator status for a group, for the rest of your session.  Changes will not be saved to the database.  Log out and log back in to restore your normal permissions.</p>

  <table>
<?php

mysql_connection_db_select(USERS_DATABASE);

$grp_ids = array_keys($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["user_access"]);
$grp_names = mysql__select_assoc(GROUPS_TABLE, GROUPS_ID_FIELD, GROUPS_NAME_FIELD, GROUPS_ID_FIELD." in (".join(",",$grp_ids).")");

foreach ($grp_ids as $grp_id) {
	print("<tr><td>".$grp_names[$grp_id]."</td><td>");
	if ($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["user_access"][$grp_id] == "admin") {
		print("<a href=?a=$grp_id>-admin</a>");
	}
	print("</td><td><a href=?g=$grp_id>exit</td></tr>");
}
?>

 </body>
</html>

