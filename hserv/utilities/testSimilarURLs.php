<?php
/**
* testSimilarURLs.php - Utility functions for finding and comparing similar URLs within the Heurist database
* 
* These functions help identify records that may have URLs related to a given URL,
* by checking for variations in protocol, 'www' prefix, and path components.
*
* Functions:
* - similarUrlExist: Checks if any URLs similar to the given URL (ignoring protocol and 'www') exist in the database.
* - similarUrlFind: Retrieves record IDs for URLs that match a given URL pattern (ignoring protocol and 'www', checking sub-paths).
* - similarUrlFindAll: Finds record IDs for URLs similar to the input URL, progressively shortening the URL path to find broader matches.
* - similarUrlByDomain: Retrieves all record URLs, IDs, and titles from the same website/domain as the input URL.
*
* 
* @package     Heurist academic knowledge management system
* @subpackage  hserv\utilities
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1.0 
*/

/**
 * Checks if there are any URLs in the database similar to the given URL.
 * Similarity is based on matching the domain part of the URL, ignoring protocol (http/https) and 'www.' prefix.
 *
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param string $url The URL to check for similar entries.
 * @return bool True if similar URLs exist, false otherwise.
 */
function similarUrlExist($mysqli, $url) {
    /* are there similar URLs to this one already? */

    // URL minus the protocol + possibly www.  and minus slash onwards
    $noproto_url = preg_replace('!^http(s)?://(?:www[.])?([^/]*).*!', '\1', $url);

    $res = mysql__select_value($mysqli, 'select count(rec_ID) from Records '
        .' where rec_URL like "%'.$mysqli->real_escape_string($noproto_url).'%" '
        .' or rec_URL like "%'.$mysqli->real_escape_string($noproto_url).'%"');//http://www.

    return $res>0;
}

/**
 * Retrieves a list of record IDs whose URLs match a given URL pattern.
 * The match is partial (LIKE "http://domain/path%") and also checks for a 'www.' prefix.
 *
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param string $url The base URL (typically domain and path, without protocol) to match against.
 * @return array An array of record IDs (rec_ID) that match the URL pattern.
 */
function similarUrlFind($mysqli, $url){
    return mysql__select_list($mysqli, 'Records', 'rec_ID',
        'rec_URL like "https://'.$mysqli->real_escape_string($url).'%" '
        .' or rec_URL like "https://www.'.$mysqli->real_escape_string($url).'%"');
}

/**
 * Finds record IDs for URLs that are similar to the input URL.
 * It progressively shortens the URL path to find broader matches, up to a limit of about 10-20 matches.
 * Ignores protocol and 'www.' prefix.
 *
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param string $url The URL to find similarities for.
 * @return array An associative array where keys and values are matching record IDs (rec_ID).
 */
function similarUrlFindAll($mysqli, $url) {
    /* return an array of (rec_ID)s ranked in order of similarity to the URL; up to "about ten" are returned */

    /* split the input URL at the directory components, and at the query if it has one */

    $noproto_url = preg_replace('!^http(s)?://(?:www[.])?!', '', $url);	// URL minus the protocol + possibly www.

    $new_matches = similarUrlFind($mysqli, $noproto_url);
    if (count($new_matches) >= 10) {return $new_matches;}

    $matches = array();
    foreach ($new_matches as $match) {$matches[$match] = $match;}

    $qpos = strpos($noproto_url, '?');
    if ($qpos) {
        $noproto_url = substr($noproto_url, 0, $qpos);
        $new_matches = similarUrlFind($mysqli, $noproto_url);
        if (count($new_matches) >= 20) {return $matches;}

        foreach ($new_matches as $match){
            $matches[$match] = $match;
        }
        if (count($matches) >= 10) {return $matches;}
    }
    while ($spos = strrpos($noproto_url, '/')) {
        $noproto_url = substr($noproto_url, 0, $spos);
        $new_matches = similarUrlFind($mysqli, $noproto_url);
        if (count($new_matches) >= 20) {
            if ($matches) {return $matches;}

            foreach ($new_matches as $match) {
                $matches[$match] = $match;
            }
            return $matches;
        }

        foreach ($new_matches as $match){
            $matches[$match] = $match;
        }

        if (count($matches) >= 10) {return $matches;}
    }

    /* try it without the trailing slash */
    $new_matches = similarUrlFind($mysqli, $noproto_url);
    if (count($new_matches) >= 20) {return $matches;}

    foreach ($new_matches as $match){
        $matches[$match] = $match;
    }

    return $matches;
}

/**
 * Finds all record URLs on the same website/domain as the provided URL.
 * Returns an associative array where keys are the full URLs and values are arrays containing [record_ID, record_Title].
 *
 * @param \mysqli $mysqli The mysqli database connection object.
 * @param string $url The URL to extract the site/domain from.
 * @return array An associative array of URLs from the same site, mapping URL => [rec_ID, rec_Title].
 */
function similarUrlByDomain($mysqli, $url) {
    /* find all the records URLs on the same site as the provided URL; return them as an ordered url=>(id,title) array */

    $sitename = preg_replace('!^http(s)?://(?:www[.])?([^/]+)(?:.*)!', '$1', $url);
    // just the host name

    $sitename = $mysqli->real_escape_string($sitename);

    $res = $mysqli->query('select rec_URL, rec_ID, rec_Title from Records where
        rec_URL like "https://'.$sitename.'/%"
        or rec_URL like "https://www.'.$sitename.'/%"
        or rec_URL = "https://'.$sitename.'"
        or rec_URL = "https://www.'.$sitename.'"
    order by rec_URL');
    $matches = array();
    if($res){
        while ($row = $res->fetch_row()){
            $matches[$row[0]] = array($row[1], $row[2]);
        }

        $res->close();
    }
    return $matches;
}
