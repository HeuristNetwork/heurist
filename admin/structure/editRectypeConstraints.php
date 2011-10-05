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

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../../common/t1000/t1000.php');

if (!is_admin()) {
	header('Location: '.HEURIST_URL_BASE.'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

mysql_connection_db_overwrite("`heurist-common`");

$template = file_get_contents('editRectypeConstraints.html');
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
