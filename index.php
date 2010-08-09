<?php
	require_once(dirname(__FILE__).'/common/config/heurist-ini.php');
//error_log("request params = ". $_SERVER["QUERY_STRING"]);
	if (@$_SERVER["QUERY_STRING"]) {
		$q = $_SERVER["QUERY_STRING"];
	}else{
		$q = "instance=".HEURIST_DEFAULT_INSTANCE;
	}
	header('Location: '.HEURIST_URL_BASE.'search/search.html?'.$q);
?>
