<?php

if ($_COOKIE['heurist-sessionid']) session_id($_COOKIE['heurist-sessionid']);
session_start();

if (array_key_exists('alt', $_REQUEST)) {
	session_write_close();
	session_id($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['alt-sessionid']);
	session_start();
}
	print '<pre>';
	print htmlspecialchars(print_r($_SESSION, 1));
	print '</pre>';
print '<a href="?alt">go to alt, man</a>';
?>
