<?php

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

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

$res = mysql_query("select rem_Nonce from usrReminders where rem_ID = ".$r);
$row = mysql_fetch_assoc($res);
if ($h != $row["rem_Nonce"]) {
	return;
}

if ($e) {
	mysql_query("delete from usrReminders where rem_ID = ".$r." and rem_Email = '".addslashes($e)."'");
	if (! mysql_affected_rows()) {
		return;
	}
} else if ($u) {
	mysql_query("delete from usrReminders where rem_ID = ".$r." and rem_ToUserID = '".$u."'");
	if (! mysql_affected_rows()) {
		// must be a group - insert a blacklist entry
		mysql_query("insert into usrRemindersBlockList (rbl_RemID, rbl_UGrpID) values (".$r.", ".$u.")");
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
