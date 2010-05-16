<?php

require_once('../php/modules/cred.php');
require_once('t1000.php');

if (! is_logged_in()  ||  ! is_admin()  ||  HEURIST_INSTANCE != "") {
	header('Location: ' . BASE_PATH . 'php/login.php');
	return;
}

mysql_connection_db_overwrite("`heurist-common`");

$template = file_get_contents('templates/edit_constrain_reftype.html');
$lexer = new Lexer($template);

$body = new BodyScope($lexer);

$body->global_vars['close_on_load'] = '';

$body->verify();
if ($_REQUEST['_submit']) {
	$body->input_check();
	if ($body->satisfied) {
		if ($body->execute()) {
			$body->global_vars['close_on_load'] = 1;
		}
	}
}
$body->render();

?>
