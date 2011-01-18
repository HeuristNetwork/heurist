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

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

if (! is_logged_in()  ||  ! is_admin()  ) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?instance='.HEURIST_INSTANCE);
	return;
}

mysql_connection_db_overwrite(HEURIST_COMMON_DB);

$delete_bdl_id = intval(@$_REQUEST['delete_bdl_field']);
if ($delete_bdl_id) {
	mysql_query('delete from defTerms where trm_ID = ' . $delete_bdl_id);
	header('Location: edit_enum.php?instance=[instance-name!]&amp;dty_ID=' . $_REQUEST['dty_ID']);
	return;
}

$update_bdl_id = intval(@$_REQUEST['trm_ID']);
if ($update_bdl_id) {
	$set_commands = 'set' . (@$_REQUEST['trm_Label'] ? ' trm_Label = "'. $_REQUEST['trm_Label'].'"' : '');
	$set_commands .= (@$_REQUEST[ 'trm_Description'] ? ($set_commands?',':'').' trm_Description = '. $_REQUEST['trm_Description'] : '');
	$set_commands .= (@$_REQUEST[ 'bd_rdl_ont_id_'.@$_REQUEST['trm_ID']] ? ($set_commands?',':'').' trm_VocabID = '. $_REQUEST[ 'bd_rdl_ont_id_'.@$_REQUEST['trm_ID']] : '');

	mysql_query('update defTerms '. $set_commands. ' where trm_ID = ' . $update_bdl_id);
	header('Location: edit_enum.php?instance=[instance-name!]&amp;dty_ID=' . @$_REQUEST['dty_ID'].'&updating='.$set_commands );
	return;
}

//define('T1000_DEBUG', 1);

$_REQUEST['_bdl_search'] = 1;
define('bdl-RESULTS_PER_PAGE', 100000);

$template = file_get_contents('edit_enum.html');
$lexer = new Lexer($template);

$body = new BodyScope($lexer);
$body->global_vars['dty_ID'] = @$_REQUEST['dty_ID']? $_REQUEST['dty_ID'] : 0;
$body->global_vars['instance-name'] = HEURIST_INSTANCE;

$body->verify();
if (@$_REQUEST['_new_bdl_submit'] ) {
	$body->input_check();
	if ($body->satisfied) $body->execute();
}
$body->render();

?>
