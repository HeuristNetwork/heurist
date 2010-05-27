<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../../common/t1000/t1000.php');

if (! is_logged_in()  ||  ! is_admin()  ||  HEURIST_INSTANCE != "") {
	header('Location: '.HEURIST_URL_BASE.'common/connect/login.php');
	return;
}

mysql_connection_db_overwrite("`heurist-common`");

$template = file_get_contents('edit_constrain_reftype.html');
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
