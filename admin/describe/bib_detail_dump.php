<?php

require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	return;
}
if (! is_admin()) return;


mysql_connection_db_select(DATABASE);

$template = file_get_contents('bib_detail_dump.html');
$template = str_replace('{PageHeader}', '[literal]'.file_get_contents('menu.html').'[end-literal]', $template);
$lexer = new Lexer($template);

$body = new BodyScope($lexer);

$body->verify();
$body->render();

?>
