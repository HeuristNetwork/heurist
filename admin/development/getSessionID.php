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

if ($_COOKIE['test_sessionid']) {
        session_id($_COOKIE['test_sessionid']);
}

session_start();
if (! $_COOKIE['test_sessionid']) {
	setcookie('test_sessionid', session_id());
}

print 'currently in session ' . session_id() . '<br>';


function jump_sessions() {
	$alt_sessionid = $_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['alt-sessionid'];
	if (! $alt_sessionid) {
		$alt_sessionid = $_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['alt-sessionid'] = sha1('import:' . rand());
	}

	session_write_close();

	session_id($alt_sessionid);
	session_start();
}

?>
