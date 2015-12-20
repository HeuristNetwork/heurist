<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


/* Take a wg_id and fill in top.HEURIST.workgroups[wg_id].members with member details
   and top.HEURIST.workgroups[wg_id].savedSearches with workgroup saved search details */
define('ISSERVICE',1);

define("SAVE_URI", "disabled");
define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../connect/applyCredentials.php");
require_once(dirname(__FILE__)."/dbMySqlWrappers.php");
if (! is_logged_in()) return;

header("Content-type: text/javascript");

$wg_id = @$_REQUEST["wg_id"] ? $_REQUEST["wg_id"] : null;

if (! $wg_id) {
	print "null";
	return;
}


mysql_connection_select(DATABASE);

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

