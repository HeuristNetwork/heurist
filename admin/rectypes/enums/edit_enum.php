<?php

define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../../common/t1000/t1000.php');

if (! is_logged_in()  ||  ! is_admin()  ||  HEURIST_INSTANCE != "") {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	return;
}

mysql_connection_db_overwrite(HEURIST_COMMON_DB);

$delete_bdl_id = intval(@$_REQUEST['delete_bdl_field']);
if ($delete_bdl_id) {
	mysql_query('delete from rec_detail_lookups where rdl_id = ' . $delete_bdl_id);
	header('Location: edit_enum.php?dty_ID=' . $_REQUEST['dty_ID']);
	return;
}

$update_bdl_id = intval(@$_REQUEST['rdl_id']);
if ($update_bdl_id) {
	$set_commands = 'set' . (@$_REQUEST['rdl_value'] ? ' rdl_value = "'. $_REQUEST['rdl_value'].'"' : '');
	$set_commands .= (@$_REQUEST[ 'rdl_description'] ? ($set_commands?',':'').' rdl_description = '. $_REQUEST['rdl_description'] : '');
	$set_commands .= (@$_REQUEST[ 'bd_rdl_ont_id_'.@$_REQUEST['rdl_id']] ? ($set_commands?',':'').' rdl_ont_id = '. $_REQUEST[ 'bd_rdl_ont_id_'.@$_REQUEST['rdl_id']] : '');

	mysql_query('update rec_detail_lookups '. $set_commands. ' where rdl_id = ' . $update_bdl_id);
	header('Location: edit_enum.php?dty_ID=' . @$_REQUEST['dty_ID'].'&updating='.$set_commands );
	return;
}

//define('T1000_DEBUG', 1);

$_REQUEST['_bdl_search'] = 1;
define('bdl-RESULTS_PER_PAGE', 100000);

$template = file_get_contents('edit_enum.html');
$lexer = new Lexer($template);

$body = new BodyScope($lexer);
$body->global_vars['dty_ID'] = @$_REQUEST['dty_ID']? $_REQUEST['dty_ID'] : 0;

$body->verify();
if (@$_REQUEST['_new_bdl_submit'] ) {
	$body->input_check();
	if ($body->satisfied) $body->execute();
}
$body->render();

?>
