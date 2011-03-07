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

<form method=post>
<?php

if ($_COOKIE['heurist-sessionid']) session_id($_COOKIE['heurist-sessionid']);
session_start();

if ($_REQUEST['delete']) {
	foreach ($_REQUEST['delete'] as $del)
		unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'][$del]);
}

foreach ($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'] as $key => $val) {
	print '<div><input type=checkbox name="delete[]" value="' . htmlspecialchars($key) . '" id="'.htmlspecialchars($key).'"><label for="' . htmlspecialchars($key) . '">' . htmlspecialchars($key) . ': ' . substr(print_r($val, 1), 0, 30) . ' ... ' . '(' . round(strlen(print_r($val, 1)) / 1024) . 'kb)</label></div>';
}

if ($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['alt-sessionid']) {
	print '<hr>';
	print '<div><b>Alternative session</b></div>';

	session_write_close();
	session_id($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['alt-sessionid']);
	session_start();
	if ($_REQUEST['alt_delete']) {
		foreach ($_REQUEST['alt_delete'] as $del)
			unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'][$del]);
	}
	foreach ($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist'] as $key => $val) {
		print '<div><input type=checkbox name="alt_delete[]" value="' . htmlspecialchars($key) . '" id="'.htmlspecialchars($key).'"><label for="' . htmlspecialchars($key) . '">' . htmlspecialchars($key) . ': ' . substr(print_r($val, 1), 0, 30) . ' ... ' . '(' . round(strlen(print_r($val, 1)) / 1024) . 'kb)</label></div>';
	}
}


?>
<script type=text/javascript>
function select_all_alt() {
	var elts = document.getElementsByName('alt_delete[]');
	for (i in elts) elts[i].checked = true;
}
</script>
<input type="button" onclick="select_all_alt()" value="Select all alternative session variables">

<hr>
<input type="submit">
</form>
