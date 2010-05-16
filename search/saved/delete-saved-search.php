<?php

require_once("../modules/cred.php");
require_once("../modules/db.php");

function jsonError($message) {
	print "{\"error\":\"" . addslashes($message) . "\"}";
	exit(0);
}

if (! is_logged_in()) {
	jsonError("no logged-in user");
}

if (! @$_REQUEST["label"]) {
	jsonError("missing argument: label");
}

mysql_connection_db_overwrite(DATABASE);

$label = @$_REQUEST["label"];
$wg = intval(@$_REQUEST["wg"]);

if ($wg > 0) {
	mysql_query("delete from saved_searches where ss_name='$label' and ss_wg_id=$wg");
} else {
	mysql_query("delete from saved_searches where ss_name='$label' and ss_usr_id=".get_user_id());
}

print "{\"deleted\":" . (mysql_affected_rows() > 0 ? "true" : "false") . "}";

?>
