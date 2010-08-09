<?php

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");
require_once(dirname(__FILE__)."/../../records/saving.php");
require_once(dirname(__FILE__)."/../../records/TitleMask.php");


if (! is_logged_in()) {
	jsonError("no logged-in user");
}

$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

mysql_connection_db_overwrite(DATABASE);

mysql_query("start transaction");

/* check if there are any records identified only by their hhash values */
$nonces = array();
$retitleRecs = array();

foreach ($_REQUEST["records"] as $nonce => $record) {
	if (! $record["id"]) {
		mysql__insert("records", array("rec_added_by_usr_id" => get_user_id(), "rec_added" => date('Y-m-d H:i:s')));
		$id = mysql_insert_id();
		$_REQUEST["records"][$nonce]["id"] = $id;
	}
	$nonces[$nonce] = $id;
}

/* go for the regular update/insert on all records */
$out = array("record" => array());
foreach ($_REQUEST["records"] as $nonce => $record) {
// FIXME?  should we perhaps index these by the nonce
	array_push($out["record"], saveRecord(@$record["id"], @$record["type"], @$record["url"], @$record["notes"], @$record["group"], @$record["vis"], @$record["bookmark"], @$record["pnotes"], @$record["crate"], @$record["irate"], @$record["qrate"], @$record["tags"], @$record["keywords"], @$record["detail"], @$record["-notify"], @$record["+notify"], @$record["-comment"], @$record["comment"], @$record["+comment"], $nonces, $retitleRecs));
}


if (count($retitleRecs) > 0) {
	foreach ( $retitleRecs as $id  ) {
		// calculate title, do an update
		$query = "select rt_title_mask, rt_id from rec_types left join records on rt_id=rec_type where rec_id = $id";
		$res = mysql_query($query);
		$mask = mysql_fetch_assoc($res);
		$type = $mask["rt_id"];
		$mask = $mask["rt_title_mask"];
		$title = fill_title_mask($mask, $id, $type);
		if ($title) {
			mysql_query("update records set rec_title = '" . addslashes($title) . "' where rec_id = $id");
		}
	}
}
mysql_query("commit");

print json_format($out);
return;


function jsonError($message) {
	mysql_query("rollback");
	print "{\"error\":\"" . addslashes($message) . "\"}";
	exit(0);
}

?>
