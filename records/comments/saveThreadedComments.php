<?php

define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
if (! is_logged_in()) return;

mysql_connection_overwrite(DATABASE);


header("Content-type: text/javascript");

$cmt_id = intval(@$_POST["cmt_ID"]);
$rec_id = intval(@$_POST["bib_id"]);
$owner = intval(@$_POST["owner"]);
if ($cmt_id) {
	$updates = array("cmt_Modified" => array("now()"));
	if (@$_POST["text"])
		$updates["cmt_Text"] = $_POST["text"];
	if (array_key_exists("delete", $_POST))
		$updates["cmt_Deleted"] = true;

	mysql__update("recThreadedComments", "cmt_ID=$cmt_id and cmt_OwnerUGrpID=".get_user_id(), $updates);
	if (mysql_error()) $error = mysql_error();

	$res = mysql_query("select * from recThreadedComments left join ".USERS_DATABASE.".sysUGrps usr on cmt_OwnerUGrpID=usr.ugr_ID where cmt_ID=$cmt_id and ! cmt_Deleted");
	$cmt = mysql_fetch_assoc($res);
}
else if ($rec_id) {
	$inserts = array("cmt_Text" => $_POST["text"], "cmt_ParentCmtID" => $owner, "cmt_Added" => array("now()"), "cmt_OwnerUGrpID" => get_user_id(), "cmt_RecID" => $rec_id);

	mysql__insert("recThreadedComments", $inserts);
	if (mysql_error()) $error = mysql_error();

	$res = mysql_query("select * from recThreadedComments left join ".USERS_DATABASE.".sysUGrps usr on cmt_OwnerUGrpID=usr.ugr_ID where cmt_ID=".mysql_insert_id());
	$cmt = mysql_fetch_assoc($res);
}


if (@$error) {
	print "({ error: \"" . slash($error) . "\" })";
}
else if (@$cmt) {
	print "({ comment: " . json_format(array(
			"id" => $cmt["cmt_ID"],
			"text" => $cmt["cmt_Text"],
			"owner" => $cmt["cmt_ParentCmtID"],
			/* don't put in dates for live-edited comments

			"added" => $cmt["cmt_Added"],
			"modified" => $cmt["cmt_Modified"],
			*/
			"user" => $cmt["ugr_FirstName"].' '.$cmt["ugr_LastName"],
			"userID" => $cmt["cmt_OwnerUGrpID"],
			"deleted" => false
		)) . " })";
}

?>
