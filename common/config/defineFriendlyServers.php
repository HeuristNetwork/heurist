<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

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