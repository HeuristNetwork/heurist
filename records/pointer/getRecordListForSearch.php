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

/* Search for records entries matching the given standard query string in $_REQUEST['q']
 * The returned fields include rec_ID, rec_Title, rec_URL and rec_RecTypeID.
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

define("RESULT_LIMIT", 100);
define('ISSERVICE',1);
define("SAVE_URI", "disabled");

define("SEARCH_VERSION", 1);


// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');

mysql_connection_select(DATABASE);

if (! array_key_exists('q', @$_REQUEST)  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

// if (! @$_REQUEST['q']) return;	// wwgd

$limit = RESULT_LIMIT;
if (intval(@$_REQUEST["l"]) > 0) $limit = intval($_REQUEST["l"]);

$searchID = "search";
if (@$_REQUEST["searchID"]) $searchID = $_REQUEST["searchID"];

$colNames = array("rec_ID", "rec_Title", "rec_URL", "rec_RecTypeID");
$query = REQUEST_to_query("select " . join(", ", $colNames) . " ", BOTH);
/*****DEBUG****///error_log("Rec List query = ".$query);
if (@$_REQUEST["r"] == "recent") {
	$query = preg_replace("/\\swhere\\s/", " where (TOPBIBLIO.rec_ID in (select distinct rre_RecID from usrRecentRecords where rre_UGrpID = " . get_user_id() . ")) and ", $query);
	// saw CHECK ME: this code assumes order by is last clause of query
	$query = preg_replace("/(.*)\\sorder by.*/", "$1 order by TOPBIBLIO.rec_Modified desc", $query);
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
