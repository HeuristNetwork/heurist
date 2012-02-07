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
require_once(dirname(__FILE__)."/../../common/php/saveRecord.php");
require_once(dirname(__FILE__)."/../../common/php/utilsTitleMask.php");


if (! is_logged_in()) {
	jsonError("no logged-in user");
}

$_REQUEST = json_decode(@$_POST["data"]?  $_POST["data"] : base64_decode(@$_GET["data"]), true);

mysql_connection_db_overwrite(DATABASE);

/* check if there are any records identified only by their hhash values */
if (!is_logged_in()) {// must be logged into save
	jsonError("invalid workgroup");
}

$nonces = array();
$retitleRecs = array();
$addRecDefaults = @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['addRecDefaults'];
if ($addRecDefaults){
	if ($addRecDefaults[0]){
		$userDefaultRectype = intval($addRecDefaults[0]);
	}
	if ($addRecDefaults[1]){
		$userDefaultOwnerGroupID = intval($addRecDefaults[1]);
	}
	if ($addRecDefaults[2]){
		$userDefaultVisibility = $addRecDefaults[2];
	}
}
/* go for the regular update/insert on all records */
$out = array("record" => array());

foreach ($_REQUEST["records"] as $nonce => $record) {
	if (! $record["id"]) {
		$wg = defined(HEURIST_NEWREC_OWNER_ID) ? HEURIST_NEWREC_OWNER_ID:get_user_id();
		if(@$record["group"]){// check membership as non-member saves are not allowed
			$res = mysql_query("select * from ".USERS_DATABASE.".sysUsrGrpLinks where ugl_UserID=" . get_user_id() . " and ugl_GroupID=" . $record["group"]);
			$wg = (mysql_num_rows($res) > 0 ? $record["group"]: get_user_id());// if not a member we save the record with user as owner
		}
		mysql__insert("Records", array("rec_AddedByUGrpID" => get_user_id(),
										"rec_OwnerUGrpID" => $wg,
										"rec_FlagTemporary" => 1,
										"rec_Added" => date('Y-m-d H:i:s')));
		if (mysql_error()) {
			array_push($out["record"], array("error" => " creating temporary record nonce = $nonce rectype = "
															.@$record["type"]." error : ".mysql_error(),
											"record" => $record,
											"nonce" => $nonce));
			$_REQUEST["records"][$nonce]["id"] = -1;
		}else{
			$id = mysql_insert_id();
			$_REQUEST["records"][$nonce]["id"] = $id;
		}
	}
	$nonces[$nonce] = $_REQUEST["records"][$nonce]["id"];
}

foreach ($_REQUEST["records"] as $nonce => $record) {
// FIXME?  should we perhaps index these by the nonce
	if ($nonces[$nonce] != -1) {
		$savedRecord =  saveRecord(@$record["id"],
									@$record["type"],
									@$record["url"],
									@$record["notes"],
									@$record["group"],
									@$record["vis"],
									@$record["bookmark"],
									@$record["pnotes"],
									@$record["rating"],
									@$record["tags"],
									@$record["wgTags"],
									@$record["detail"],
									@$record["-notify"],
									@$record["+notify"],
									@$record["-comment"],
									@$record["comment"],
									@$record["+comment"],
									$nonces,
									$retitleRecs);
		if (@$savedRecord['error']) {//there was an error so give more context info back
			$savedRecord['record'] = $record;
			$savedRecord['nonce'] = $nonce;
		}
		array_push($out["record"],$savedRecord);
	}
}
//error_log("updating titlemasks ");
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
//error_log("committing changes");


print json_format($out);
return;


function jsonError($message) {
	mysql_query("rollback");
	print "{\"error\":\"" . addslashes($message) . "\"}";
	exit(0);
}

?>
