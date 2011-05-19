<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

	/*<!-- saveRecord.php

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

	-->*/
require_once(dirname(__FILE__)."/../../search/getSearchResults.php");
// NOTE  tags are a complete replacement list of personal tags for this record and are only used if personalised is true
function saveRecord($recordID, $type, $url, $notes, $wg, $vis, $personalised, $pnotes, $rating, $tags, $wgTags, $details, $notifyREMOVE, $notifyADD, $commentREMOVE, $commentMOD, $commentADD, &$nonces=null, &$retitleRecs=null) {
	$recordID = intval($recordID);
	$wg = intval($wg);
	if ($wg) {
		$res = mysql_query("select * from ".USERS_DATABASE.".sysUsrGrpLinks where ugl_UserID=" . get_user_id() . " and ugl_GroupID=" . $wg);
		if (mysql_num_rows($res) < 1) jsonError("invalid workgroup");
	}

	$type = intval($type);
	if ($recordID  &&  ! $type) {
		jsonError("cannot change existing record to private note");
	}

	$now = date('Y-m-d H:i:s');

	// public records data
	if (! $recordID) {
		mysql__insert("Records", array(
		"rec_RecTypeID" => $type,
		"rec_URL" => $url,
		"rec_ScratchPad" => $notes,
		"rec_OwnerUGrpID" => ($wg?$wg:get_user_id()),
		"rec_NonOwnerVisibility" => $wg? ($vis? "viewable" : "hidden") : "viewable",
		"rec_AddedByUGrpID" => get_user_id(),
		"rec_Added" => $now,
		"rec_Modified" => $now
		));
		if (mysql_error()) jsonError("database write error - " . mysql_error());

		$recordID = mysql_insert_id();
	}else{
		$res = mysql_query("select * from Records left join ".USERS_DATABASE.".sysUsrGrpLinks on ugl_GroupID=rec_OwnerUGrpID and ugl_UserID=".get_user_id()." where rec_ID=$recordID");
		$record = mysql_fetch_assoc($res);

		if ($wg != $record["rec_OwnerUGrpID"] && $record["rec_OwnerUGrpID"] != get_user_id() ) {
			if ($record["rec_OwnerUGrpID"] > 0  &&  $record["ugl_Role"] != "admin") {
				// user is trying to change the workgroup when they are not an admin
				jsonError("user is not a workgroup admin");
			} else if (! is_admin()) {
				// you must be an database admin to change a public record into a workgroup record
				jsonError("user does not have sufficient authority to change public record to workgroup record");
			}
		}
		if (! $wg) { $vis = NULL; }

		mysql__update("Records", "rec_ID=$recordID", array(
		"rec_RecTypeID" => $type,
		"rec_URL" => $url,
		"rec_ScratchPad" => $notes,
		"rec_OwnerUGrpID" => $wg?$wg:get_user_id(),
		"rec_NonOwnerVisibility" => $wg? ($vis? "viewable" : "hidden") : "viewable",
		"rec_Modified" => $now
		));
		if (mysql_error()) jsonError("database write error" . mysql_error());
	}

	// public recDetails data
	if ($details) {
		$bdIDs = doDetailInsertion($recordID, $details, $type, $wg, $nonces, $retitleRecs);
	}

	// check that all the required fields are present
	$res = mysql_query("select rst_ID from defRecStructure left join recDetails on dtl_RecID=$recordID and rst_DetailTypeID=dtl_DetailTypeID where rst_RecTypeID=$type and rst_RequirementType='required' and dtl_ID is null");
	if (mysql_num_rows($res) > 0) {
		// at least one missing field
		jsonError("record is missing required field(s)");
	}
/* Override  code removed by SAW on 13/1/11
	$res = mysql_query("select rst_ID from rec_detail_requirements_overrides left join recDetails on dtl_RecID=$recordID and rst_DetailTypeID=dtl_DetailTypeID where (rdr_wg_id = 0 or rdr_wg_id=$wg) and rdr_wg_id = rst_RecTypeID=$type and rst_RequirementType='required' and dtl_ID is null");
	if (mysql_num_rows($res) > 0) {
		// at least one missing field
		jsonError("record is missing required field(s)");
	}
*/
	// calculate title, do an update
	$mask = mysql__select_array("defRecTypes", "rty_TitleMask", "rty_ID=$type");  $mask = $mask[0];
	$title = fill_title_mask($mask, $recordID, $type);
	if ($title) {
		mysql_query("update Records set rec_Title = '" . addslashes($title) . "' where rec_ID = $recordID");
	}

	// Update memcache: we can do this here since it's only the public data that we cache.
	updateCachedRecord($recordID);

	// private data
	$bkmk = @mysql_fetch_row(mysql_query("select bkm_ID from usrBookmarks where bkm_UGrpID=" . get_user_id() . " and bkm_recID=" . $recordID));
	$bkm_ID = @$bkmk[0];
	if ($personalised) {
		if (! $bkm_ID) {
			// Record is not yet bookmarked, but we want it to be
			mysql_query("insert into usrBookmarks (bkm_Added,bkm_Modified,bkm_UGrpID,bkm_recID) values (now(),now(),".get_user_id().",$recordID)");
			if (mysql_error()) jsonError("database error - " . mysql_error());
			$bkm_ID = mysql_insert_id();
		}

		mysql__update("usrBookmarks", "bkm_ID=$bkm_ID", array(
//		"pers_notes" => $pnotes,	//saw TODO: need to add code to place this in a personal woot
		"bkm_Rating" => $rating,
		"bkm_Modified" => date('Y-m-d H:i:s')
		));
		//WARNING  tags is assumed to be a complete replacement list for personal tags on this record.
		doTagInsertion($recordID, $bkm_ID, $tags);
	} else if ($bkm_ID) {
		// Record is bookmarked, but the user doesn't want it to be
		mysql_query("delete usrBookmarks, usrRecTagLinks ".
					"from usrBookmarks left join usrRecTagLinks on rtl_RecID = bkm_recID ".
					"left join usrTags tag_ID = rtl_TagID ".
					"where bkm_ID=$bkm_ID and bkm_recID=$recordID and bkm_UGrpID = tag_UGrpID and bkm_UGrpID=" . get_user_id());
		if (mysql_error()) jsonError("database error while removing bookmark- " . mysql_error());
		//saw TODO: add code to remove other personal data reminders, personal notes (woots), etc.
	}

	doWgTagInsertion($recordID, $wgTags);

	if ($notifyREMOVE  ||  $notifyADD) {
		$notifyIDs = handleNotifications($recordID, $notifyREMOVE, $notifyADD);
	}

	if ($commentREMOVE  ||  $commentMOD  ||  $commentADD) {
		$commentIDs = handleComments($recordID, $commentREMOVE, $commentMOD, $commentADD);
	}


	$rval = array("bibID" => $recordID, "bkmkID" => $bkm_ID, "modified" => $now);
	if ($title) {
		$rval["title"] = $title;
	}
	if (@$bdIDs) {
		$rval["detail"] = $bdIDs;
	}
	if (@$notifyIDs) {
		$rval["notify"] = $notifyIDs;
	}
	if (@$commentIDs) {
		$rval["comment"] = $commentIDs;
	}

	return $rval;
}

/*
id
type
url
notes
group
vis
pnotes
rating
irate //deprecated
qrate //deprecated
tags
wgTags
details : [t:xxx] => [ [bd:yyy] => val ]*

*/


function doDetailInsertion($recordID, $details, $recordType, $wg, &$nonces, &$retitleRecs) {
	/* $nonces :  nonce-to-bibID mapping, makes it possible to resolve recDetails values of reference variety */
	/* $retitleRecs : set of records whos titles could be out of date and need recalc */
	// do a double-pass to grab the expected varieties for each bib-detail-type we encounter

	$types = array();
	foreach ($details as $type => $pairs) {
		if (substr($type, 0, 2) != "t:") continue;
		if (! ($bdtID = intval(substr($type, 2)))) continue;
		array_push($types, $bdtID);
	}
	$typeVarieties = mysql__select_assoc("defDetailTypes", "dty_ID", "dty_Type", "dty_ID in (" . join($types, ",") . ")");
	$repeats = mysql__select_assoc("defRecStructure", "rst_DetailTypeID", "rst_MaxValues", "rst_DetailTypeID in (" . join($types, ",") . ") and rst_RecTypeID=" . $recordType);

	$updates = array();
	$inserts = array();
	$dontDeletes = array();
	foreach ($details as $type => $pairs) {
		if (substr($type, 0, 2) != "t:") continue;
		if (! ($bdtID = intval(substr($type, 2)))) continue;	// invalid type id so skip it

		foreach ($pairs as $bdID => $val) {
			if (substr($bdID, 0, 3) == "bd:") {// this detail corresponds to an existing recDetails: remember its existing dtl_ID
				if (! ($bdID = intval(substr($bdID, 3)))) continue; // invalid id so skip it
			}else {	// simple case: this is a new detail (no existing dtl_ID)
				if ($bdID != intval($bdID)) continue;
				$bdID = "";
			}
			$val = trim($val);

			$bdVal = $bdFileID = $bdGeo = "NULL";
			if ( ! array_key_exists($bdtID,$repeats)) continue; // detail type not allowed or hit limit
			if ($repeats[$bdtID] > 1) {
				$repeats[$bdtID] = $repeats[$bdtID] - 1; // decrement to reduce limit count NOTE: assumes that all details are given to save
			} else if ($repeats[$bdtID] == 1) {
				unset ($repeats[$bdtID]);	// remove this type so no more values can be accepted
			}
			switch ($typeVarieties[$bdtID]) {
				case "integer":
					if (intval($val)  ||  $val == "0") $bdVal = intval($val);
					else { if ($bdID) array_push($dontDeletes, $bdID); continue; }
					break;

				case "float":
					if (floatval($val)  ||  preg_match('/^0(?:[.]0*)?$/', $val)) $bdVal = floatval($val);
					else { if ($bdID) array_push($dontDeletes, $bdID); continue; }
					break;

				case "freetext": case "blocktext":
				case "date": case "year":
					if (! $val) { if ($bdID) array_push($dontDeletes, $bdID); continue; }
					$bdVal = "'" . addslashes($val) . "'";
					break;

				case "boolean":
					$bdVal = ($val && $val != "0")? "'true'" : "'false'";
					break;

				case "enum":
				case "relationtype":	//saw TODO: change this to call validateEnumTerm(RectypeID, DetailTypeID) also Term limits
										// also may need to separate enum from relationtype
					// validate that the id is for the given detail type.
					/*if (mysql_num_rows(mysql_query("select trm_ID from defTerms
														left join defDetailTypes on dty_NativeVocabID = trm_VocabID
														where dty_ID=$bdtID and trm_ID='".$val."'")) <= 0) {
					jsonError("invalid enumeration value \"$val\"");
					}
					*/
					$bdVal = "'" . $val . "'";
					break;

				case "resource":
					// check that the given resource exists and is fit to point to
					if ($val[0] == "#") {
						// a nonce value -- find the appropriate bibID
						if ($nonces && $nonces[$val]) {
							$val = $nonces[$val];
							if (is_array($retitleRecs)) {
								array_push($retitleRecs,$val);
							}
						}
						else {
							jsonError("invalid resource reference '".$val."'");
						}
					}

					if (mysql_num_rows(mysql_query("select rec_ID from Records where (! rec_OwnerUGrpID or rec_OwnerUGrpID=$wg) and rec_ID=".intval($val))) <= 0)
					jsonError("invalid resource #".intval($val));
					$bdVal = intval($val);
					break;

				case "file":
					if (mysql_num_rows(mysql_query("select ulf_ID from recUploadedFiles where ulf_ID=".intval($val))) <= 0)
					jsonError("invalid file pointer");
					$bdFileID = intval($val);
					break;

				case "geo":
					$geoType = trim(substr($val, 0, 2));
					$geoVal = trim(substr($val, 2));
					$res = mysql_query("select geomfromtext('".addslashes($geoVal)."') = 'Bad object'");
					$row = mysql_fetch_row($res);
					if ($row[0]) {
						// bad object!  Go stand in the corner.
						jsonError("invalid geographic value");
					}
					$bdVal = '"' . addslashes($geoType) . '"';
					$bdGeo = "geomfromtext('".addslashes($geoVal)."')";

				case "separator":
				case "relmarker":
					continue;	//noop since separators and relmarker have no detail values
								// saw Decide - should we do a relationConstraints check here

				default:
					// ???
					if ($bdID) array_push($dontDeletes, $bdID);
					continue;
			}

			if ($bdID) {
				array_push($updates, "update recDetails set dtl_Value=$bdVal, dtl_UploadedFileID=$bdFileID, dtl_Geo=$bdGeo where dtl_ID=$bdID and dtl_DetailTypeID=$bdtID and dtl_RecID=$recordID");
				array_push($dontDeletes, $bdID);
			}
			else {
				array_push($inserts, "($recordID, $bdtID, $bdVal, $bdFileID, $bdGeo)");
			}
		}
	}
	$deleteDetailQuery = "delete from recDetails where dtl_RecID=$recordID";
	if (count($dontDeletes)) $deleteDetailQuery .= " and dtl_ID not in (" . join(",", $dontDeletes) . ")";
	mysql_query($deleteDetailQuery);
	if (mysql_error()) jsonError("database error - " . mysql_error());

	if (mysql_error()) jsonError("database error - " . mysql_error());
	foreach ($updates as $update) {
		mysql_query($update);
		if (mysql_error()) jsonError("database error - " . mysql_error());
	}

	if (count($inserts)) {
		mysql_query("insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, dtl_Geo) values " . join(",", $inserts));
		$first_bd_id = mysql_insert_id();
		return range($first_bd_id, $first_bd_id + count($inserts) - 1);
	}else{
		return array();
	}
}

function doTagInsertion($recordID, $bkmkID, $tagString) {
	$usrID = get_user_id();
	//get all existing personal tags for this record
	$kwds = mysql__select_array("usrRecTagLinks, usrTags",
	"tag_Text", "rtl_RecID=$recordID and tag_ID=rtl_TagID and tag_UGrpID=$usrID order by rtl_Order, rtl_ID");
	$existingTagString = join(",", $kwds);

	// if tags are already there Nothing to do
	if (strtolower(trim($tagString)) == strtolower(trim($existingTagString))) return;


	$tags = array_filter(array_map("trim", explode(",", str_replace("\\", "/", $tagString))));     // replace backslashes with forwardslashes
	// create a map of this user's personal tags to tagIDs
	$tagMap = mysql__select_assoc("usrTags", "trim(lower(tag_Text))", "tag_ID",
	"tag_UGrpID=".get_user_id()." and tag_Text in (\"".join("\",\"", array_map("addslashes", $tags))."\")");

	//create an ordered list of personal tag ids
	$tag_ids = array();
	foreach ($tags as $tag) {
		if (@$tagMap[strtolower($tag)]) {// existing tag
			$tag_id = $tagMap[strtolower($tag)];
		} else { // new tag so add it
			mysql_query("insert into usrTags (tag_Text, tag_UGrpID) values (\"" . addslashes($tag) . "\", $usrID)");
			$tag_id = mysql_insert_id();
		}
		array_push($tag_ids, $tag_id);
	}

	// Delete all non-workgroup personal tags for this record
	mysql_query("delete usrRecTagLinks from usrRecTagLinks, usrTags where rtl_RecID=$recordID and tag_ID=rtl_TagID and tag_UGrpID =$usrID");

	if (count($tag_ids) > 0) {
		$query = "";
		for ($i=0; $i < count($tag_ids); ++$i) {
			if ($query) $query .= ", ";
			$query .= "($recordID, ".($i+1).", ".$tag_ids[$i].")";
		}
		$query = "insert into usrRecTagLinks (rtl_RecID, rtl_Order, rtl_TagID) values " . $query;
		mysql_query($query);
	}
}

function doWgTagInsertion($recordID, $wgTagIDs) {
	if ($wgTagIDs != ""  &&  ! preg_match("/^\\d+(?:,\\d+)*$/", $wgTagIDs)) return;

	if ($wgTagIDs) {
		mysql_query("delete usrRecTagLinks from usrRecTagLinks, usrTags, ".USERS_DATABASE.".sysUsrGrpLinks where rtl_RecID=$recordID and rtl_TagID=tag_ID and tag_UGrpID=ugl_GroupID and ugl_UserID=".get_user_id()." and tag_ID not in ($wgTagIDs)");
		if (mysql_error()) jsonError("database error - " . mysql_error());
	} else {
		mysql_query("delete usrRecTagLinks from usrRecTagLinks, usrTags, ".USERS_DATABASE.".sysUsrGrpLinks where rtl_RecID=$recordID and rtl_TagID=tag_ID and tag_UGrpID=ugl_GroupID and ugl_UserID=".get_user_id());
		if (mysql_error()) jsonError("database error - " . mysql_error());
		return;
	}

	$existingKeywordIDs = mysql__select_assoc("usrRecTagLinks, usrTags, ".USERS_DATABASE.".sysUsrGrpLinks", "rtl_TagID", "1", "rtl_RecID=$recordID and rtl_TagID=tag_ID and tag_UGrpID=ugl_GroupID and ugl_UserID=".get_user_id());
	$newKeywordIDs = array();
	foreach (explode(",", $wgTagIDs) as $kwdID) {
		if (! @$existingKeywordIDs[$kwdID]) array_push($newKeywordIDs, $kwdID);
	}

	if ($newKeywordIDs) {
		mysql_query("insert into usrRecTagLinks (rtl_TagID, rtl_RecID) select tag_ID, $recordID from usrTags, ".USERS_DATABASE.".sysUsrGrpLinks where tag_UGrpID=ugl_GroupID and ugl_UserID=".get_user_id()." and tag_ID in (" . join(",", $newKeywordIDs) . ")");
		if (mysql_error()) jsonError("database error - " . mysql_error());
	}
}


function handleNotifications($recordID, $removals, $additions) {
	// removals are encoded as just the notification ID# ... easy!
	$removals = array_map("intval", $removals);
	if ($removals) {
		mysql_query("delete from usrReminders where rem_ID in (" . join(",",$removals) . ") and rem_RecID=$recordID and rem_OwnerUGrpID=" . get_user_id());
	}

	// additions have properties
	// {.user OR .workgroup OR ..email}, .date, .frequency and .message
	$newIDs = array();
	foreach ($additions as $addition) {
		// Input-checking ... this is all done in JS too, so if somebody gets this far they've been doing funny buggers.

		if (! (@$addition["user"] || @$addition["workgroup"] || @$addition["email"])) {
			array_push($newIDs, array("error" => "invalid recipient"));
			continue;
		}

		if (! strtotime(@$addition["date"])) {
			array_push($newIDs, array("error" => "invalid start date"));
			continue;
		}

		if (! preg_match('/^(?:once|daily|weekly|monthly|annually)$/', @$addition["frequency"])) {
			array_push($newIDs, array("error" => "invalid notification frequency"));
			continue;
		}

		$insertVals = array(
		"rem_RecID" => $recordID,
		"rem_OwnerUGrpID" => get_user_id(),
		"rem_StartDate" => date('Y-m-d', strtotime($startDate)),
		"rem_Message" => $addition["message"]
		);

		if (@$addition["user"]) {
			if (! mysql__select_array(USERS_DATABASE.".sysUGrps usr", "usr.ugr_ID", "usr.ugr_ID=".intval($addition["user"])." and usr.ugr_Type = 'User' and  and usr.ugr_Enabled='y'")) {
				array_push($newIDs, array("error" => "invalid recipient"));
				continue;
			}
			$insertVals["rem_ToUserID"] = intval($addition["user"]);
		}
		else if (@$addition["workgroup"]) {
			if (! mysql__select_array(USERS_DATABASE.".sysUsrGrpLinks", "ugl_ID", "ugl_GroupID=".intval($addition["workgroup"])." and ugl_UserID=" . get_user_id())) {
				array_push($newIDs, array("error" => "invalid recipient"));
				continue;
			}
			$insertVals["rem_ToWorkgroupID"] = intval($addition["workgroup"]);
		}
		else if (@$addition["email"]) {
			$insertVals["rem_Email"] = $addition["email"];
		}
		else {	// can't happen
			array_push($newIDs, array("error" => "invalid recipient"));
			continue;
		}

		mysql__insert("usrReminders", $insertVals);
		array_push($newIDs, array("id" => mysql_insert_id()));
	}

	return $newIDs;
}


function handleComments($recordID, $removals, $modifications, $additions) {
	// removals are encoded as just the comments ID# ... easy.
	if ($removals) {
		$removals = array_map("intval", $removals);
		mysql_query("update recThreadedComments set cmt_Deleted=1
		where cmt_OwnerUGrpID=".get_user_id()." and cmt_RecID=$recordID and cmt_ID in (".join(",",$removals).")");
	}

	// modifications have the values
	// .id, .parentComment, .text
	foreach ($modifications as $modification) {
		// note that parentComment (of course) cannot be modified
		mysql__update("recThreadedComments", "cmt_ID=".intval($modification["id"])." and cmt_OwnerUGrpID=".get_user_id(),
		array("cmt_Text" => $modification["text"], "cmt_Modified" => date('Y-m-d H:i:s')));
	}

	// additions are the same as modifications, except that the COMMENT-ID is blank (of course!)
	$newIDs = array();
	foreach ($additions as $addition) {
		$parentID = intval($addition["parentComment"]);

		// do a sanity check first: does this reply make sense?
		$parentTest = $parentID? "cmt_ID=$parentID" : "cmt_ID is null";
		if (! mysql__select_array("Records left join recThreadedComments on rec_ID=cmt_RecID and $parentTest", "rec_ID", "rec_ID=$recordID and $parentTest")) {
			array_push($newIDs, array("error" => "invalid parent comments"));
			continue;
		}

		if (! $parentID) { $parentId = NULL; }

		mysql__insert("recThreadedComments", array("cmt_Text" => $addition["text"], "cmt_Added" => date('Y-m-d H:i:s'), "cmt_OwnerUGrpID" => get_user_id(),
		"cmt_ParentCmtID" => $parentID, "cmt_RecID" => $recordID));
		array_push($newIDs, array("id" => mysql_insert_id()));
	}

	return $newIDs;
}

?>
