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
* code for finding similar URLs
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
* @subpackage  Records/Util
*/


function exist_similar($url) {
	/* are there similar URLs to this one already? */

	$noproto_url = preg_replace('!^http://(?:www[.])?([^/]*).*!', '\1', $url);	// URL minus the protocol + possibly www.
											// and minus slash onwards

	$res = mysql_query('select rec_ID from Records where rec_URL like "http://'.mysql_real_escape_string($noproto_url).'%"
	                                                 or rec_URL like "http://www.'.mysql_real_escape_string($noproto_url).'%"');
	if (mysql_num_rows($res)) return true;
	else return false;
}

function similar_urls($url) {
	/* return an array of (rec_ID)s ranked in order of similarity to the URL; up to "about ten" are returned */

	/* split the input URL at the directory components, and at the query if it has one */

	$noproto_url = preg_replace('!^http://(?:www[.])?!', '', $url);	// URL minus the protocol + possibly www.

	$new_matches = mysql__select_array('Records', 'rec_ID', 'rec_URL like "http://'.mysql_real_escape_string($noproto_url).'%"
	                                                     or rec_URL like "http://www.'.mysql_real_escape_string($noproto_url).'%"');
	if (count($new_matches) >= 10) return $new_matches;

	$matches = array();
	foreach ($new_matches as $match) $matches[$match] = $match;

	$qpos = strpos($noproto_url, '?');
	if ($qpos) {
		$noproto_url = substr($noproto_url, 0, $qpos);
		$new_matches = mysql__select_array('Records', 'rec_ID', 'rec_URL like "http://'.mysql_real_escape_string($noproto_url).'%"
		                                                     or rec_URL like "http://www.'.mysql_real_escape_string($noproto_url).'%"');
		if (count($new_matches) >= 20) return $matches;

		foreach ($new_matches as $match)
			$matches[$match] = $match;

		if (count($matches) >= 10) return $matches;
	}
	while (($spos = strrpos($noproto_url, '/'))) {
		$noproto_url = substr($noproto_url, 0, $spos);
		$new_matches = mysql__select_array('Records', 'rec_ID', 'rec_URL like "http://'.mysql_real_escape_string($noproto_url).'/%"
		                                                     or rec_URL like "http://www.'.mysql_real_escape_string($noproto_url).'/%"');
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
	$new_matches = mysql__select_array('Records', 'rec_ID', 'rec_URL like "http://'.mysql_real_escape_string($noproto_url).'%"
	                                                     or rec_URL like "http://www.'.mysql_real_escape_string($noproto_url).'%"');
	if (count($new_matches) >= 20) return $matches;

	foreach ($new_matches as $match)
		$matches[$match] = $match;

	return $matches;
}


function site_urls($url) {
	/* find all the records URLs on the same site as the provided URL; return them as an ordered url=>(id,title) array */

	$sitename = preg_replace('!^http://(?:www[.])?([^/]+)(?:.*)!', '$1', $url);
		// just the host name

	$res = mysql_query('select rec_URL, rec_ID, rec_Title from Records where
	                           rec_URL like "http://'.mysql_real_escape_string($sitename).'/%"
	                        or rec_URL like "http://www.'.mysql_real_escape_string($sitename).'/%"
	                        or rec_URL = "http://'.mysql_real_escape_string($sitename).'"
	                        or rec_URL = "http://www.'.mysql_real_escape_string($sitename).'"
	                           order by rec_URL');
	$matches = array();
	while ($row = mysql_fetch_row($res))
		$matches[$row[0]] = array($row[1], $row[2]);
	return $matches;
}

?>
