<?php

header('Content-type: text/xml');
define('T1000_SUPPRESS_HIDDEN_FIELDS',1);
define('T1000_XML', 1);
//define('T1000_DEBUG', 1);
define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../../common/connect/cred.php');

if (!is_logged_in()) {
        header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
        return;
}


//define('T1000_DEFS', '.safe_htstdefs');
require_once('t1000.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../search/advanced/adv-search.php');

$_REQUEST['_rss_search_search'] = 1;
define('rss_search-RESULTS_PER_PAGE', 500);

mysql_connection_db_select(DATABASE);


if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
	construct_legacy_search();	// migration path

if (! @$_REQUEST['w']  ||  $_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark') {		// my bookmark entries
	$search_type = BOOKMARK;
	$query = 'select distinct pers_id ';
} else if ($_REQUEST['w'] == 'a'  ||  $_REQUEST['w'] == 'all') {			// all records entries
	$search_type = BOTH;
	$query = 'select distinct rec_id ';
} else {
	return;	// wwgd
}

$query = REQUEST_to_query($query, $search_type);
if (preg_match('/.* order by (.*)/', $query, $matches)) {
	$order_col = $matches[1];
	if ($search_type == BOTH  ||  ! preg_match('/bib_/', $order_col)) {
		define('rss_search-order', $order_col);
	}
	$query = substr($query, 0, strlen($query) - strlen(' order by ' . $matches[1]));
}


// hack!  Instead of stupidly searching the useless personals (bookmarks) table, give us rec_ids instead
$query = str_replace("select distinct pers_id from", "select distinct rec_id from", $query);
$SEARCHES['rss_search'] = $query;

$template = file_get_contents('templates/search_rss.xml');



$lexer = new Lexer($template);

$body = new BodyScope($lexer);

if ($search_type == BOOKMARK) $body->global_vars['search-type'] = 'bookmark';
else $body->global_vars['search-type'] = 'biblio';

$body->verify();
$body->render();

?>
