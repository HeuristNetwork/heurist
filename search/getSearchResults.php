<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

/*<!-- getSearchResults.php

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
if (! defined('SEARCH_VERSION')) {
	define('SEARCH_VERSION', 1);
}
if (!defined('MEMCACHED_PORT')) define('MEMCACHED_PORT', 11211);

require_once(dirname(__FILE__).'/parseQueryToSQL.php');

$memcache = null;

mysql_connection_select(DATABASE);

function loadSearch($args, $bare = false, $onlyIDs = false)  {
	/*
	 * Three basic steps are involved here:
	 *
	 * 1. Execute the query, which results in a list of record IDs
	 *    (this step includes authentication, i.e. the results will be only
	 *    those records visible to the user).
	 *
	 * 2. Load the core, public data for each record, whether it be from the
	 *    cache or from the database.
	 *
	 * 3. Load the user-dependent data for each record (bookmark, tags, comments etc.).
	 *    This step is optional - some applications may need only the core data.  In this
	 *    case they should specify $bare = true.
	 */

	if (! @$args["q"]) {
		return array("error" => "no query specified");
	}

	if (is_logged_in()  &&  @$args["w"] === "bookmark") {
		$searchType = BOOKMARK;
	} else {
		$searchType = BOTH;
	}

	$fresh = !! @$args["f"];

	if (array_key_exists("l", $args)) {
		$limit = intval(@$args["l"]);
		unset($args["l"]);
	}else if(array_key_exists("limit", $args)) {
		$limit = intval(@$args["limit"]);  // this is back in since hml.php passes through stuff from sitemap.xmap
	}else{
		$limit = 100;
	}
	if ($limit < 0 ) unset($limit);
	if (@$limit) $limit = min($limit, 1000);

	if (array_key_exists("o", $args)) {
		$offset = intval(@$args["o"]);
		unset($args["o"]);
	}else if(array_key_exists("offset", $args)) {
		$offset = intval(@$args["offset"]);  // this is back in since hml.php passes through stuff from sitemap.xmap
	}

	$query = REQUEST_to_query("select SQL_CALC_FOUND_ROWS rec_ID ", $searchType, $args)
								. (@$limit? " limit $limit" : "") . (@$offset? " offset $offset " : "");
	$res = mysql_query($query);

	$fres = mysql_query('select found_rows()');
	$resultCount = mysql_fetch_row($fres); $resultCount = $resultCount[0];

	if ($onlyIDs) {
		$row = mysql_fetch_assoc($res);
		$ids = "" . ($row["rec_ID"] ? $row["rec_ID"]:"");
		while ($row = mysql_fetch_assoc($res)) {
			$ids .= ($row["rec_ID"] ? ",".$row["rec_ID"]:"");
		}
		return array("resultCount" => $resultCount, "recordCount" => count(explode(",",$ids)), "recIDs" => $ids);
	}else{
		$recs = array();
		while ($row = mysql_fetch_assoc($res)) {
			$record = loadRecord($row["rec_ID"], $fresh, $bare);
			if (array_key_exists("error", $record)) {
				return array("error" => $record["error"]);
			}
			array_push($recs, $record);
		}

		return array("resultCount" => $resultCount, "recordCount" => count($recs), "records" => $recs);
	}
}


function loadRecord($id, $fresh = false, $bare = false) {
	global $memcache;
	if (! $id) {
		return array("error" => "must specify record id");
	}
	if (! $memcache) {
		$memcache = new Memcache;
		if (! $memcache->connect('localhost', MEMCACHED_PORT)) {	//saw Decision: error or just load raw???
			return array("error" => "couldn't connect to memcached");
		}
	}
	$key = HEURIST_DBNAME . ":record:" . $id;
	$record = null;
	if (! $fresh) {
		$record = $memcache->get($key);
	}
	if (! $record) {
		$record = loadBareRecordFromDB($id);
		if ($record) {
			$memcache->set($key, $record);
		}
	}
	if ($record && ! $bare) {
		loadUserDependentData($record);
	}
	return $record;
}


function updateCachedRecord($id) {
	global $memcache;
	if (! $memcache) {
		$memcache = new Memcache;
		if (! $memcache->connect('localhost', MEMCACHED_PORT)) {
			return array("error" => "couldn't connect to memcached");
		}
	}
	$key = HEURIST_DBNAME . ":record:" . $id;
	$record = $memcache->get($key);
	if ($record) {	// will only update if previously cached
		$record = loadBareRecordFromDB($id);
		$memcache->set($key, $record);
	}
}

function loadRecordStub($id) {
	$res = mysql_query(
	    "select rec_ID,
	            rec_RecTypeID,
	            rec_Title,
	            rec_URL,
	            rec_ScratchPad,
	            rec_OwnerUGrpID,
	            if (rec_NonOwnerVisibility = 'hidden', 0, 1) as rec_NonOwnerVisibility,
	            rec_URLLastVerified,
	            rec_URLErrorMessage,
	            rec_Added,
	            rec_Modified,
	            rec_AddedByUGrpID,
	            rec_Hash
	       from Records
	      where rec_ID = $id");
	$record = mysql_fetch_assoc($res);
	return $record;
}

function loadBareRecordFromDB($id) {
	$res = mysql_query(
	    "select rec_ID,
	            rec_RecTypeID,
	            rec_Title,
	            rec_URL,
	            rec_ScratchPad,
	            rec_OwnerUGrpID,
	            if (rec_NonOwnerVisibility = 'hidden', 0, 1) as rec_NonOwnerVisibility,
	            rec_URLLastVerified,
	            rec_URLErrorMessage,
	            rec_Added,
	            rec_Modified,
	            rec_AddedByUGrpID,
	            rec_Hash
	       from Records
	      where rec_ID = $id");
	$record = mysql_fetch_assoc($res);
	if ($record) {
		loadRecordDetails($record);
		// saw todo might need to load record relmarker info here which gets the constrained set or just constraints
	}
	return $record;
}

function loadRecordDetails(&$record) {
	$recID = $record["rec_ID"];
	$res = mysql_query(
	    "select dtl_ID,
	            dtl_DetailTypeID,
	            dtl_Value,
	            astext(dtl_Geo) as dtl_Geo,
	            dtl_UploadedFileID,
	            dty_Type,
	            rec_ID,
	            rec_Title,
	            rec_RecTypeID,
	            rec_Hash
	       from recDetails
	  left join defDetailTypes on dty_ID = dtl_DetailTypeID
	  left join Records on rec_ID = dtl_Value and dty_Type = 'resource'
	      where dtl_RecID = $recID");

	$details = array();
	while ($rd = mysql_fetch_assoc($res)) {
		if (! @$details[$rd["dtl_DetailTypeID"]]) $details[$rd["dtl_DetailTypeID"]] = array();

		if ( !$rd["dtl_DetailTypeID"] === "file" && $rd["dtl_Value"] === null ) continue;

		$detailValue = null;

		switch ($rd["dty_Type"]) {
			case "freetext": case "blocktext":
			case "integer": case "float": case "boolean":
			case "date": case "year":
			case "enum":
			case "relationtype":
			$detailValue = $rd["dtl_Value"];
			break;

			case "file":
			$fres = mysql_query(//saw NOTE! these field names match thoses used in HAPI to init an HFile object.
			    "select ulf_ID as id,
			            ulf_ObfuscatedFileID as nonce,
			            ulf_OrigFileName as origName,
			            ulf_FileSizeKB as size,
			            fxm_MimeType as type,
			            ulf_Added as date,
			            ulf_Description as description
			       from recUploadedFiles left join defFileExtToMimetype on ulf_MimeExt = fxm_Extension
			      where ulf_ID = " . intval($rd["dtl_UploadedFileID"]));

			$detailValue = array("file" => mysql_fetch_assoc($fres));
			$origName = urlencode($detailValue["file"]["origName"]);
			$detailValue["file"]["URL"] =
				HEURIST_URL_BASE."records/files/downloadFile.php?". (defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )
				."ulf_ID=".$detailValue["file"]["nonce"];
			$detailValue["file"]["thumbURL"] =
				HEURIST_URL_BASE."common/php/resizeImage.php?" . (defined('HEURIST_DBNAME') ? "db=".HEURIST_DBNAME."&" : "" )
				."ulf_ID=".$detailValue["file"]["nonce"];
			break;

			case "resource":
			$detailValue = array(
				"id" => $rd["rec_ID"],
				"type"=>$rd["rec_RecTypeID"],
				"title" => $rd["rec_Title"],
				"hhash" => $rd["rec_Hash"]
			);
			break;

			case "geo":
			if ($rd["dtl_Value"]  &&  $rd["dtl_Geo"]) {
				$detailValue = array(
					"geo" => array(
						"type" => $rd["dtl_Value"],
						"wkt" => $rd["dtl_Geo"]
					)
				);
			}
			break;

			case "separator":	// this should never happen since separators are not saved as details, skip if it does
			case "relmarker":	// relmarkers are places holders for display of relationships constrained in some way
			default:
			continue;
		}

		if ($detailValue) {
			$details[$rd["dtl_DetailTypeID"]][$rd["dtl_ID"]] = $detailValue;
		}
	}

	$record["details"] = $details;
}


function loadUserDependentData(&$record) {
	$recID = $record["rec_ID"];
	$res = mysql_query(
	    "select bkm_ID,
	            bkm_Rating,
	       from usrBookmarks
	      where bkm_recID = $recID
	        and bkm_UGrpID = ".get_user_id());
	if ($res && mysql_num_rows($res) > 0) {
		$row = mysql_fetch_assoc($res);
		$record = array_merge($record, $row);
	}

	$res = mysql_query(
	    "select rem_RecID,
	            rem_ID,
	            rem_ToWorkgroupID,
	            rem_ToUserID,
	            rem_Email,
	            rem_Message,
	            rem_StartDate,
	            rem_Freq
	       from usrReminders
	      where rem_RecID = $recID
	        and rem_OwnerUGrpID=".get_user_id());
	$reminders = array();
	while ($res && $rem = mysql_fetch_row($res)) {
		$rec_id = array_shift($rem);
		array_push($reminders, $rem);
	}

	$res = mysql_query(
	    "select cmt_ID,
	            cmt_ParentCmtID,
	            cmt_Added,
	            cmt_Modified,
	            cmt_Text,
	            cmt_OwnerUGrpID,
	            cmt_Deleted
	       from recThreadedComments
	      where cmt_RecID = $recID
	   order by cmt_ID");
	$comments = array();
	while ($cmt = mysql_fetch_row($res)) {
		$cmt[1] = intval($cmt[1]);
		$cmt[6] = intval($cmt[6]);

		if ($cmt[6]) {	// comment has been deleted, just leave a stub
			$cmt = array($cmt[0], $cmt[1], NULL, NULL, NULL, NULL, 1);
		}
		array_push($comments, $cmt);
	}

	$record["tags"] = mysql__select_array(
		"usrRecTagLinks, usrTags",
		"tag_Text",
		"tag_ID = rtl_TagID and
		 tag_UGrpID= ".get_user_id()." and
		 rtl_RecID = $recID
		 order by rtl_Order");

	$record["wgTags"] = mysql__select_array(
		"usrRecTagLinks, usrTags, ".USERS_DATABASE.".sysUsrGrpLinks",
		"rtl_TagID",
		"tag_ID = rtl_TagID and
		 tag_UGrpID = ugl_GroupID and
		 ugl_UserID = ".get_user_id()." and
		 rtl_RecID = $recID
		 order by rtl_Order");

	$record["notifies"] = $reminders;
	$record["comments"] = $comments;
}



?>
