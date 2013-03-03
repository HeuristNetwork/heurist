<?php

/*
* Copyright (C) 2005-2013 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

?>

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
