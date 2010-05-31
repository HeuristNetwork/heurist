<?php

function friendlyServer($addr) {
	$patterns = array(
		"/^129\.78\.138\.\d\d?\d?$/",	// the arts domain, more or less
		"/^129\.78\.64\.10[0-6]$/",		// usyd www cache servers
	);

	foreach ($patterns as $pattern) {
		if (preg_match($pattern, $addr)) {
			return true;
		}
	}
	return false;
}

?>
