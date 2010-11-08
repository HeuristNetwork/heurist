<?php
	/*<!-- saving.php

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
require_once(dirname(__FILE__)."/../search/saved/loading.php");

function saveRecord($id, $type, $url, $notes, $group, $vis, $personalised, $pnotes, $crate, $irate, $qrate, $tags, $keywords, $details, $notifyREMOVE, $notifyADD, $commentREMOVE, $commentMOD, $commentADD, &$nonces=null, &$retitleRecs=null) {
	$id = intval($id);
	$group = intval($group);
	if ($group) {
		$res = mysql_query("select * from ".USERS_DATABASE.".UserGroups where ug_user_id=" . get_user_id() . " and ug_group_id=" . $group);
		if (mysql_num_rows($res) < 1) jsonError("invalid workgroup");
	}

	$type = intval($type);
	if ($id  &&  ! $type) {
		jsonError("cannot change existing record to private note");
	}

	$now = date('Y-m-d H:i:s');

	// public records data
	if (! $id) {
		mysql__insert("records", array(
		"rec_type" => $type,
		"rec_url" => $url,
		"rec_scratchpad" => $notes,
		"rec_wg_id" => $group,
		"rec_visibility" => $group? ($vis? "Viewable" : "Hidden") : NULL,
		"rec_added_by_usr_id" => get_user_id(),
		"rec_added" => $now,
		"rec_modified" => $now
		));
		if (mysql_error()) jsonError("database write error - " . mysql_error());

		$id = mysql_insert_id();
	}else{
		$res = mysql_query("select * from records left join ".USERS_DATABASE.".UserGroups on ug_group_id=rec_wg_id and ug_user_id=".get_user_id()." where rec_id=$id");
		$bib = mysql_fetch_assoc($res);

		if ($group != $bib["rec_wg_id"]) {
			if ($bib["rec_wg_id"] > 0  &&  $bib["ug_role"] != "admin") {
				// user is trying to change the workgroup when they are not an admin
				jsonError("user is not a workgroup admin");
			} else if (! is_admin()) {
				// you must be an instance admin to change a public record into a workgroup record
				jsonError("user does not have sufficient authority to change public record to workgroup record");
			}
		}
		if (! $group) { $vis = NULL; }

		mysql__update("records", "rec_id=$id", array(
		"rec_type" => $type,
		"rec_url" => $url,
		"rec_scratchpad" => $notes,
		"rec_wg_id" => $group,
		"rec_visibility" => $group? ($vis? "Viewable" : "Hidden") : NULL,
		"rec_modified" => $now
		));
		if (mysql_error()) jsonError("database write error" . mysql_error());
	}

	// public rec_details data
	if ($details) {
		$bdIDs = doDetailInsertion($id, $details, $type, $group, $nonces, $retitleRecs);
	}

	// check that all the required fields are present
	$res = mysql_query("select rdr_id from rec_detail_requirements left join rec_details on rd_rec_id=$id and rdr_rdt_id=rd_type where rdr_rec_type=$type and rdr_required='Y' and rd_id is null");
	if (mysql_num_rows($res) > 0) {
		// at least one missing field
		jsonError("record is missing required field(s)");
	}
	$res = mysql_query("select rdr_id from rec_detail_requirements_overrides left join rec_details on rd_rec_id=$id and rdr_rdt_id=rd_type where (rdr_wg_id = 0 or rdr_wg_id=$group) and rdr_wg_id = rdr_rec_type=$type and rdr_required='Y' and rd_id is null");
	if (mysql_num_rows($res) > 0) {
		// at least one missing field
		jsonError("record is missing required field(s)");
	}

	// calculate title, do an update
	$mask = mysql__select_array("rec_types", "rt_title_mask", "rt_id=$type");  $mask = $mask[0];
	$title = fill_title_mask($mask, $id, $type);
	if ($title) {
		mysql_query("update records set rec_title = '" . addslashes($title) . "' where rec_id = $id");
	}

	// Update memcache: we can do this here since it's only the public data that we cache.
	updateCachedRecord($id);

	// private data
	$bkmk = @mysql_fetch_row(mysql_query("select bkm_ID from usrBookmarks where pers_usr_id=" . get_user_id() . " and pers_rec_id=" . $id));
	$bkm_ID = @$bkmk[0];
	if ($personalised) {
		if (! $bkm_ID) {
			// Record is not yet bookmarked, but we want it to be
			mysql_query("insert into usrBookmarks (bkm_Added,pers_modified,pers_usr_id,pers_rec_id) values (now(),now(),".get_user_id().",$id)");
			if (mysql_error()) jsonError("database error - " . mysql_error());
			$bkm_ID = mysql_insert_id();
		}

		mysql__update("usrBookmarks", "bkm_ID=$bkm_ID", array(
		"pers_notes" => $pnotes,
		"pers_content_rating" => $crate,
		"pers_interest_rating" => $irate,
		"pers_quality_rating" => $qrate,
		"pers_modified" => date('Y-m-d H:i:s')
		));

		doTagInsertion($id, $bkm_ID, $tags);
	}
	else if ($bkm_ID) {
		// Record is bookmarked, but the user doesn't want it to be
		mysql_query("delete usrBookmarks, keyword_links from usrBookmarks left join keyword_links on kwl_pers_id = bkm_ID where bkm_ID=$bkm_ID and pers_rec_id=$id and pers_usr_id=" . get_user_id());
		if (mysql_error()) jsonError("database error - " . mysql_error());
	}

	doKeywordInsertion($id, $keywords);

	if ($notifyREMOVE  ||  $notifyADD) {
		$notifyIDs = handleNotifications($id, $notifyREMOVE, $notifyADD);
	}

	if ($commentREMOVE  ||  $commentMOD  ||  $commentADD) {
		$commentIDs = handleComments($id, $commentREMOVE, $commentMOD, $commentADD);
	}


	$rval = array("bibID" => $id, "bkmkID" => $bkm_ID, "modified" => $now);
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
crate
irate
qrate
tags
keywords
details : [t:xxx] => [ [bd:yyy] => val ]*

*/


function doDetailInsertion($bibID, $details, $recordType, $group, &$nonces, &$retitleRecs) {
	/* $nonces :  nonce-to-bibID mapping, makes it possible to resolve rec_details values of reference variety */
	/* $retitleRecs : set of records whos titles could be out of date and need recalc */
	// do a double-pass to grab the expected varieties for each bib-detail-type we encounter

	$types = array();
	foreach ($details as $type => $pairs) {
		if (substr($type, 0, 2) != "t:") continue;
		if (! ($bdtID = intval(substr($type, 2)))) continue;
		array_push($types, $bdtID);
	}
	$typeVarieties = mysql__select_assoc("rec_detail_types", "rdt_id", "rdt_type", "rdt_id in (" . join($types, ",") . ")");
	$repeatable = mysql__select_assoc("rec_detail_requirements", "rdr_rdt_id", "rdr_repeatable", "rdr_rdt_id in (" . join($types, ",") . ") and rdr_rec_type=" . $recordType);

	$updates = array();
	$inserts = array();
	$dontDeletes = array();
	foreach ($details as $type => $pairs) {
		if (substr($type, 0, 2) != "t:") continue;
		if (! ($bdtID = intval(substr($type, 2)))) continue;

		foreach ($pairs as $bdID => $val) {
			if (substr($bdID, 0, 3) == "bd:") {
				// this detail corresponds to an existing rec_details: remember its existing rd_id
				if (! ($bdID = intval(substr($bdID, 3)))) continue;
			}
			else {
				if ($bdID != intval($bdID)) continue;
				// simple case: this is a new detail (no existing rd_id)
				$bdID = "";
			}
			$val = trim($val);

			$bdVal = $bdFileID = $bdGeo = "NULL";
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
					// validate that the id is for the given detail type.
					if (mysql_num_rows(mysql_query("select rdl_id from rec_detail_lookups where rdl_rdt_id=$bdtID and rdl_id='".$val."'")) <= 0)
					jsonError("invalid enumeration value \"$val\"");
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

					if (mysql_num_rows(mysql_query("select rec_id from records where (! rec_wg_id or rec_wg_id=$group) and rec_id=".intval($val))) <= 0)
					jsonError("invalid resource #".intval($val));
					$bdVal = intval($val);
					break;

				case "file":
					if (mysql_num_rows(mysql_query("select file_id from files where file_id=".intval($val))) <= 0)
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

				default:
					// ???
					if ($bdID) array_push($dontDeletes, $bdID);
					continue;
			}

			if ($bdID) {
				array_push($updates, "update rec_details set rd_val=$bdVal, rd_file_id=$bdFileID, rd_geo=$bdGeo where rd_id=$bdID and rd_type=$bdtID and rd_rec_id=$bibID");
				array_push($dontDeletes, $bdID);
			}
			else {
				array_push($inserts, "($bibID, $bdtID, $bdVal, $bdFileID, $bdGeo)");
			}
		}
	}
	$deleteDetailQuery = "delete from rec_details where rd_rec_id=$bibID";
	if (count($dontDeletes)) $deleteDetailQuery .= " and rd_id not in (" . join(",", $dontDeletes) . ")";
	mysql_query($deleteDetailQuery);
	if (mysql_error()) jsonError("database error - " . mysql_error());

	if (mysql_error()) jsonError("database error - " . mysql_error());
	foreach ($updates as $update) {
		mysql_query($update);
		if (mysql_error()) jsonError("database error - " . mysql_error());
	}

	if (count($inserts)) {
		mysql_query("insert into rec_details (rd_rec_id, rd_type, rd_val, rd_file_id, rd_geo) values " . join(",", $inserts));
		$first_bd_id = mysql_insert_id();
		return range($first_bd_id, $first_bd_id + count($inserts) - 1);
	}else{
		return array();
	}
}


function doTagInsertion($bibID, $bkmkID, $tagString) {
	$kwds = mysql__select_array("keyword_links, keywords",
	"kwd_name", "kwl_pers_id=$bkmkID and kwd_id=kwl_kwd_id and kwd_usr_id=".get_user_id()." order by kwl_order, kwl_id");
	$existingTagString = join(",", $kwds);

	// Nothing to do here
	if (strtolower(trim($tagString)) == strtolower(trim($existingTagString))) return;


	$tags = array_filter(array_map("trim", explode(",", str_replace("\\", "/", $tagString))));     // replace backslashes with forwardslashes
	$tagMap = mysql__select_assoc("keywords", "trim(lower(kwd_name))", "kwd_id",
	"kwd_usr_id=".get_user_id()." and kwd_name in (\"".join("\",\"", array_map("addslashes", $tags))."\")");

	$kwd_ids = array();
	foreach ($tags as $tag) {
		if (@$tagMap[strtolower($tag)]) {
			$kwd_id = $tagMap[strtolower($tag)];
		} else {
			mysql_query("insert into keywords (kwd_name, kwd_usr_id) values (\"" . addslashes($tag) . "\", " . get_user_id() . ")");
			$kwd_id = mysql_insert_id();
		}
		array_push($kwd_ids, $kwd_id);
	}

	// Delete all non-workgroup keywords for this bookmark
	mysql_query("delete keyword_links from keyword_links, keywords where kwl_pers_id=$bkmkID and kwd_id=kwl_kwd_id and kwd_wg_id is null");

	if (count($kwd_ids) > 0) {
		$query = "";
		for ($i=0; $i < count($kwd_ids); ++$i) {
			if ($query) $query .= ", ";
			$query .= "($bkmkID, $bibID, ".($i+1).", ".$kwd_ids[$i].")";
		}
		$query = "insert into keyword_links (kwl_pers_id, kwl_rec_id, kwl_order, kwl_kwd_id) values " . $query;
		mysql_query($query);
	}
}

function doKeywordInsertion($bibID, $keywordIDs) {
	if ($keywordIDs != ""  &&  ! preg_match("/^\\d+(?:,\\d+)*$/", $keywordIDs)) return;

	if ($keywordIDs) {
		mysql_query("delete keyword_links from keyword_links, keywords, ".USERS_DATABASE.".UserGroups where kwl_rec_id=$bibID and kwl_kwd_id=kwd_id and kwd_wg_id=ug_group_id and ug_user_id=".get_user_id()." and kwd_id not in ($keywordIDs)");
		if (mysql_error()) jsonError("database error - " . mysql_error());
	} else {
		mysql_query("delete keyword_links from keyword_links, keywords, ".USERS_DATABASE.".UserGroups where kwl_rec_id=$bibID and kwl_kwd_id=kwd_id and kwd_wg_id=ug_group_id and ug_user_id=".get_user_id());
		if (mysql_error()) jsonError("database error - " . mysql_error());
		return;
	}

	$existingKeywordIDs = mysql__select_assoc("keyword_links, keywords, ".USERS_DATABASE.".UserGroups", "kwl_kwd_id", "1", "kwl_rec_id=$bibID and kwl_kwd_id=kwd_id and kwd_wg_id=ug_group_id and ug_user_id=".get_user_id());
	$newKeywordIDs = array();
	foreach (explode(",", $keywordIDs) as $kwdID) {
		if (! @$existingKeywordIDs[$kwdID]) array_push($newKeywordIDs, $kwdID);
	}

	if ($newKeywordIDs) {
		mysql_query("insert into keyword_links (kwl_kwd_id, kwl_rec_id) select kwd_id, $bibID from keywords, ".USERS_DATABASE.".UserGroups where kwd_wg_id=ug_group_id and ug_user_id=".get_user_id()." and kwd_id in (" . join(",", $newKeywordIDs) . ")");
		if (mysql_error()) jsonError("database error - " . mysql_error());
	}
}


function handleNotifications($bibID, $removals, $additions) {
	// removals are encoded as just the notification ID# ... easy!
	$removals = array_map("intval", $removals);
	if ($removals) {
		mysql_query("delete from reminders where rem_id in (" . join(",",$removals) . ") and rem_rec_id=$bibID and rem_owner_id=" . get_user_id());
	}

	// additions have properties
	// {.user OR .workgroup OR .colleagueGroup OR .email}, .date, .frequency and .message
	$newIDs = array();
	foreach ($additions as $addition) {
		// Input-checking ... this is all done in JS too, so if somebody gets this far they've been doing funny buggers.

		if (! (@$addition["user"] || @$addition["workgroup"] || @$addition["colleagueGroup"] || @$addition["email"])) {
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
		"rem_rec_id" => $bibID,
		"rem_owner_id" => get_user_id(),
		"rem_startdate" => date('Y-m-d', strtotime($startDate)),
		"rem_message" => $addition["message"]
		);

		if (@$addition["user"]) {
			if (! mysql__select_array(USERS_DATABASE.".Users", "Id", "Id=".intval($addition["user"])." and Active='Y'")) {
				array_push($newIDs, array("error" => "invalid recipient"));
				continue;
			}
			$insertVals["rem_usr_id"] = intval($addition["user"]);
		}
		else if (@$addition["colleagueGroup"]) {
			if (! mysql__select_array("coll_groups", "cgr_id", "cgr_id=".intval($addition["colleagueGroup"])." and cgr_owner_id=" . get_user_id())) {
				array_push($newIDs, array("error" => "invalid recipient"));
				continue;
			}
			$insertVals["rem_cgr_id"] = intval($addition["colleagueGroup"]);
		}
		else if (@$addition["workgroup"]) {
			if (! mysql__select_array(USERS_DATABASE.".UserGroups", "ug_id", "ug_group_id=".intval($addition["workgroup"])." and ug_user_id=" . get_user_id())) {
				array_push($newIDs, array("error" => "invalid recipient"));
				continue;
			}
			$insertVals["rem_wg_id"] = intval($addition["workgroup"]);
		}
		else if (@$addition["email"]) {
			$insertVals["rem_email"] = $addition["email"];
		}
		else {	// can't happen
			array_push($newIDs, array("error" => "invalid recipient"));
			continue;
		}

		mysql__insert("reminders", $insertVals);
		array_push($newIDs, array("id" => mysql_insert_id()));
	}

	return $newIDs;
}


function handleComments($bibID, $removals, $modifications, $additions) {
	// removals are encoded as just the comments ID# ... easy.
	if ($removals) {
		$removals = array_map("intval", $removals);
		mysql_query("update comments set cmt_deleted=1
		where cmt_usr_id=".get_user_id()." and cmt_rec_id=$bibID and cmt_id in (".join(",",$removals).")");
	}

	// modifications have the values
	// .id, .parentComment, .text
	foreach ($modifications as $modification) {
		// note that parentComment (of course) cannot be modified
		mysql__update("comments", "cmt_id=".intval($modification["id"])." and cmt_usr_id=".get_user_id(),
		array("cmt_text" => $modification["text"], "cmt_modified" => date('Y-m-d H:i:s')));
	}

	// additions are the same as modifications, except that the COMMENT-ID is blank (of course!)
	$newIDs = array();
	foreach ($additions as $addition) {
		$parentID = intval($addition["parentComment"]);

		// do a sanity check first: does this reply make sense?
		$parentTest = $parentID? "cmt_id=$parentID" : "cmt_id is null";
		if (! mysql__select_array("records left join comments on rec_id=cmt_rec_id and $parentTest", "rec_id", "rec_id=$bibID and $parentTest")) {
			array_push($newIDs, array("error" => "invalid parent comments"));
			continue;
		}

		if (! $parentID) { $parentId = NULL; }

		mysql__insert("comments", array("cmt_text" => $addition["text"], "cmt_date" => date('Y-m-d H:i:s'), "cmt_usr_id" => get_user_id(),
		"cmt_parent_cmt_id" => $parentID, "cmt_rec_id" => $bibID));
		array_push($newIDs, array("id" => mysql_insert_id()));
	}

	return $newIDs;
}

?>
