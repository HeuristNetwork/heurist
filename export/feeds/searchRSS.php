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

header('Content-type: text/xml');
define('T1000_SUPPRESS_HIDDEN_FIELDS',1);
define('T1000_XML', 1);
//define('T1000_DEBUG', 1);
define('SAVE_URI', 'disabled');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

//define('T1000_DEFS', '.safe_htstdefs');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');

$_REQUEST['_rss_search_search'] = 1;
define('rss_search-RESULTS_PER_PAGE', 500);

mysql_connection_db_select(DATABASE);


if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

if ( $_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark') {		// my bookmark entries
	$search_type = BOOKMARK;
	$query = 'select distinct bkm_ID ';
} else if (! @$_REQUEST['w']  ||$_REQUEST['w'] == 'a'  ||  $_REQUEST['w'] == 'all') {			// all records entries
	$search_type = BOTH;
	$query = 'select distinct rec_ID ';
} else {
	return;	// wwgd
}
if (@$where){
	$query = REQUEST_to_query($query, $search_type,NULL,"0");
} else {
	$query = REQUEST_to_query($query, $search_type);
}
if (preg_match('/.* order by (.*)/', $query, $matches)) {
	$order_col = $matches[1];
	if ($search_type == BOTH  ||  ! preg_match('/bib_/', $order_col)) {
		define('rss_search-order', $order_col);
	}
	$query = substr($query, 0, strlen($query) - strlen(' order by ' . $matches[1]));
}


// hack!  Instead of stupidly searching the useless usrBookmarks (bookmarks) table, give us rec_ids instead
$query = str_replace("select distinct bkm_ID from", "select distinct rec_ID from", $query);
$SEARCHES['rss_search'] = $query;
/*****DEBUG****///error_log("query = ".$query);

$template = file_get_contents('searchRSSTemplate.xml');



$lexer = new Lexer($template);

$body = new BodyScope($lexer);

$body->global_vars['hBase'] = HEURIST_BASE_URL;
$body->global_vars['dbname'] = HEURIST_DBNAME;

if ($search_type == BOOKMARK) $body->global_vars['search-type'] = 'bookmark';
else $body->global_vars['search-type'] = 'biblio';

$body->verify();
$body->render();

?>
