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

require_once('../php/dbMySqlWrappers.php');

if (! $_REQUEST['pub_id'])
	die('No published search id supplied');

mysql_connection_db_select(DATABASE);
$res = mysql_query('select * from usrSavedSearches where svs_ID='.$_REQUEST['pub_id']);
if ($pub = mysql_fetch_assoc($res)) {
	// redirect to the correct script for the published search
	//if (basename($_SERVER['SCRIPT_NAME']) != $pub['pub_script'])
		//header('Location: ' . $pub['pub_script'] . '?pub_id=' . $pub['pub_id']);
	//replace request vars with those of the saved search
	//unset($_REQUEST);
	add_args_from_string(urldecode($pub['svs_Query']));
	//add_args_from_string($pub['pub_args']);

	// fake login (alternative to cred.php)
	$pub_usr_id = $pub['svs_UGrpID'];
	function get_user_id() { global $pub_usr_id; return $pub_usr_id; }
	function get_user_name() { return ''; }
	function get_user_username() { return ''; }
	function get_group_ids() { return array(1); }
	function is_admin() { return false; }
	function is_logged_in() { return true; }

} else {
	die('No published search found with id ' . $_REQUEST['pub_id']);
}


function add_args_from_string ($argstring) {
	if (preg_match('/^[?]/', $argstring)) {
		$argstring = explode('?', $argstring);
		$argstring = $argstring[1];
	}
	foreach (explode('&', $argstring) as $arg) {
        preg_match('/([^=]+)=(.*)/',$arg,$matches); 
        if ($matches[1]) $_REQUEST[$matches[1]] = $matches[2];
	}
}

?>
