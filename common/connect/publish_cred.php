<?php

require_once('db.php');

if (! $_REQUEST['pub_id'])
	die('No published search id supplied');

mysql_connection_db_select(DATABASE);
$res = mysql_query('select * from saved_searches where ss_id='.$_REQUEST['pub_id']);
if ($pub = mysql_fetch_assoc($res)) {
	// redirect to the correct script for the published search
	//if (basename($_SERVER['SCRIPT_NAME']) != $pub['pub_script'])
		//header('Location: ' . $pub['pub_script'] . '?pub_id=' . $pub['pub_id']);
	//replace request vars with those of the saved search
	//unset($_REQUEST);
	add_args_from_string(urldecode($pub['ss_query']));
	//add_args_from_string($pub['pub_args']);

	// fake login (alternative to cred.php)
	$pub_usr_id = $pub['ss_usr_id'];
	function get_user_id() { global $pub_usr_id; return $pub_usr_id; }
	function get_user_name() { return ''; }
	function get_user_username() { return ''; }
	function get_group_ids() { return array(2); }
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
