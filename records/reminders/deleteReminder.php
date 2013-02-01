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

$r = intval(@$_REQUEST["r"]);
$u = intval(@$_REQUEST["u"]);
$e = @$_REQUEST["e"];
$h = @$_REQUEST["h"];

if (! $r) return;

if ($u) {
	if (! is_logged_in()  ||  $u != get_user_id()) {
		header("Location: " . HEURIST_BASE_URL . "common/connect/login.php?logout=1");
		return;
	}
}

mysql_connection_overwrite(DATABASE);

$res = mysql_query("select rem_Nonce from usrReminders where rem_ID = ".$r);
$row = mysql_fetch_assoc($res);
if ($h != $row["rem_Nonce"]) {
	return;
}

if ($e) {
	mysql_query("delete from usrReminders where rem_ID = ".$r." and rem_ToEmail = '".addslashes($e)."'");
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
