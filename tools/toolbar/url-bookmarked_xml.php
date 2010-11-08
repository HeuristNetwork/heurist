<?php
//heurist toolbar call this with the url of the current load page to check if it has been bookmarked.
//this php code returns the found record (with the highest number of bookmarks) and/or bookmark (of the current user) ids in an xml structure
//  <ids>
//     	<HEURIST_url_bib_id value=recIdFound_or_null/>
//		<HEURIST_url_bkmk_id value=bkmkIdFound_or_null/>
//	</ids>

define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');


require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

mysql_connection_db_select(DATABASE);

header("Content-type: text/xml");

echo "<?xml version=\"1.0\"?>";
?>

<ids>

<?php
if (! @$_REQUEST["url"]) return;

$url = $_REQUEST["url"];
//error_log(" url : ". $url);
if (substr($url, -1) == "/") $url = substr($url, 0, strlen($url)-1);

$res = mysql_query("select rec_id
					  from records
				 left join usrBookmarks on pers_rec_id = rec_id
					 where (rec_url='".addslashes($url)."' or rec_url='".addslashes($url)."/')
				  group by pers_id
				  order by count(pers_id), rec_id
					 limit 1");
if ($row = mysql_fetch_assoc($res)) {
	print "<HEURIST_url_bib_id value=\"".$row["rec_id"]."\"/>";
} else {
	print "<HEURIST_url_bib_id value=\"null\"/>";
}

$res = mysql_query("select pers_id
					  from usrBookmarks
				 left join records on rec_id = pers_rec_id
					 where pers_usr_id=".get_user_id()."
					   and (rec_url='".addslashes($url)."' or rec_url='".addslashes($url)."/')
					 limit 1");
if ($row = mysql_fetch_assoc($res)) {
	print "<HEURIST_url_bkmk_id value=\"".$row["pers_id"]."\"/>";
} else {
	print "<HEURIST_url_bkmk_id value=\"null\"/>";
}
?>

</ids>

<?php
ob_end_flush();
?>
