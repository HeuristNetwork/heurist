<?php

require_once(dirname(__FILE__)."/../../common/connect/db.php");

function get_location($key) {
	mysql_connection_db_select("hapi");
	return mysql_fetch_assoc(mysql_query("select * from hapi_locations where hl_key='".addslashes($key)."'"));
}

?>
