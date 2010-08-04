<?php
/* code for finding similar URLs */

function exist_similar($url) {
	/* are there similar URLs to this one already? */

	$noproto_url = preg_replace('!^http://(?:www[.])?([^/]*).*!', '\1', $url);	// URL minus the protocol + possibly www.
											// and minus slash onwards

	$res = mysql_query('select rec_id from records where rec_url like "http://'.addslashes($noproto_url).'%"
	                                                 or rec_url like "http://www.'.addslashes($noproto_url).'%"');
	if (mysql_num_rows($res)) return true;
	else return false;
}

function similar_urls($url) {
	/* return an array of (rec_id)s ranked in order of similarity to the URL; up to "about ten" are returned */

	/* split the input URL at the directory components, and at the query if it has one */

	$noproto_url = preg_replace('!^http://(?:www[.])?!', '', $url);	// URL minus the protocol + possibly www.

	$new_matches = mysql__select_array('records', 'rec_id', 'rec_url like "http://'.addslashes($noproto_url).'%"
	                                                     or rec_url like "http://www.'.addslashes($noproto_url).'%"');
	if (count($new_matches) >= 10) return $new_matches;

	$matches = array();
	foreach ($new_matches as $match) $matches[$match] = $match;

	$qpos = strpos($noproto_url, '?');
	if ($qpos) {
		$noproto_url = substr($noproto_url, 0, $qpos);
		$new_matches = mysql__select_array('records', 'rec_id', 'rec_url like "http://'.addslashes($noproto_url).'%"
		                                                     or rec_url like "http://www.'.addslashes($noproto_url).'%"');
		if (count($new_matches) >= 20) return $matches;

		foreach ($new_matches as $match)
			$matches[$match] = $match;

		if (count($matches) >= 10) return $matches;
	}
	while (($spos = strrpos($noproto_url, '/'))) {
		$noproto_url = substr($noproto_url, 0, $spos);
		$new_matches = mysql__select_array('records', 'rec_id', 'rec_url like "http://'.addslashes($noproto_url).'/%"
		                                                     or rec_url like "http://www.'.addslashes($noproto_url).'/%"');
		if (count($new_matches) >= 20) {
			if ($matches) return $matches;

			foreach ($new_matches as $match) {
				$matches[$match] = $match;
			}
			return $matches;
		}

		foreach ($new_matches as $match)
			$matches[$match] = $match;

		if (count($matches) >= 10) return $matches;
	}

	/* try it without the trailing slash */
	$new_matches = mysql__select_array('records', 'rec_id', 'rec_url like "http://'.addslashes($noproto_url).'%"
	                                                     or rec_url like "http://www.'.addslashes($noproto_url).'%"');
	if (count($new_matches) >= 20) return $matches;

	foreach ($new_matches as $match)
		$matches[$match] = $match;

	return $matches;
}


function site_urls($url) {
	/* find all the records URLs on the same site as the provided URL; return them as an ordered url=>(id,title) array */

	$sitename = preg_replace('!^http://(?:www[.])?([^/]+)(?:.*)!', '$1', $url);
		// just the host name
error_log($sitename);

	$res = mysql_query('select rec_url, rec_id, rec_title from records where
	                           rec_url like "http://'.addslashes($sitename).'/%"
	                        or rec_url like "http://www.'.addslashes($sitename).'/%"
	                        or rec_url = "http://'.addslashes($sitename).'"
	                        or rec_url = "http://www.'.addslashes($sitename).'"
	                           order by rec_url');
	$matches = array();
	while ($row = mysql_fetch_row($res))
		$matches[$row[0]] = array($row[1], $row[2]);
	return $matches;
}

?>
