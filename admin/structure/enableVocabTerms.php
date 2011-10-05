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

define('SAVE_URI', 'disabled');
define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.phps');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

if (!is_admin()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

mysql_connection_db_overwrite(DATABASE);

/* saw removed 2010/12/1
if (@$_REQUEST['update-active-rdls']) {
	$rdt = intval($_REQUEST['dty_ID']);
	$ardls = array_keys($_REQUEST['active_trm']);
//	mysql_query('delete active_rec_detail_lookups from defTerms, active_rec_detail_lookups where trm_VocabID='.$rdt.' and ardl_id=trm_ID and ardl_id not in (' . join(',', $ardls) . ')');
//	mysql_query('insert ignore into active_rec_detail_lookups values (' . join('),(', $ardls) . ')');
	header('Location: select_enum_values.php?dty_ID=' . $_REQUEST['dty_ID']);
	return;
}
*/
$_REQUEST['_bdl_search'] = 1;
define('bdl-RESULTS_PER_PAGE', 100000);

$template = file_get_contents('enableVocabTerms.html');
$lexer = new Lexer($template);

$body = new BodyScope($lexer);
$body->global_vars['dbname'] = HEURIST_DBNAME;

$body->verify();
if (@$_REQUEST['_submit']) {
	$body->input_check();
	if ($body->satisfied) $body->execute();
}
$body->render();

?>
