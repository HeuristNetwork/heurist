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
