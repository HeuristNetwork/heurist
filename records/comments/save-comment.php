<?php

define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/db.php");
require_once(dirname(__FILE__)."/../../common/connect/cred.php");
if (! is_logged_in()) return;

mysql_connection_overwrite(DATABASE);


header("Content-type: text/javascript");

$cmt_id = intval(@$_POST["cmt_id"]);
$rec_id = intval(@$_POST["bib_id"]);
$owner = intval(@$_POST["owner"]);
if ($cmt_id) {
	$updates = array("cmt_modified" => array("now()"));
	if (@$_POST["text"])
		$updates["cmt_text"] = $_POST["text"];
	if (array_key_exists("delete", $_POST))
		$updates["cmt_deleted"] = true;

	mysql__update("comments", "cmt_id=$cmt_id and cmt_usr_id=".get_user_id(), $updates);
	if (mysql_error()) $error = mysql_error();

	$res = mysql_query("select * from comments left join ".USERS_DATABASE.".sysUGrps usr on cmt_usr_id=usr.ugr_ID where cmt_id=$cmt_id and ! cmt_deleted");
	$cmt = mysql_fetch_assoc($res);
}
else if ($rec_id) {
	$inserts = array("cmt_text" => $_POST["text"], "cmt_parent_cmt_id" => $owner, "cmt_date" => array("now()"), "cmt_usr_id" => get_user_id(), "cmt_rec_id" => $rec_id);

	mysql__insert("comments", $inserts);
	if (mysql_error()) $error = mysql_error();

	$res = mysql_query("select * from comments left join ".USERS_DATABASE.".sysUGrps usr on cmt_usr_id=usr.ugr_ID where cmt_id=".mysql_insert_id());
	$cmt = mysql_fetch_assoc($res);
}


if (@$error) {
	print "({ error: \"" . slash($error) . "\" })";
}
else if (@$cmt) {
	print "({ comment: " . json_format(array(
			"id" => $cmt["cmt_id"],
			"text" => $cmt["cmt_text"],
			"owner" => $cmt["cmt_parent_cmt_id"],
			/* don't put in dates for live-edited comments

			"added" => $cmt["cmt_date"],
			"modified" => $cmt["cmt_modified"],
			*/
			"user" => $cmt["ugr_FirstName"].' '.$cmt["ugr_LastName"],
			"userID" => $cmt["cmt_usr_id"],
			"deleted" => false
		)) . " })";
}

?>
