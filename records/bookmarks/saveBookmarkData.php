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
* service to save personal data attached to a record
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Util
*/


/* Save personal data */

define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

if (! is_logged_in()) return;

mysql_connection_overwrite(DATABASE);
$usrID = get_user_id();
$bkm_ID = intval($_POST["bkmk_id"]);
if ($bkm_ID  &&  $_POST["save-mode"] == "edit") {
	if (array_key_exists("tagString", $_POST)) {
		$tagString = doTagInsertion($bkm_ID);
	} else  $tagString = NULL;

	$updatable = array(
	/* map database column to array($_POST variable name, JSON response name) */
		"bkm_PwdReminder" => array("password-reminder", "passwordReminder"),
		"bkm_Rating" => array("overall-rating", "rating")//,
//		"pers_notes" => array("quick-notes", "quickNotes")
	);
	$updates = array();
	foreach ($updatable as $colName => $varNames) {
		if (array_key_exists($varNames[0], $_POST))
			$updates[$colName] = $_POST[$varNames[0]];
	}
	mysql__update("usrBookmarks", "bkm_ID=$bkm_ID and bkm_UGrpID=$usrID", $updates);

	$res = mysql_query("select " . join(", ", array_keys($updates)) .
				" from usrBookmarks where bkm_ID=$bkm_ID and bkm_UGrpID=$usrID");
	if (mysql_num_rows($res) == 1) {
		$dbVals = mysql_fetch_assoc($res);
		$hVals = array();
		foreach ($dbVals as $colName => $val) $hVals[$updatable[$colName][1]] = $val;

		if ($tagString !== NULL) $hVals["tagString"] = $tagString;
		print "(" . json_format($hVals) . ")";
	}
	else if ($tagString !== NULL) {
		print "({tagString: \"" . slash($tagString) . "\"})";
	}
}


function doTagInsertion($bkm_ID) {
global $usrID;
	//translate bmkID to record IT
	$res = mysql_query("select bkm_recID from usrBookmarks where bkm_ID=$bkm_ID");
	$rec_id = mysql_fetch_row($res);
	$rec_id = $rec_id[0]?$rec_id[0]:null;
	if (! $rec_id) return "";

	$tags = mysql__select_array("usrRecTagLinks, usrTags",
	                            "tag_Text", "rtl_RecID=$rec_id and tag_ID=rtl_TagID and tag_UGrpID=$usrID order by rtl_Order, rtl_ID");
	$tagString = join(",", $tags);

	// if the tags to insert is the same as the existing tags (in order) Nothing to do
	if (mb_strtolower(trim($_POST["tagString"]), 'UTF-8') == mb_strtolower(trim($tagString), 'UTF-8')) return;

	// create array of tags to be linked
	$tags = array_filter(array_map("trim", explode(",", str_replace("\\", "/", $_POST["tagString"]))));	// replace backslashes with forwardslashes
    
	//create a map of this user's personal tags to tagIDs
	$kwd_map = mysql__select_assoc("usrTags", "trim(lower(tag_Text))", "tag_ID",
	                               "tag_UGrpID=$usrID and tag_Text in (\"".join("\",\"", array_map("mysql_real_escape_string", $tags))."\")");
                                   
	$tag_ids = array();
	foreach ($tags as $tag) {
		$tag = preg_replace('/\\s+/', ' ', trim($tag));
        
        $tag = mb_strtolower($tag, 'UTF-8');

		if (@$kwd_map[$tag]) {// tag exist get it's id
        
			$tag_id = $kwd_map[$tag];
		} else {// no existing tag so add it and get it's id
        
            $query = "insert into usrTags (tag_Text, tag_UGrpID) values (\"" . mysql_real_escape_string($tag) . "\", $usrID)";
            mysql_query($query);
            
			$tag_id = mysql_insert_id();
		}
		array_push($tag_ids, $tag_id);
	}

	// Delete all personal tags for this bookmark's record
	mysql_query("delete usrRecTagLinks from usrRecTagLinks, usrTags where rtl_RecID = $rec_id and tag_ID=rtl_TagID and tag_UGrpID = $usrID");

	if (count($tag_ids) > 0) {
		$query = "";
		for ($i=0; $i < count($tag_ids); ++$i) {
			if ($query) $query .= ", ";
			$query .= "($rec_id, ".($i+1).", ".$tag_ids[$i].")";
		}
		$query = "insert into usrRecTagLinks (rtl_RecID, rtl_Order, rtl_TagID) values " . $query;

		mysql_query($query);
	}

	// return new tag string
	$tags = mysql__select_array("usrRecTagLinks, usrTags",
	                            "tag_Text", "rtl_RecID = $rec_id and tag_ID=rtl_TagID and tag_UGrpID=$usrID order by rtl_Order, rtl_ID");
	return join(",", $tags);
}

?>
