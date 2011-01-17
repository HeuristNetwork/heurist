<?php

/**
 * File: admin_menu.php - menu for administration functions Ian Johnson 11 Oct 2010
 * @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

??? Needs to include admin_menu.html and set the value for the current database to be used in menu calls

require_once(dirname(__FILE__).'/../common/connect/cred.php');
require_once(dirname(__FILE__).'/../common/t1000/t1000.php');


if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . '/../common/connect/login.php');
	return;
}
if (! is_admin()) {
	print "<html><body><p>You do not have sufficient privileges to access this page</p><p><a href=".HEURIST_URL_BASE.">Return to Heurist</a></p></body></html>";
	return;
}

$body->global_vars['instance-name'] = HEURIST_INSTANCE;

$template = file_get_contents('admin_menu.html');
$lexer = new Lexer($template);

$body = new BodyScope($lexer);
$body->global_vars['instance-name'] = HEURIST_INSTANCE;

$body->verify();
$body->render();

?>
