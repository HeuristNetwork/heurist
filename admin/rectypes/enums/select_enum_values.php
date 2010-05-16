<?php

define('SAVE_URI', 'disabled');

require_once('../php/modules/cred.php');
require_once('t1000.php');

if (! is_logged_in()  ||  ! is_admin()) {
	header('Location: ' . BASE_PATH . 'php/login.php');
	return;
}

mysql_connection_db_overwrite(DATABASE);

if (@$_REQUEST['update-active-rdls']) {
	$rdt = intval($_REQUEST['rdt_id']);
	$ardls = array_keys($_REQUEST['active_rdl']);
	mysql_query('delete active_rec_detail_lookups from rec_detail_lookups, active_rec_detail_lookups where rdl_rdt_id='.$rdt.' and ardl_id=rdl_id and ardl_id not in (' . join(',', $ardls) . ')');
	mysql_query('insert ignore into active_rec_detail_lookups values (' . join('),(', $ardls) . ')');
	header('Location: select_enum_values.php?rdt_id=' . $_REQUEST['rdt_id']);
	return;
}

$_REQUEST['_bdl_search'] = 1;
define('bdl-RESULTS_PER_PAGE', 100000);

$template = file_get_contents('templates/select_enum_values.html');
$lexer = new Lexer($template);

$body = new BodyScope($lexer);

$body->verify();
if (@$_REQUEST['_submit']) {
	$body->input_check();
	if ($body->satisfied) $body->execute();
}
$body->render();

?>
