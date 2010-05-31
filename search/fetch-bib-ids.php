<?php

session_cache_limiter('no-cache');

define('SAVE_URI', 'disabled');

define('SEARCH_VERSION', 1);

if (array_key_exists('alt', $_REQUEST)) define('use_alt_db', 1);

require_once(dirname(__FILE__).'/../common/connect/cred.php');
require_once(dirname(__FILE__).'/../common/connect/db.php');
require_once(dirname(__FILE__).'/../search/advanced/adv-search.php');

// if the user isn't logged in, never do a personals search
if (! is_logged_in())
	$_REQUEST['w'] = 'all';


if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

if (! @$_REQUEST['q']  &&  ! @$_REQUEST['s']) return;	// wwgd

if ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark')
	$search_type = BOOKMARK;	// my bookmarks
else
	$search_type = BOTH;	// all records


mysql_connection_db_select(DATABASE);

if (preg_match('/\\b_BROKEN_\\b/', $_REQUEST['q'])) {
	$broken = 1;
	$_REQUEST['q'] = preg_replace('/\\b_BROKEN_\\b/', '', $_REQUEST['q']);
}

$query = REQUEST_to_query("select rec_id, pers_id ", $search_type);

if (@$broken) {
	$query = str_replace(' where ', ' where (to_days(now()) - to_days(rec_url_last_verified) >= 8) and ', $query);
}

$res = mysql_query($query);
$ids = array();
while ($row = mysql_fetch_assoc($res)) {
	array_push($ids, array("bib_id" => $row["rec_id"], "bkmk_id" => $row["pers_id"]));
}

print json_format($ids);

?>
