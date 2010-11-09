<?php

define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/db.php");
require_once(dirname(__FILE__)."/../../common/connect/cred.php");
if (! is_logged_in()) return;

mysql_connection_overwrite(DATABASE);


header("Content-type: text/javascript");

$rec_id = intval(@$_POST["bib_id"]);
$actions = $_POST["action"];
if ($rec_id  &&  $actions) {
	$deletions = array();
	$additions = array();

	foreach ($actions as $action) {
		$kwd_id = intval(substr($action, 4));
		if( ! $kwd_id) continue;

		if (substr($action, 0, 3) == "del") {
			$deletions[$kwd_id] = $kwd_id;
			unset($additions[$kwd_id]);
		}
		else if (substr($action, 0, 3) == "add") {
			$additions[$kwd_id] = $kwd_id;
			unset($deletions[$kwd_id]);
		}
	}

	if (count($deletions) > 0)
		mysql_query("delete from keyword_links where kwl_rec_id=$rec_id and kwl_kwd_id in (" . join($deletions,",") . ")");
	if (count($additions) > 0)
		mysql_query("insert into keyword_links (kwl_kwd_id, kwl_rec_id) values (" . join(",$rec_id), (", $additions) . ",$rec_id)");


	$res = mysql_query("select kwd_id from keyword_links, usrTags where kwl_kwd_id=kwd_id and kwl_rec_id=$rec_id");
	$kwd_ids = array();
	while ($row = mysql_fetch_row($res)) array_push($kwd_ids, $row[0]);

	print "(" . json_format($kwd_ids) . ")";
}

?>
