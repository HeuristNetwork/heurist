<?php

define("SAVE_URI", "disabled");

// using ob_gzhandler makes this stuff up on IE6-
ini_set("zlib.output_compression_level", 5);
//ob_start('ob_gzhandler');


require_once("../modules/cred.php");
require_once("../modules/db.php");

mysql_connection_db_select(DATABASE);

header("Content-type: text/javascript");

if (! @$_REQUEST["url"]) return;

$url = $_REQUEST["url"];

if (substr($url, -1) == "/") $url = substr($url, 0, strlen($url)-1);

$res = mysql_query("select rec_id
					  from records
				 left join personals on pers_rec_id = rec_id
					 where (rec_url='".addslashes($url)."' or rec_url='".addslashes($url)."/')
				  group by pers_id
				  order by count(pers_id), rec_id
					 limit 1");
if ($row = mysql_fetch_assoc($res)) {
	print "HEURIST_url_bib_id = ".$row["rec_id"].";\n\n";
} else {
	print "HEURIST_url_bib_id = null;\n\n";
}

$res = mysql_query("select pers_id
					  from personals
				 left join records on rec_id = pers_rec_id
					 where pers_usr_id=".get_user_id()."
					   and (rec_url='".addslashes($url)."' or rec_url='".addslashes($url)."/')
					 limit 1");
if ($row = mysql_fetch_assoc($res)) {
	print "HEURIST_url_bkmk_id = ".$row["pers_id"].";\n\n";
} else {
	print "HEURIST_url_bkmk_id = null;\n\n";
}

print "if (window.HEURIST_urlBookmarkedOnload) HEURIST_urlBookmarkedOnload();\n\n";

ob_end_flush();
?>
