<?php

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

$r = intval(@$_REQUEST["r"]);
$u = intval(@$_REQUEST["u"]);
$e = @$_REQUEST["e"];
$h = @$_REQUEST["h"];

if (! $r) return;

if ($u) {
	if (! is_logged_in()  ||  $u != get_user_id()) {
		header("Location: " . BASE_PATH . "common/connect/login.php?logout=1");
		return;
	}
}

mysql_connection_db_overwrite(DATABASE);

$res = mysql_query("select rem_nonce from reminders where rem_id = ".$r);
$row = mysql_fetch_assoc($res);
if ($h != $row["rem_nonce"]) {
	return;
}

if ($e) {
	mysql_query("delete from reminders where rem_id = ".$r." and rem_email = '".addslashes($e)."'");
	if (! mysql_affected_rows()) {
		return;
	}
} else if ($u) {
	mysql_query("delete from reminders where rem_id = ".$r." and rem_usr_id = '".$u."'");
	if (! mysql_affected_rows()) {
		// must be a group or colleague-group reminder - insert a blacklist entry
		mysql_query("insert into reminders_blacklist (rbl_rem_id, rbl_user_id) values (".$r.", ".$u.")");
		if (! mysql_affected_rows()) {
			return;
		}
	}
} else {
	return;
}

?>
<html>
 <body>
  <p>Reminder removed</p>
  <p><a href=..>Heurist home</a></p>
 </body>
</html>
