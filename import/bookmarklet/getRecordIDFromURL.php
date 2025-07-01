<?php
/**
* getRecordIDFromURL.php - Checks if a URL is already bookmarked in Heurist and returns record and bookmark IDs as JavaScript variables.
*
* @project     Heurist academic knowledge management system
* @package  import\bookmarklet
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       3.1
*/
header(CTYPE_JS);

if (! @$_REQUEST["url"]) {return;}

require_once dirname(__FILE__).'/../../autoload.php';
$system = new hserv\System();
if(!$system->init(@$_REQUEST['db'])){
    return;
}

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", '5');
//ob_start('ob_gzhandler');

ob_start();

$url = $_REQUEST["url"];
$mysqli = $system->getMysqli();

if (substr($url, -1) == "/") {$url = substr($url, 0, strlen($url)-1);}

$url = $mysqli->real_escape_string($url);

//find record with exactly the same URL
$query = "select rec_id from Records where (rec_URL='". $url ."' or rec_URL='". $url ."/')";

$rec_id = mysql__select_value($mysqli, $query);
$bkm_id = 0;

if ($rec_id>0) {
    print "HEURIST_url_bib_id = ".intval($rec_id).";\n\n";

    //find bookmark for this record for current user
    $query = 'select bkm_ID from usrBookmarks where bkm_recID='.intval($rec_id).' and bkm_UGrpID='.$system->getUserId();
    $bkm_id = mysql__select_value($mysqli, $query);

} else {
    print "HEURIST_url_bib_id = null;\n\n";
}

if ($bkm_id>0) {
    print "HEURIST_url_bkmk_id = ".intval($bkm_id).";\n\n";
} else {
    print "HEURIST_url_bkmk_id = null;\n\n";
}

print "if (window.HEURIST_urlBookmarkedOnload) HEURIST_urlBookmarkedOnload();\n\n";

ob_end_flush();
