<?php

session_cache_limiter('no-cache');

define('RESULTS_PER_PAGE', 50);
define('SAVE_URI', 'disabled');
define('SEARCH_SET_SAVE_LIMIT', 100);

define('SEARCH_VERSION', 1);

if (array_key_exists('alt', $_REQUEST)) define('use_alt_db', 1);

require_once(dirname(__FILE__).'/../common/connect/cred.php');
require_once(dirname(__FILE__).'/../common/connect/db.php');
require_once(dirname(__FILE__).'/advanced/adv-search.php');


list($usec, $sec) = explode(' ', microtime());
$stime = $sec + $usec;

// if the user isn't logged in, never do a personals search
if (! is_logged_in())
	$_REQUEST['w'] = 'all';


if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

if (! @$_REQUEST['q']  &&  ! @$_REQUEST['s']) return;	// wwgd

if (@$_REQUEST['w'] == 'B'  ||  @$_REQUEST['w'] == 'bookmark')	// my bookmark entries
	$search_type = BOOKMARK;
else 				// all records entries
	$search_type = BOTH;


$query = 'select SQL_CALC_FOUND_ROWS
          pers_id,
          pers_usr_id,
          rec_id,
          rec_url,
          rec_type,
          rec_title,
          rec_wg_id,
          if(rec_visibility="Hidden",1,0),
          rec_url_last_verified,
          rec_url_error,
          pers_pwd_reminder ';


mysql_connection_db_select(DATABASE);

if (preg_match('/\\b_BROKEN_\\b/', $_REQUEST['q'])) {
	$broken = 1;
	$_REQUEST['q'] = preg_replace('/\\b_BROKEN_\\b/', '', $_REQUEST['q']);
}

if (preg_match('/\\b_COLLECTED_\\b/', $_REQUEST['q'])) {
	$collected = 1;
	$_REQUEST['q'] = preg_replace('/\\b_COLLECTED_\\b/', '', $_REQUEST['q']);
}

$query = REQUEST_to_query($query, $search_type);
//error_log($query);

if (@$broken) {
	$query = str_replace(' where ', ' where (to_days(now()) - to_days(rec_url_last_verified) >= 8) and ', $query);
}

if (@$collected) {
	session_start();
	$collection = &$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['record-collection'];
	if (count($collection) > 0) {
		$query = str_replace(' where ', ' where rec_id in (' . join(',', array_keys($collection)) . ') and ', $query);
	} else {
		$query = str_replace(' where ', ' where 0 and ', $query);
	}
}

// if (! array_key_exists('all', $_REQUEST)) $query .= ' limit ' . (RESULTS_PER_PAGE * 2);


list($usec, $sec) = explode(' ', microtime());
$ptime = $sec + $usec;

$res = mysql_query($query);
if (mysql_error()) error_log(mysql_error());
$fres = mysql_query('select found_rows()');
$num_rows = mysql_fetch_row($fres); $num_rows = $num_rows[0];

list($usec, $sec) = explode(' ', microtime());
$etime = $sec + $usec;

if (mysql_error()) { error_log(mysql_error()); return; }

session_start();

if ($num_rows <= SEARCH_SET_SAVE_LIMIT) {
	$sid = dechex(rand());

	if (! @$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['search-results'])
		$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['search-results'] = array();

	// limit to 5 sets of results
	while (count($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['search-results']) > 4) {
		// remove older result sets
		array_shift($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['search-results']);
	}

	$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['search-results'][$sid] = array();
}

?>
<html>
 <head>
  <!--link rel=alternate type="application/rss+xml" title=RSS href="<?= rss_url() ?>" id=rss_rel-->

  <script type="text/javascript">


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
results.notified = false;

if (top.HEURIST && top.HEURIST.firedEvents["heurist-search-html-loaded"] && top.HEURIST.firedEvents["heurist-search-js-loaded"] && top.HEURIST.firedEvents["heurist-obj-common-loaded"]) top.HEURIST.search.searchNotify(results);

<?php
	$num = 0;
	$page = 0;
	$results = array();
	$first_of_page = true;
	$reftypes = array();

	print "results.records.push(\n";
	while ($row = mysql_fetch_row($res)) {
		if (! $first_of_page) print ",\n";

		$reftypes[$row[4]] = $row[4];
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
			array_push($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['search-results'][$sid], $row[2]);
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
<?php foreach ($reftypes as $rt_id) if ($rt_id > 0) print "<img src=../common/images/reftype-icons/".$rt_id.".gif>"; ?>
 </body>
</html>
<?php

$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['last-search-url'] = @$_SERVER['HTTP_REFERER'];


function print_result($row) {
	global $num, $page;

	print "	[";
	foreach ($row as $i => $val) {
		if ($i > 0) print ',';
		print "'".str_replace("\n", '\\n', str_replace("\r", '', addslashes($val)))."'";
	}
	if (@$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['display-preferences']['search-result-style'] == "thumbs") {
		$thumb_url = "";
		// 223  Thumbnail
		// 222  Logo image
		// 224  Images
		$res = mysql_query("select files.*
		                      from rec_details
		                 left join files on file_id = rd_file_id
		                     where rd_rec_id = " . $row[2] . "
		                       and rd_type in (223,222,224,221,231)
		                       and file_mimetype like 'image%'
		                  order by rd_type = 223 desc, rd_type = 222 desc, rd_type = 224 desc, rd_type
		                     limit 1");
		if (mysql_num_rows($res) == 1) {
			$file = mysql_fetch_assoc($res);
			$thumb_url = "../common/lib/resize_image.php?file_id=".$file['file_nonce'];
		} else {
			// 603  Full image url
			$res = mysql_query("select rd_val
			                      from rec_details
			                     where rd_rec_id = " . $row[2] . "
		                           and rd_type = 603
		                         limit 1");
			if (mysql_num_rows($res) == 1) {
				$row = mysql_fetch_assoc($res);
				$thumb_url = "../common/lib/resize_image.php?file_url=".htmlspecialchars($row['rd_val']);
			}
		}
		print ",'$thumb_url'";
	}
	print "]";

	++$num;
}


function rss_url() {
	return 'feeds/search_rss.php?s='.@$_REQUEST['s'].'&w='.@$_REQUEST['w'].'&q='.urlencode(@$_REQUEST['q']);
}

?>
