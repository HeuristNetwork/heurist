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
require_once('class.rsssearch.php');
require_once('class.kmlbuilder.php');

mysql_connection_db_select(DATABASE);


/**
 *
 */
if (! @$_REQUEST['rssURL']) {
	echo " <error> You must supply an rssURL = URL to a valid rss feed </error>";
	return;
}
$url = $_REQUEST['rssURL'];
//multilevel parameter added to subvert multiple views for complex geography
if (@$_REQUEST['multilevel'] == "false"){
	$multilevel = false;
} else {
	$multilevel = true;
}

/**
 * Create new RssSearch and format the results to KML
 */

try{
	$search = new RssSearch($url);
	$kmlbuilder = new KMLBuilder($search);
	$kmlbuilder->build($multilevel,'pseudo rec_id');
	print $kmlbuilder->getResult();
} catch (Exception $e){
	echo $e->getMessage();
}

?>
