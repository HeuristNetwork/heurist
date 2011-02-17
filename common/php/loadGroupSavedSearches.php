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

/* Take a wg_id and fill in top.HEURIST.workgroups[wg_id].members with member details
   and top.HEURIST.workgroups[wg_id].savedSearches with workgroup saved search details */

define("SAVE_URI", "disabled");
define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../connect/applyCredentials.php");
require_once("dbMySqlWrappers.php");
if (! is_logged_in()) return;

header("Content-type: text/javascript");

$wg_id = @$_REQUEST["wg_id"] ? $_REQUEST["wg_id"] : null;

if (! $wg_id) {
	print "null";
	return;
}


mysql_connection_db_select(DATABASE);

$res = mysql_query("select ugl_UserID from ".USERS_DATABASE.".sysUsrGrpLinks where ugl_UserID=".get_user_id()." and ugl_GroupID=".$wg_id);
if (mysql_num_rows($res) < 1) {
	print '({ "error": "User unauthorised to fetch workgroup data for workgroup '.$wg_id.'" })';
	return;
}
?>

{
	"members": [<?php
$res = mysql_query("select usr.ugr_ID, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as name, usr.ugr_eMail
					  from ".USERS_DATABASE.".sysUsrGrpLinks
				 left join ".USERS_DATABASE.".sysUGrps usr on usr.ugr_ID = ugl_UserID
					 where usr.ugl_GroupID = ".$wg_id."
					   and usr.ugr_Enabled = 'y'
				  order by usr.ugr_LastName");
$first = true;
while ($row = mysql_fetch_row($res)) {
	if (! $first) print ",";  print "\n"; $first = false;
	print "\t\t{ \"id\": ".slash($row[0]).", \"name\": \"".slash($row[1])."\", \"email\": \"".slash($row[2])."\" }";
}
?>

	],

	"savedSearches": [ <?php
$res = mysql_query("select svs_Name, ss_url, ss_url not like '%w=bookmark%' as w_all
					  from usrSavedSearches
					 where svs_UGrpID=".$wg_id."
				  order by svs_Name");
$first = true;
while ($row = mysql_fetch_assoc($res)) {
    if (! $first) print ",";  print "\n"; $first = false;
    print "\t\t[ \"" . addslashes($row['svs_Name']) . "\", \"" . addslashes($row['ss_url']) . "\", 0, " . intval($row['w_all']) . " ]";
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

