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

function jsonError($message) {
	print "{\"error\":\"" . addslashes($message) . "\"}";
	exit(0);
}

if (! is_logged_in()) {
	jsonError("no logged-in user");
}

$label = @$_REQUEST["label"];
$wg = intval(@$_REQUEST["wg"]);
$ssid = intval(@$_REQUEST["ssid"]);

if($label && $ssid){
	jsonError("missing argument (id or label) for saved search deletion");
}

mysql_connection_overwrite(DATABASE);

if($ssid>0){
	mysql_query("delete from usrSavedSearches where svs_ID=$ssid");
}else if ($wg > 0) { //OLD WAY
	mysql_query("delete from usrSavedSearches where svs_Name='$label' and svs_UGrpID=$wg");
} else {
	mysql_query("delete from usrSavedSearches where svs_Name='$label' and svs_UGrpID=".get_user_id());
}

print "{\"deleted\":" . (mysql_affected_rows() > 0 ? "true" : "false") . "}";

?>
