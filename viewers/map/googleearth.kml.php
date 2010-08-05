<?php
// Date in the past
header("Expires: Mon, 1 Jul 1982 00:00:00 GMT");
// always modified
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
// HTTP/1.1
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
// HTTP/1.0
header("Pragma: no-cache");
//XML Header
header("content-type:application/vnd.google-earth.kml+xml");
//header("content-type: text/xml");

/**
 * include requiered files
 */

require_once(dirname(__FILE__).'/../../common/config/heurist-instances.php');



define('SEARCH_VERSION', 1);
if( !empty($_REQUEST['pub_id'])) {
	require_once(dirname(__FILE__).'/../../common/connect/publish_cred.php');
} else {
	define("BYPASS_LOGIN", true);
	require_once(dirname(__FILE__).'/../../common/connect/cred.php');
}

require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../search/advanced/adv-search.php');
require_once('class.search.php');
require_once('class.kmlbuilder.php');

mysql_connection_db_select(DATABASE);


/**
 * format the request to a usable database search querry
 */
if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

if ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark')
	$search_type = BOOKMARK;	// my bookmarks
else
	$search_type = BOTH;	// all records

//multilevel parameter added to subvert multiple views for complex geography
if (@$_REQUEST['multilevel'] == "false"){
	$multilevel = false;
} else {
	$multilevel = true;
}

$querry = REQUEST_to_query('select distinct rec_id, rec_url, rec_scratchpad, rec_type ', $search_type);
error_log($querry);



/**
 * Create new Search and fromat the results to KML
 */

$search = new Search($querry);
$kmlbuilder = new KMLBuilder($search);
$kmlbuilder->build($multilevel);
print $kmlbuilder->getResult();



?>
