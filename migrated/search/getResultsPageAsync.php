<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


session_cache_limiter('no-cache');

define('RESULTS_PER_PAGE', 50);
define('SAVE_URI', 'disabled');
define('SEARCH_SET_SAVE_LIMIT', 100);

define('SEARCH_VERSION', 1);

if (array_key_exists('alt', $_REQUEST)) define('use_alt_db', 1);

require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/parseQueryToSQL.php');
require_once(dirname(__FILE__).'/../records/files/uploadFile.php');
require_once(dirname(__FILE__).'/../admin/setup/dbproperties/getCurrentVersion.php');

mysql_connection_overwrite(DATABASE);

//remove any tempory records more that a week old since
//it's possible that someone leaves the edit page up for a while before saving.
mysql_query("delete from Records where rec_FlagTemporary = 1 and rec_Modified < date_sub(now(), interval 1 week)");

list($usec, $sec) = explode(' ', microtime());
$stime = $sec + $usec;//start time

// if the user isn't logged in, never do a usrBookmarks search
if (! is_logged_in())
	$_REQUEST['w'] = 'all';


if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

if (! @$_REQUEST['q']  &&  ! @$_REQUEST['s']) return;	// wwgd

if (@$_REQUEST['w'] == 'B'  ||  @$_REQUEST['w'] == 'bookmark')	// my bookmark entries
	$search_type = BOOKMARK;
else 				// all records entries
	$search_type = BOTH;


$query = 'select SQL_CALC_FOUND_ROWS '
		.'bkm_ID,'
		.'bkm_UGrpID,'
		.'rec_ID,'
		.'rec_URL,'
		.'rec_RecTypeID,'
		.'rec_Title,'
		.'rec_OwnerUGrpID,'
		.'rec_NonOwnerVisibility,'
		.'rec_URLLastVerified,'
		.'rec_URLErrorMessage,'
		.'bkm_PwdReminder ';



if (preg_match('/\\b_BROKEN_\\b/', $_REQUEST['q'])) {
	$broken = 1;
	$_REQUEST['q'] = preg_replace('/\\b_BROKEN_\\b/', '', $_REQUEST['q']);
}

if (preg_match('/\\b_COLLECTED_\\b/', $_REQUEST['q'])) {
	$collected = 1;
	$_REQUEST['q'] = preg_replace('/\\b_COLLECTED_\\b/', '', $_REQUEST['q']);
}

$query = REQUEST_to_query($query, $search_type);

if (@$broken) {
	$query = str_replace(' where ', ' where (to_days(now()) - to_days(rec_URLLastVerified) >= 8) and ', $query);
}

if (@$collected) {
	session_start();
	$collection = &$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['record-collection'];
	if (count($collection) > 0) {
		$query = str_replace(' where ', ' where rec_ID in (' . join(',', array_keys($collection)) . ') and ', $query);
	} else {
		$query = str_replace(' where ', ' where 0 and ', $query);
	}
}

// if (! array_key_exists('all', $_REQUEST)) $query .= ' limit ' . (RESULTS_PER_PAGE * 2);


list($usec, $sec) = explode(' ', microtime());
$ptime = $sec + $usec;//parse time
/*****DEBUG****///
//error_log(print_r($_REQUEST, true));        
//error_log("query from asynch ".print_r($query,true));

$res = mysql_query($query);
if (mysql_error()) {
	error_log("queryError in getResultsPageAsync -".mysql_error());
}
$fres = mysql_query('select found_rows()');
$num_rows = mysql_fetch_row($fres); $num_rows = $num_rows[0];

list($usec, $sec) = explode(' ', microtime());
$etime = $sec + $usec;// execusion time

if (mysql_error()) {
	error_log(mysql_error());
	return;
}

session_start();

//update top.HEURIST.user.workgroups
$isNeedUpdateWorkgroups = false;
if(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access_updated']==1){
	$isNeedUpdateWorkgroups = true;
	unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access_updated']);
}

if ($num_rows <= SEARCH_SET_SAVE_LIMIT) {
	$sid = dechex(rand());
// set up search context for use with edit records prev next
	if (! @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'])
		$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'] = array();

	// limit to 5 sets of results
	while (count($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results']) > 4) {
		// remove older result sets
		array_shift($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results']);
	}

	$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid] = array(
																	"infoByDepth"=>array(array( "count"=>0,
																								"recIDs"=>array(),
																								"rectypes"=>array())),
																	"recSet"=> array()
																	);
}
$resDepth = 0; // the result records depth with respect to the query

$current_stable_version = "";
if (false && HEURIST_INDEX_BASE_URL!=HEURIST_BASE_URL){

    $current_stable_version = checkVersionOnMainServer(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['current_stable_version']);

    if($current_stable_version && $current_stable_version!=@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['current_stable_version']){
            $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['current_stable_version'] = $current_stable_version;
    }
}

?>
<html>
 <head>
  <!--link rel=alternate type="application/rss+xml" title=RSS href="<?= rss_url() ?>" id=rss_rel-->
  <!--
 <?=$query?>
   -->
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <script type="text/javascript">

<?php 
    ob_start();
    if($isNeedUpdateWorkgroups){ 
?>
top.HEURIST.user.workgroups = [<?php
		if (is_array(@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'])) {
			$query = "grp.ugr_ID in (".join(",", array_keys($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['user_access'])).") and grp.ugr_Type !='user' order by grp.ugr_Name";

			/*****DEBUG****///error_log(">>>>>>>>>>>> PREFIX=".HEURIST_SESSION_DB_PREFIX."   ".$query);
			$workgroups = mysql__select_array(USERS_DATABASE.".sysUGrps grp", "grp.ugr_ID", $query);
			print join(", ", $workgroups);
		}
?> ];
<?php } ?>


/* prevent JS errors when inspecting the page explicitly */
window.HEURIST = {
	firedEvents: [],
	search: {
		searchNotify: function() { },
		searchResultsNotify: function() { }
	}
};

var results = {};
results.sid = "<?= @$sid ?>";
results.records = [];
results.totalRecordCount = "<?= $num_rows ?>";
results.current_stable_version = "<?=$current_stable_version ?>";
results.notified = false;

if (top.HEURIST && top.HEURIST.firedEvents["heurist-search-html-loaded"] && top.HEURIST.firedEvents["heurist-search-js-loaded"] && top.HEURIST.firedEvents["heurist-obj-common-loaded"]) top.HEURIST.search.searchNotify(results);

<?php
	$num = 0;
	$page = 0;
	$results = array();
	$first_of_page = true;
	$rectypes = array();

	print "results.records.push(\n";
    if($res){
	while ($row = mysql_fetch_row($res)) {
		if (! $first_of_page) print ",\n";

		$rectypes[$row[4]] = $row[4];
		print_result($row);

		$first_of_page = false;

		if ($num % RESULTS_PER_PAGE == 0) {
			print "\n);\n";
			print "if (top.HEURIST && top.HEURIST.firedEvents[\"heurist-search-html-loaded\"] && top.HEURIST.firedEvents[\"heurist-search-js-loaded\"] && top.HEURIST.firedEvents[\"heurist-obj-common-loaded\"]) top.HEURIST.search.searchResultsNotify(results, " . ($page*RESULTS_PER_PAGE) . ");\n";
			print "</script>\n";
			ob_flush();

			print "  <script type=\"text/javascript\">\n";
			print "results.records.push(\n";

			++$page;
			$first_of_page = true;
		}

		array_push($results, $row);
		if ($num_rows <= SEARCH_SET_SAVE_LIMIT) {
			array_push($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]["infoByDepth"][0]["recIDs"], $row[2]);
			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]["infoByDepth"][0]["count"]++;
			if (!@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]["recSet"][$row[2]]){
				$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]["recSet"][$row[2]] = array("depth" => $resDepth,
																												"record" => $row);
		if (!@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]['infoByDepth'][0]['rectypes'][$row[4]]) {
			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]['infoByDepth'][0]['rectypes'][$row[4]] = array($row[2]);
		} else if ( !in_array($row[2],$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]['infoByDepth'][0]['rectypes'][$row[4]])){
			array_push($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['search-results'][$sid]['infoByDepth'][0]['rectypes'][$row[4]],$row[2]);
		}

			}
		}
	}
    }
	print ");\n";

	if ($num % RESULTS_PER_PAGE) {
		print "if (top.HEURIST && top.HEURIST.firedEvents[\"heurist-search-html-loaded\"] && top.HEURIST.firedEvents[\"heurist-search-js-loaded\"] && top.HEURIST.firedEvents[\"heurist-obj-common-loaded\"]) top.HEURIST.search.searchResultsNotify(results, " . ($page*RESULTS_PER_PAGE) . ");\n";
	}
	ob_flush();
?>
  </script>
 </head>

<?php print '<!-- ' . $_REQUEST['q'] . ' -->'; ?>
<?php
	print '<!-- ';
	print 'parse time: ' . ($ptime - $stime)*1000 . "ms\n";
	print 'query time: ' . ($etime - $ptime)*1000 . "ms\n";
	print ' -->';
?>
 <body>
<?php foreach ($rectypes as $rt_id) if ($rt_id > 0) print "<img src=".HEURIST_ICON_URL.$rt_id.".png>"; ?>
 </body>
</html>
<?php

$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['last-search-url'] = @$_SERVER['HTTP_REFERER'];


function print_result($row) {
	global $num;

	print "	[";
	foreach ($row as $i => $val) {
		if ($i > 0) print ',';
		print "'".str_replace("\n", '\\n', str_replace("\r", '', addslashes($val)))."'";
	}

	$thumb_url = getThumbnailURL($row[2]);

	print ",'$thumb_url'";
	print "]";

	++$num;
}

function rss_url() {
	return HEURIST_BASE_URL.'export/feeds/searchRSS.php?s='.@$_REQUEST['s'].'&w='.@$_REQUEST['w'].'&q='.urlencode(@$_REQUEST['q']);
}

?>
