<?php

/* Search for records entries matching the given standard query string in $_REQUEST['q']
 * The returned fields include rec_id, rec_title, rec_url and rec_type.
 * If $_REQUEST['l'] is supplied, it is used as the maximum number of results to return,
 *  (or defaults to 50)
 * If $_REQUEST['r'] is "recent", then matches are restricted to records records viewed
 *  or edited by the user
 * If $_REQUEST['searchID'] is present, it is used as a search notification ID;
 *  this defaults to "search";
 * Results are stored in (object) top.HEURIST.results[ searchID ]:
 *  - colNames is an array of the field names
 *  - records is an array, with each entry being an array corresponding to one matching record
 * Once the results are finished, the window.HEURIST.notifyResults( searchID ) is called.
 */

define("RESULT_LIMIT", 50);
define("SAVE_URI", "disabled");

define("SEARCH_VERSION", 1);


// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);


require_once("modules/cred.php");
require_once("modules/db.php");
require_once('modules/adv-search.php');

mysql_connection_db_select(DATABASE);

if (! array_key_exists('q', @$_REQUEST)  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

// if (! @$_REQUEST['q']) return;	// wwgd

$limit = RESULT_LIMIT;
if (intval(@$_REQUEST["l"]) > 0) $limit = intval($_REQUEST["l"]);

$searchID = "search";
if (@$_REQUEST["searchID"]) $searchID = $_REQUEST["searchID"];

$colNames = array("rec_id", "rec_title", "rec_url", "rec_type");
$query = REQUEST_to_query("select " . join(", ", $colNames) . " ", BOTH);

if (@$_REQUEST["r"] == "recent") {
	$query = preg_replace("/\\swhere\\s/", " where (TOPBIBLIO.rec_id in (select distinct ruse_rec_id from recently_used_records where ruse_usr_id = " . get_user_id() . ")) and ", $query);
	// can probably FIXME: do we want the sort order to reflect how recently the user has seen each record?
	$query = preg_replace("/(.*)\\sorder by.*/", "$1 order by (select -max(ruse_id) from recently_used_records where ruse_usr_id=" . get_user_id() . " and ruse_rec_id=TOPBIBLIO.rec_id)", $query);
}

$query .= " limit $limit";


header("Content-type: text/javascript");

?>
({
	"searchID": "<?= slash($searchID) ?>",
	"colNames": [ "<?= join("\", \"", $colNames) ?>" ],

	"records": [
<?php
	$res = mysql_query($query);
	$first = true;
	while ($row = mysql_fetch_row($res)) {
		if (! $first) print ",\n"; $first = false;
		print "[ \"" . join("\", \"", array_map("slash", $row)) . "\" ]";
	}
?>
	]
})
