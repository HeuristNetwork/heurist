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
require_once(dirname(__FILE__)."/../../common/php/saveRecord.php.php");
require_once(dirname(__FILE__)."/../../common/php/utilsTitleMask.php");


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
		mysql__insert("Records", array("rec_AddedByUGrpID" => get_user_id(), "rec_Added" => date('Y-m-d H:i:s')));
		$id = mysql_insert_id();
		$_REQUEST["records"][$nonce]["id"] = $id;
	}
	$nonces[$nonce] = $id;
}

/* go for the regular update/insert on all records */
$out = array("record" => array());
foreach ($_REQUEST["records"] as $nonce => $record) {
// FIXME?  should we perhaps index these by the nonce
	array_push($out["record"], saveRecord(@$record["id"], @$record["type"], @$record["url"], @$record["notes"], @$record["group"], @$record["vis"], @$record["bookmark"], @$record["pnotes"], @$record["rating"], null, null, @$record["tags"], @$record["wgTags"], @$record["detail"], @$record["-notify"], @$record["+notify"], @$record["-comment"], @$record["comment"], @$record["+comment"], $nonces, $retitleRecs));
}


if (count($retitleRecs) > 0) {
	foreach ( $retitleRecs as $id  ) {
		// calculate title, do an update
		$query = "select rty_TitleMask, rty_ID from defRecTypes left join Records on rty_ID=rec_RecTypeID where rec_ID = $id";
		$res = mysql_query($query);
		$mask = mysql_fetch_assoc($res);
		$type = $mask["rty_ID"];
		$mask = $mask["rty_TitleMask"];
		$title = fill_title_mask($mask, $id, $type);
		if ($title) {
			mysql_query("update Records set rec_Title = '" . addslashes($title) . "' where rec_ID = $id");
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
