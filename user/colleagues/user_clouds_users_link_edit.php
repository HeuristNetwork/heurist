<?php

require_once('../php/modules/cred.php');
require_once('../php/modules/db.php');
require_once('t1000.php');

if (!is_logged_in()) {
	header('Location: ../php/login.php');
	return;
}

mysql_connection_db_overwrite(DATABASE);

$user_select = "select ".USERS_ID_FIELD.",concat(".USERS_FIRSTNAME_FIELD.",' ',".USERS_LASTNAME_FIELD.") as fullname from ".USERS_DATABASE.".".USERS_TABLE." where ".USERS_FIRSTNAME_FIELD." is not null and ".USERS_LASTNAME_FIELD." is not null order by fullname asc";

$template = file_get_contents('templates/user_clouds_users_link_edit.html');
$template = str_replace("{user-select}", $user_select, $template);
$template = str_replace("{firstname-field}", USERS_FIRSTNAME_FIELD, $template);
$template = str_replace("{lastname-field}", USERS_LASTNAME_FIELD, $template);
$lexer = new Lexer($template);
$body = new BodyScope($lexer);

$body->verify();
if ($_REQUEST['_submit']) {
	$body->input_check();
	if ($body->satisfied) {
		$body->execute();
		header('Location: user_clouds_users_link_edit.php?ucl_id='.$_REQUEST['ucl_id']);
	}
}
$body->render();

?>
