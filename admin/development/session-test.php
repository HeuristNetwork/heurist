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
