<?php

/*<!--
 * File: admin_menu.php - menu for administration functions Ian Johnson 11 Oct 2010
 * @copyright 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../common/t1000/t1000.php');


if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	return;
}

// we will need this test on individual menu entries in the html template
// if (is_admin()) {
//	}

$body->global_vars['instance-name'] = HEURIST_INSTANCE;

$template = file_get_contents('adminMenu.html');
$template = str_replace('{PageHeader}', '[literal]'.file_get_contents(dirname(__FILE__).'/../common/html/simpleHeader.html').'[end-literal]', $template);
$lexer = new Lexer($template);

$body = new BodyScope($lexer);
$body->global_vars['instance-name'] = HEURIST_INSTANCE;

$body->verify();

$body->render();

?>
