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

header('Content-type: text/plain');

session_start();
foreach ($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist'] as $i => $j) {
	if (substr($i, 0, 14) == 'heurist-import') {
		print "clearing $i\n";
		unset($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist'][$i]);
	}
}

?>
