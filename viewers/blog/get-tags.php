<?php
/**
*This file retruns a json code array of array of tags keyed by record id
*  [rec_id1 => [ tag1,tag2...],
*   rec_id2 =>[..],
*   ...
*  ]
*/
require_once(dirname(__FILE__)."/../../common/connect/db.php");
require_once(dirname(__FILE__)."/../../common/connect/cred.php");

if (! is_logged_in()) return "";

$userID = intval($_REQUEST["u"]);

if (! $userID) return "";

mysql_connection_select(DATABASE);
// get a list of tags linked to any of the 'blog entry' records for this user
$res = mysql_query("select rec_id, group_concat(kwd_name)
					  from records, keyword_links, usrTags
					 where rec_type = 137 and kwl_rec_id = rec_id
					   and kwd_id = kwl_kwd_id
					   and kwd_usr_id = " . $userID . "
					group by rec_id");

$tags = array();
while ($row = mysql_fetch_array($res)) {
	$tags[$row[0]] = explode(",", $row[1]);
}

print json_format($tags);

?>
