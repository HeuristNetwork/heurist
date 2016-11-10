<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Add
*/


define('ISSERVICE',1);
define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

if (! is_logged_in()) return;

mysql_connection_overwrite(DATABASE);

header("Content-type: text/javascript");


/* chase down any "replaced by" indirections */
$usrID = get_user_id();
$rec_id = intval($_REQUEST["recID"]);
$res = mysql_query("select * from Records where rec_ID = $rec_id");
$bib = mysql_fetch_assoc($res);
if (! $bib) {
	print "{ error: \"invalid record ID - $rec_id\" }";
	return;
}

/* check workgroup permissions */
if (array_key_exists("rec_OwnerUGrpID",$bib) &&
		$bib["rec_OwnerUGrpID"] != $usrID &&
		$bib["rec_OwnerUGrpID"] != 0 &&
		$bib["rec_NonOwnerVisibility"] == "hidden") {
	$res = mysql_query("select ugl_GroupID from ".USERS_DATABASE.".sysUsrGrpLinks ".
						"where ugl_UserID=$usrID and ugl_GroupID=" . intval($bib["rec_OwnerUGrpID"]));
	if (! mysql_num_rows($res)) {
		$res = mysql_query("select grp.ugr_Name from ".USERS_DATABASE.".sysUGrps grp where grp.ugr_ID=" . $bib["rec_OwnerUGrpID"]);
		$grp_name = mysql_fetch_row($res);  $grp_name = $grp_name[0];
		print "{ error: \"record is restricted to workgroup " . slash($grp_name) . "\" }";
		return;
	}
}


/* check -- maybe the user has this bookmarked already ..? */
$res = mysql_query("select * from usrBookmarks where bkm_recID=$rec_id and bkm_UGrpID=$usrID");

if (mysql_num_rows($res) == 0) {
	/* full steam ahead */
	mysql_query("insert into usrBookmarks (bkm_recID, bkm_UGrpID, bkm_Added, bkm_Modified) values (" . $rec_id . ", $usrID, now(), now())");

	$res = mysql_query("select * from usrBookmarks where bkm_ID=last_insert_id()");
	if (mysql_num_rows($res) == 0) {
		print "{ error: \"internal database error while adding bookmark\" }";
		return;
	}
	$bkmk = mysql_fetch_assoc($res);
	$tagString = "";
}else{
	$bkmk = mysql_fetch_assoc($res);
	$kwds = mysql__select_array("usrRecTagLinks left join usrTags on tag_ID=rtl_TagID", "tag_Text", "rtl_RecID=$rec_id and tag_UGrpID=$usrID order by rtl_Order, rtl_ID");
	$tagString = join(",", $kwds);
}

$record = array(
	"bkmkID" => $bkmk["bkm_ID"],
	"tagString" => $tagString,
	"rating" => $bkmk["bkm_Rating"],
	"reminders" => array(), // FIXME: should really import these freshly in case the bkmk already exists
	"passwordReminder" => $bkmk["bkm_PwdReminder"]//,
//	"quickNotes" => $bkmk["pers_notes"]? $bkmk["pers_notes"] : ""	// saw TODO possibly need to change this to include woot
);

print json_format($record);

?>
