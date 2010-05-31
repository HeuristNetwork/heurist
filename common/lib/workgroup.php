<?php

/* Take a wg_id and fill in top.HEURIST.workgroups[wg_id].members with member details
   and top.HEURIST.workgroups[wg_id].savedSearches with workgroup saved search details */

define("SAVE_URI", "disabled");
define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../connect/cred.php");
require_once(dirname(__FILE__)."/../connect/db.php");
if (! is_logged_in()) return;

header("Content-type: text/javascript");

$wg_id = @$_REQUEST["wg_id"] ? $_REQUEST["wg_id"] : null;

if (! $wg_id) {
	print "null";
	return;
}


mysql_connection_db_select(DATABASE);

$res = mysql_query("select ug_user_id from ".USERS_DATABASE.".UserGroups where ug_user_id=".get_user_id()." and ug_group_id=".$wg_id);
if (mysql_num_rows($res) < 1) {
	print '({ "error": "User unauthorised to fetch workgroup data for workgroup '.$wg_id.'" })';
	return;
}
?>

{
	"members": [<?php
$res = mysql_query("select Id, concat(firstname,' ',lastname) as name, EMail
					  from ".USERS_DATABASE.".UserGroups
				 left join ".USERS_DATABASE.".Users on Id = ug_user_id
					 where ug_group_id = ".$wg_id."
					   and Active = 'Y'
				  order by lastname");
$first = true;
while ($row = mysql_fetch_row($res)) {
	if (! $first) print ",";  print "\n"; $first = false;
	print "\t\t{ \"id\": ".slash($row[0]).", \"name\": \"".slash($row[1])."\", \"email\": \"".slash($row[2])."\" }";
}
?>

	],

	"savedSearches": [ <?php
$res = mysql_query("select ss_name, ss_url, ss_url not like '%w=bookmark%' as w_all
					  from saved_searches
					 where ss_wg_id=".$wg_id."
				  order by ss_name");
$first = true;
while ($row = mysql_fetch_assoc($res)) {
    if (! $first) print ",";  print "\n"; $first = false;
    print "\t\t[ \"" . addslashes($row['ss_name']) . "\", \"" . addslashes($row['ss_url']) . "\", 0, " . intval($row['w_all']) . " ]";
}
?>

	],

	"publishedSearches": [ <?php
$res = mysql_query("select pub_id, pub_name
					  from published_searches
					 where pub_wg_id=".$wg_id."
                     order by pub_name");
$first = true;
while ($row = mysql_fetch_assoc($res)) {
    if (! $first) print ",";  print "\n"; $first = false;
    print "\t\t{ \"id\": \"".addslashes($row['pub_id'])."\", \"label\": \"".addslashes($row['pub_name'])."\" }";
}
?>

	]
}

