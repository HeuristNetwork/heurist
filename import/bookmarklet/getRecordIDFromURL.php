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

define('ISSERVICE',1);
define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

mysql_connection_select(DATABASE);

header("Content-type: text/javascript");

if (! @$_REQUEST["url"]) return;

$url = $_REQUEST["url"];

if (substr($url, -1) == "/") $url = substr($url, 0, strlen($url)-1);

$res = mysql_query("select rec_id
					  from records
				 left join usrBookmarks on bkm_recID = rec_id
					 where (rec_URL='".addslashes($url)."' or rec_URL='".addslashes($url)."/')
				  group by bkm_ID
				  order by count(bkm_ID), rec_id
					 limit 1");
if ($row = mysql_fetch_assoc($res)) {
	print "HEURIST_url_bib_id = ".$row["rec_id"].";\n\n";
} else {
	print "HEURIST_url_bib_id = null;\n\n";
}

$res = mysql_query("select bkm_ID
					  from usrBookmarks
				 left join records on rec_id = bkm_recID
					 where bkm_UGrpID=".get_user_id()."
					   and (rec_URL='".addslashes($url)."' or rec_URL='".addslashes($url)."/')
					 limit 1");
if ($res && $row = mysql_fetch_assoc($res)) {
	print "HEURIST_url_bkmk_id = ".$row["bkm_ID"].";\n\n";
} else {
	print "HEURIST_url_bkmk_id = null;\n\n";
}

print "if (window.HEURIST_urlBookmarkedOnload) HEURIST_urlBookmarkedOnload();\n\n";

ob_end_flush();
?>
