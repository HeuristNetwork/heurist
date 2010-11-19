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


/* Take rec_id or bkm_ID, fill in window.HEURIST.record.bibID and window.HEURIST.record.bkmkID as appropriate */
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
	// Look at the request parameters rec_id and bkm_ID,
	// return the actual rec_id and bkm_ID as the user has access to them

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
		$res = mysql_query('select rec_id, bkm_ID from records left join usrBookmarks on bkm_recID=rec_id and bkm_UGrpID='.get_user_id().' where rec_id='.$rec_id);
		$row = mysql_fetch_assoc($res);
		$rec_id = intval($row['rec_id']);
		$bkm_ID = intval($row['bkm_ID']);
	}

	if (! $rec_id  &&  intval(@$_REQUEST['bkmk_id'])) {
		$bkm_ID = intval($_REQUEST['bkmk_id']);
		$res = mysql_query('select bkm_ID, rec_id from usrBookmarks left join records on bkm_recID=rec_id where bkm_ID='.$bkm_ID.' and bkm_UGrpID='.get_user_id());
		$row = mysql_fetch_assoc($res);
		$bkm_ID = intval($row['bkm_ID']);
		$rec_id = intval($row['rec_id']);
	}

	return array($rec_id, $bkm_ID, $replaced);
}


function getBaseProperties($rec_id, $bkm_ID) {
	// Return an array of the basic scalar properties for this record / bookmark
	if (!$rec_id && !$bkm_ID) return array("error"=>"invalid parameters passed to getBaseProperties");
	if ($bkm_ID) {
		$res = mysql_query('select rec_id, rec_title as title, rt_name as reftype, rt_id as reftypeID, rec_url as url, grp.ugr_ID as workgroupID, grp.ugr_Name as workgroup, rec_scratchpad as notes, rec_visibility as visibility, bkm_PwdReminder as passwordReminder, bkm_Rating as rating, rec_modified, rec_temporary from usrBookmarks left join records on bkm_recID=rec_id and bkm_UGrpID='.get_user_id().' left join rec_types on rt_id = rec_type left join '.USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=rec_wg_id where bkm_ID='.$bkm_ID);
	} else if ($rec_id) {
		$res = mysql_query('select rec_id, rec_title as title, rt_name as reftype, rt_id as reftypeID, rec_url as url, grp.ugr_ID as workgroupID, grp.ugr_Name as workgroup, rec_scratchpad as notes, rec_visibility as visibility, rec_modified, rec_temporary from records left join usrBookmarks on bkm_recID=rec_id left join rec_types on rt_id = rec_type left join '.USERS_DATABASE.'.sysUGrps grp on grp.ugr_ID=rec_wg_id where rec_id='.$rec_id);
	}

	$row = mysql_fetch_assoc($res);
	$rec_id = $row["rec_id"];
	$props = array();

	if ($rec_id) $props["bibID"] = $rec_id;
	if ($bkm_ID) $props["bkmkID"] = $bkm_ID;
	$props["title"] = $row["title"];
	$props["reftype"] = $row["reftype"];
	$props["reftypeID"] = $row["reftypeID"];
	$props["url"] = $row["url"];
	$props["moddate"] = $row["rec_modified"];
	$props["isTemporary"] = $row["rec_temporary"]? true : false;

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
	// Get all rec_details entries for this entry,
	// as an array.
	// File entries have file data associated,
	// geo entries have geo data associated,
	// record references have title data associated.

	$res = mysql_query("select rd_id, rd_type, rd_val, rec_title, rd_file_id, rdl_value,
	                           if(rd_geo is not null, astext(envelope(rd_geo)), null) as envelope,
	                           if(rd_geo is not null, astext(rd_geo), null) as rd_geo
	                      from rec_details
	                 left join rec_detail_types on rdt_id=rd_type
	                 left join records on rec_id=rd_val and rdt_type='resource'
	                 left join rec_detail_lookups on rdt_type='enum' and rdl_id = rd_val
	                     where rd_rec_id = $rec_id order by rd_id");
	$bibDetails = array();
	while ($row = mysql_fetch_assoc($res)) {
		$detail = array();

		$detail["id"] = $row["rd_id"];
		$detail["value"] = $row["rd_val"];
		if (array_key_exists('rdl_value',$row) && $row['rdl_value']) $detail["enumValue"] = $row["rdl_value"];	// saw Enum change
		if ($row["rec_title"]) $detail["title"] = $row["rec_title"];

		if ($row["rd_file_id"]) {
			$fileRes = mysql_query("select * from files where file_id=" . intval($row["rd_file_id"]));
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
			if ($row["rd_val"] == "l"  &&  preg_match("/^LINESTRING[(]([^ ]+) ([^ ]+),.*,([^ ]+) ([^ ]+)[)]$/", $row["rd_geo"], $matches)) {
				list($dummy, $minX, $minY, $maxX, $maxY) = $matches;
			}

			switch ($row["rd_val"]) {
			  case "p": $type = "point"; break;
			  case "pl": $type = "polygon"; break;
			  case "c": $type = "circle"; break;
			  case "r": $type = "rectangle"; break;
			  case "l": $type = "path"; break;
			  default: $type = "unknown";
			}
			$wkt = $row["rd_val"] . " " . $row["rd_geo"];	// well-known text value
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

		if (! @$bibDetails[$row["rd_type"]]) $bibDetails[$row["rd_type"]] = array();
		array_push($bibDetails[$row["rd_type"]], $detail);
	}

	return $bibDetails;
}


function getAllReminders($rec_id) {
	// Get any reminders as an array;
	if (! $rec_id) return array();

	// ... MYSTIFYINGLY these are stored by rec_id+user_id, not bkm_ID
	$res = mysql_query("select * from reminders where rem_rec_id=$rec_id and rem_owner_id=".get_user_id()." order by rem_startdate");

	$reminders = array();
	if (mysql_num_rows($res) > 0) {
		while ($rem = mysql_fetch_assoc($res)) {

			array_push($reminders, array(
				"id" => $rem["rem_id"],
				"user" => $rem["rem_usr_id"],
				"group" => $rem["rem_wg_id"],
				"colleagueGroup" => $rem["rem_cgr_id"],
				"email" => $rem["rem_email"],
				"message" => $rem["rem_message"],
				"when" => $rem["rem_startdate"],
				"frequency" => $rem["rem_freq"]
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
		$res = mysql_query("select rec_url from records where rec_id=".$rec_id);
		$row = mysql_fetch_assoc($res);
		if (preg_match("!(acl.arts.usyd.edu.au|heuristscholar.org)/tmwiki!", @$row["rec_url"])) {	//FIXME: this needs to be configurable or generic for installations
			array_push($wikis, array("Public", preg_replace("!.*/!", "", $row["rec_url"])));
			array_push($wikiNames, preg_replace("!.*/!", "", $row["rec_url"]));
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
	$res = mysql_query("select rcon_target_rt_id as rt_id, rcon_rdt_id as rdt_id,
						rcon_rdl_ids as rdl_ids, rcon_ont_id as ont_id, rcon_order, rcon_limit
						from rec_constraints
						where rcon_source_rt_id=$recType
						order by rcon_rdt_id, rcon_target_rt_id, rcon_order ");
	while($row = mysql_fetch_assoc($res)) {
		if (! @$rcons[$row["rdt_id"]]) {
			$rcons[$row["rdt_id"]] = array();
		}
		if (! @$rcons[$row["rdt_id"]][$row["rt_id"]]) {
			$rcons[$row["rdt_id"]][$row["rt_id"]] = array();
		}
		array_push($rcons[$row["rdt_id"]][$row["rt_id"]],$row);
	}
	return $rcons;
}
?>
