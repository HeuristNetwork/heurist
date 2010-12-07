<?php
/*<!--  bib.php

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

  -->
*/


/* Take rec_ID or bkm_ID, fill in window.HEURIST.record.bibID and window.HEURIST.record.bkmkID as appropriate */
/* FIXME: leave around some useful error messages */

if (! defined("SAVE_URI")) {
	define("SAVE_URI", "disabled");
}

/* define JSON_RESPONSE to strip out the JavaScript commands;
 * just output the .record object definition
 */

if (! defined("JSON_RESPONSE")) {
	require_once(dirname(__FILE__)."/../relationships/relationships.php");
	require_once(dirname(__FILE__)."/../../common/connect/cred.php");
	require_once(dirname(__FILE__)."/../../common/connect/db.php");
	if (! is_logged_in()) return;

	header('Content-type: text/javascript');
}

mysql_connection_db_select(DATABASE);

list($rec_id, $bkm_ID, $replaced) = findRecordIDs();

if (! $rec_id) {
	// record does not exist
	$record = null;
} else if ($replaced) {
	// the record has been deprecated
	$record = array();
	$record["replacedBy"] = $rec_id;
} else {
	$record = getBaseProperties($rec_id, $bkm_ID);
	if (@$record["workgroupID"]  &&  $record["workgroupVisibility"] == "Hidden"  &&  ! $_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']["user_access"][$record["workgroupID"]]) {
		// record is hidden and user is not a member of owning workgroup
		$record = array();
		$record["denied"] = true;
	} else {
		$record["bdValuesByType"] = getAllBibDetails($rec_id);
		$record["reminders"] = getAllReminders($rec_id);
		$record["wikis"] = getAllWikis($rec_id, $bkm_ID);
		$record["comments"] = getAllComments($rec_id);
		$record["workgroupTags"] = getAllworkgroupTags($rec_id);
		$record["relatedRecords"] = getAllRelatedRecords($rec_id);
		$record["rtConstraintsByDType"] = getConstraintsByRdt($record['reftypeID']);
		$record["retrieved"] = date('Y-m-d H:i:s');	// the current time according to the server
	}
}

?>

<?php if (! defined("JSON_RESPONSE")) { ?>
if (! top.HEURIST) top.HEURIST = {};
if (! top.HEURIST.edit) top.HEURIST.edit = {};
top.HEURIST.edit.record = <?= json_format($record) ?>;
top.HEURIST.fireEvent(window, "heurist-record-loaded");
<?php } else { ?>
<?= json_format($record) ?>
<?php }
/***** END OF OUTPUT *****/


function findRecordIDs() {
	// Look at the request parameters rec_ID and bkm_ID,
	// return the actual rec_ID and bkm_ID as the user has access to them

	/* chase down replaced-by-bib-id references */
	$replaced = false;
	if (intval(@$_REQUEST["bib_id"])) {
		$res = mysql_query("select new_rec_id from aliases where old_rec_id=" . intval(@$_REQUEST["bib_id"]));
		$recurseLimit = 10;
		while (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_row($res);
			$_REQUEST["bib_id"] = $row[0];
			$replaced = true;
			$res = mysql_query("select new_rec_id from aliases where old_rec_id=" . $_REQUEST["bib_id"]);

			if ($recurseLimit-- === 0) { return array(); }
		}
	}

	$rec_id = 0;
	$bkm_ID = 0;
	if (intval(@$_REQUEST['bib_id'])) {
		$rec_id = intval($_REQUEST['bib_id']);
		$res = mysql_query('select rec_ID, bkm_ID from Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id().' where rec_ID='.$rec_id);
		$row = mysql_fetch_assoc($res);
		$rec_id = intval($row['rec_ID']);
		$bkm_ID = intval($row['bkm_ID']);
	}

	if (! $rec_id  &&  intval(@$_REQUEST['bkmk_id'])) {
		$bkm_ID = intval($_REQUEST['bkmk_id']);
		$res = mysql_query('select bkm_ID, rec_ID from usrBookmarks left join Records on bkm_recID=rec_ID where bkm_ID='.$bkm_ID.' and bkm_UGrpID='.get_user_id());
		$row = mysql_fetch_assoc($res);
		$bkm_ID = intval($row['bkm_ID']);
		$rec_id = intval($row['rec_ID']);
	}

	return array($rec_id, $bkm_ID, $replaced);
}


function getBaseProperties($rec_id, $bkm_ID) {
	// Return an array of the basic scalar properties for this record / bookmark
	if (!$rec_id && !$bkm_ID) return array("error"=>"invalid parameters passed to getBaseProperties");
	if ($bkm_ID) {
		$res = mysql_query('select rec_ID, rec_Title as title, rty_Name as reftype, rty_ID as reftypeID, rec_URL as url, grp.ugr_ID as workgroupID, grp.ugr_Name as workgroup, rec_ScratchPad as notes, rec_NonOwnerVisibility as visibility, bkm_PwdReminder as passwordReminder, bkm_Rating as rating, rec_Modified, rec_FlagTemporary from usrBookmarks left join Records on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id().' left join defRecTypes on rty_ID = rec_RecTypeID left join '.USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=rec_OwnerUGrpID where bkm_ID='.$bkm_ID);
	} else if ($rec_id) {
		$res = mysql_query('select rec_ID, rec_Title as title, rty_Name as reftype, rty_ID as reftypeID, rec_URL as url, grp.ugr_ID as workgroupID, grp.ugr_Name as workgroup, rec_ScratchPad as notes, rec_NonOwnerVisibility as visibility, rec_Modified, rec_FlagTemporary from Records left join usrBookmarks on bkm_recID=rec_ID left join defRecTypes on rty_ID = rec_RecTypeID left join '.USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=rec_OwnerUGrpID where rec_ID='.$rec_id);
	}

	$row = mysql_fetch_assoc($res);
	$rec_id = $row["rec_ID"];
	$props = array();

	if ($rec_id) $props["bibID"] = $rec_id;
	if ($bkm_ID) $props["bkmkID"] = $bkm_ID;
	$props["title"] = $row["title"];
	$props["reftype"] = $row["reftype"];
	$props["reftypeID"] = $row["reftypeID"];
	$props["url"] = $row["url"];
	$props["moddate"] = $row["rec_Modified"];
	$props["isTemporary"] = $row["rec_FlagTemporary"]? true : false;

	if (@$row["passwordReminder"]) {
		$props["passwordReminder"] = $row["passwordReminder"];
	}
	if (@$row["rating"]) {
		$props['rating'] = $row['rating'];
	}
	$props["quickNotes"] = @$row["quickNotes"]? $row["quickNotes"] : "";
	if ($row['workgroupID']) {
		$props['workgroupID'] = $row['workgroupID'];
		$props['workgroup'] = $row['workgroup'];
		if ($row['visibility']) $props['workgroupVisibility'] = $row['visibility'];
	}
//	$props['notes'] = $row['notes']; // saw TODO: add code to get personal woots

	if ($bkm_ID) {
		// grab the user tags for this bookmark, as a single comma-delimited string
		$kwds = mysql__select_array("usrRecTagLinks left join usrTags on tag_ID=rtl_TagID", "tag_Text", "rtl_RecID=$rec_id and tag_UGrpID=".get_user_id() . " order by rtl_Order, rtl_ID");
		$props["tagString"] = join(",", $kwds);
	}

	return $props;
}

function getAllBibDetails($rec_id) {
	// Get all recDetails entries for this entry,
	// as an array.
	// File entries have file data associated,
	// geo entries have geo data associated,
	// record references have title data associated.

	$res = mysql_query("select dtl_ID, dtl_DetailTypeID, dtl_Value, rec_Title, dtl_UploadedFileID, trm_Label,
	                           if(dtl_Geo is not null, astext(envelope(dtl_Geo)), null) as envelope,
	                           if(dtl_Geo is not null, astext(dtl_Geo), null) as dtl_Geo
	                      from recDetails
	                 left join defDetailTypes on dty_ID=dtl_DetailTypeID
	                 left join Records on rec_ID=dtl_Value and dty_Type='resource'
	                 left join defTerms on dty_Type='enum' and trm_ID = dtl_Value
	                     where dtl_RecID = $rec_id order by dtl_ID");
	$bibDetails = array();
	while ($row = mysql_fetch_assoc($res)) {
		$detail = array();

		$detail["id"] = $row["dtl_ID"];
		$detail["value"] = $row["dtl_Value"];
		if (array_key_exists('trm_Label',$row) && $row['trm_Label']) $detail["enumValue"] = $row["trm_Label"];	// saw Enum change
		if ($row["rec_Title"]) $detail["title"] = $row["rec_Title"];

		if ($row["dtl_UploadedFileID"]) {
			$fileRes = mysql_query("select * from files where file_id=" . intval($row["dtl_UploadedFileID"]));
			if (mysql_num_rows($fileRes) == 1) {
				$file = mysql_fetch_assoc($fileRes);
				$detail["file"] = array(
					"id" => $file["file_id"],
					"origName" => $file["file_orig_name"],
					"date" => $file["file_id"],
					"mimeType" => $file["file_mimetype"],
					"nonce" => $file["file_nonce"],
					"fileSize" => $file["file_size"],
					"typeDescription" => $file["file_typedescription"]
				);
			}
		}
		else if ($row["envelope"]  &&  preg_match("/^POLYGON[(][(]([^ ]+) ([^ ]+),[^,]*,([^ ]+) ([^,]+)/", $row["envelope"], $poly)) {
			list($match, $minX, $minY, $maxX, $maxY) = $poly;
//error_log($match);
			$x = 0.5 * ($minX + $maxX);
			$y = 0.5 * ($minY + $maxY);

			// This is a bit ugly ... but it is useful.
			// Do things differently for a path -- set minX,minY to the first point in the path, maxX,maxY to the last point
			if ($row["dtl_Value"] == "l"  &&  preg_match("/^LINESTRING[(]([^ ]+) ([^ ]+),.*,([^ ]+) ([^ ]+)[)]$/", $row["dtl_Geo"], $matches)) {
				list($dummy, $minX, $minY, $maxX, $maxY) = $matches;
			}

			switch ($row["dtl_Value"]) {
			  case "p": $type = "point"; break;
			  case "pl": $type = "polygon"; break;
			  case "c": $type = "circle"; break;
			  case "r": $type = "rectangle"; break;
			  case "l": $type = "path"; break;
			  default: $type = "unknown";
			}
			$wkt = $row["dtl_Value"] . " " . $row["dtl_Geo"];	// well-known text value
			$detail["geo"] = array(
				"minX" => $minX,
				"minY" => $minY,
				"maxX" => $maxX,
				"maxY" => $maxY,
				"x" => $x,
				"y" => $y,
				"type" => $type,
				"value" => $wkt
			);
		}

		if (! @$bibDetails[$row["dtl_DetailTypeID"]]) $bibDetails[$row["dtl_DetailTypeID"]] = array();
		array_push($bibDetails[$row["dtl_DetailTypeID"]], $detail);
	}

	return $bibDetails;
}


function getAllReminders($rec_id) {
	// Get any reminders as an array;
	if (! $rec_id) return array();

	// ... MYSTIFYINGLY these are stored by rec_ID+user_id, not bkm_ID
	$res = mysql_query("select * from usrReminders where rem_RecID=$rec_id and rem_OwnerUGrpID=".get_user_id()." order by rem_StartDate");

	$reminders = array();
	if (mysql_num_rows($res) > 0) {
		while ($rem = mysql_fetch_assoc($res)) {

			array_push($reminders, array(
				"id" => $rem["rem_ID"],
				"user" => $rem["rem_ToUserID"],
				"group" => $rem["rem_ToWorkGroupID"],
				"email" => $rem["rem_ToEmail"],
				"message" => $rem["rem_Message"],
				"when" => $rem["rem_StartDate"],
				"frequency" => $rem["rem_Freq"]
			));
		}
	}

	return $reminders;
}

function getAllWikis($rec_id, $bkm_ID) {
	// Get all wikis for this record / bookmark as an array/object

	$wikis = array();
	$wikiNames = array();

	if ($bkm_ID) {
		array_push($wikis, array("Private", "Bookmark:$bkm_ID"));
		array_push($wikiNames, "Bookmark:$bkm_ID");
	}

	if ($rec_id) {
		$res = mysql_query("select rec_URL from Records where rec_ID=".$rec_id);
		$row = mysql_fetch_assoc($res);
		if (preg_match("!(acl.arts.usyd.edu.au|heuristscholar.org)/tmwiki!", @$row["rec_URL"])) {	//FIXME: this needs to be configurable or generic for installations
			array_push($wikis, array("Public", preg_replace("!.*/!", "", $row["rec_URL"])));
			array_push($wikiNames, preg_replace("!.*/!", "", $row["rec_URL"]));
		} else {
			array_push($wikis, array("Public", "Biblio:$rec_id"));
			array_push($wikiNames, "Biblio:$rec_id");
		}

		$res = mysql_query("select grp.ugr_ID, grp.ugr_Name from ".USERS_DATABASE.".sysUsrGrpLinks left join ".USERS_DATABASE.".sysUGrps grp on ugl_GroupID=grp.ugr_ID where ugl_UserID=".get_user_id()." and grp.ugr_Type !='User' order by grp.ugr_Name");
		while ($grp = mysql_fetch_row($res)) {
			array_push($wikis, array(htmlspecialchars($grp[1]), "Biblio:".$rec_id."_Workgroup:".$grp[0]));
			array_push($wikiNames, slash("Biblio:".$rec_id."_Workgroup:".$grp[0]));
		}
	}

	// get a precis for each of the wikis we're dealing with	//FIXME:  tmwikidb is not used??
	$preces = mysql__select_assoc("tmwikidb.tmw_page left join tmwikidb.tmw_revision on rev_id=page_latest left join tmwikidb.tmw_text on old_id=rev_text_id", "page_title", "old_text", "page_title in ('" . join("','", $wikiNames) . "')");
	foreach ($wikis as $id => $wiki) {
		$precis = @$preces[$wiki[1]];	// look-up by wiki page name
		if (strlen($precis) > 100) $precis = substr($precis, 0, 100) . "...";
		array_push($wikis[$id], $precis? $precis : "");
	}

	return $wikis;
}

function getAllComments($rec_id) {
	$res = mysql_query("select cmt_id, cmt_deleted, cmt_text, cmt_parent_cmt_id, cmt_date, cmt_modified, cmt_usr_id, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as Realname from comments left join ".USERS_DATABASE.".sysUGrps usr on cmt_usr_id=usr.ugr_ID where cmt_rec_id = $rec_id order by cmt_date");

	$comments = array();
	while ($cmt = mysql_fetch_assoc($res)) {
		if ($cmt["cmt_deleted"]) {
			/* indicate that the comments exists but has been deleted */
			$comments[$cmt["cmt_id"]] = array(
				"id" => $cmt["cmt_id"],
				"owner" => $cmt["cmt_parent_cmt_id"],
				"deleted" => true
			);
			continue;
		}

		$comments[$cmt["cmt_id"]] = array(
			"id" => $cmt["cmt_id"],
			"text" => $cmt["cmt_text"],
			"owner" => $cmt["cmt_parent_cmt_id"],	/* comments that owns this one (i.e. parent, just like in Dickensian times) */
			"added" => $cmt["cmt_date"],
			"modified" => $cmt["cmt_modified"],
			"user" => $cmt["Realname"],
			"userID" => $cmt["cmt_usr_id"],
			"deleted" => false
		);
	}

	return $comments;
}

function getAllworkgroupTags($rec_id) {
// FIXME: should limit this just to workgroups that the user is in
	$res = mysql_query("select tag_ID from usrRecTagLinks, usrTags where rtl_TagID=tag_ID and rtl_RecID=$rec_id");
	$kwd_ids = array();
	while ($row = mysql_fetch_row($res)) array_push($kwd_ids, $row[0]);
	return $kwd_ids;
}

function getConstraintsByRdt($recType) {
	$rcons = array();
	$res = mysql_query("select rcs_TargetRectypeID as rty_ID, rcs_DetailtypeID as dty_ID,
						rcs_TermIDs as trm_ids, rcs_VocabID as vcb_ID, rcs_Order, rcs_TermLimit, rcs_RelationshipsLimit
						from defRelationshipConstraints
						where rcs_SourceRectypeID=$recType
						order by rcs_DetailtypeID, rcs_TargetRectypeID, rcs_Order ");
	while($row = mysql_fetch_assoc($res)) {
		if (! @$rcons[$row["dty_ID"]]) {
			$rcons[$row["dty_ID"]] = array();
		}
		if (! @$rcons[$row["dty_ID"]][$row["rty_ID"]]) {
			$rcons[$row["dty_ID"]][$row["rty_ID"]] = array();
		}
		array_push($rcons[$row["dty_ID"]][$row["rty_ID"]],$row);
	}
	return $rcons;
}
?>
