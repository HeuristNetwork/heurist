<?php
	/*<!-- loading.php

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
define('MEMCACHED_PORT', 11211);

require_once(dirname(__FILE__).'/../advanced/adv-search.php');

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

	$query = REQUEST_to_query("select SQL_CALC_FOUND_ROWS rec_id ", $searchType, $args)
								. (@$limit? " limit $limit" : "") . (@$offset? " offset $offset " : "");
	$res = mysql_query($query);

	$fres = mysql_query('select found_rows()');
	$resultCount = mysql_fetch_row($fres); $resultCount = $resultCount[0];

	if ($onlyIDs) {
		$row = mysql_fetch_assoc($res);
		$ids = "" . ($row["rec_id"] ? $row["rec_id"]:"");
		while ($row = mysql_fetch_assoc($res)) {
			$ids .= ($row["rec_id"] ? ",".$row["rec_id"]:"");
		}
		return array("resultCount" => $resultCount, "recordCount" => count(explode(",",$ids)), "recIDs" => $ids);
	}else{
		$recs = array();
		while ($row = mysql_fetch_assoc($res)) {
			$record = loadRecord($row["rec_id"], $fresh, $bare);
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
		if (! $memcache->connect('localhost', MEMCACHED_PORT)) {
			return array("error" => "couldn't connect to memcached");
		}
	}
	$key = HEURIST_INSTANCE . ":record:" . $id;
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
	$key = HEURIST_INSTANCE . ":record:" . $id;
	$record = $memcache->get($key);
	if ($record) {
		$record = loadBareRecordFromDB($id);
		$memcache->set($key, $record);
	}
}

function loadRecordStub($id) {
	$res = mysql_query(
	    "select rec_id,
	            rec_type,
	            rec_title,
	            rec_url,
	            rec_scratchpad,
	            rec_wg_id,
	            if (rec_visibility = 'Hidden', 0, 1) as rec_visibility,
	            rec_url_last_verified,
	            rec_url_error,
	            rec_added,
	            rec_modified,
	            rec_added_by_usr_id,
	            rec_hhash
	       from records
	      where rec_id = $id");
	$record = mysql_fetch_assoc($res);
	return $record;
}

function loadBareRecordFromDB($id) {
	$res = mysql_query(
	    "select rec_id,
	            rec_type,
	            rec_title,
	            rec_url,
	            rec_scratchpad,
	            rec_wg_id,
	            if (rec_visibility = 'Hidden', 0, 1) as rec_visibility,
	            rec_url_last_verified,
	            rec_url_error,
	            rec_added,
	            rec_modified,
	            rec_added_by_usr_id,
	            rec_hhash
	       from records
	      where rec_id = $id");
	$record = mysql_fetch_assoc($res);
	if ($record) {
		loadRecordDetails($record);
		// saw todo might need to load record relmarker info here which gets the constrained set or just constraints
	}
	return $record;
}

function loadRecordDetails(&$record) {
	$recID = $record["rec_id"];
	$res = mysql_query(
	    "select rd_id,
	            rd_type,
	            rd_val,
	            astext(rd_geo) as rd_geo,
	            rd_file_id,
	            rdt_type,
	            rec_id,
	            rec_title,
	            rec_type,
	            rec_hhash
	       from rec_details
	  left join rec_detail_types on rdt_id = rd_type
	  left join records on rec_id = rd_val and rdt_type = 'resource'
	      where rd_rec_id = $recID");

	$details = array();
	while ($rd = mysql_fetch_assoc($res)) {
		if (! @$details[$rd["rd_type"]]) $details[$rd["rd_type"]] = array();

		if ( !$rd["rd_type"] === "file" && $rd["rd_val"] === null ) continue;

		$detailValue = null;

		switch ($rd["rdt_type"]) {
			case "freetext": case "blocktext":
			case "integer": case "float": case "boolean":
			case "date": case "year":
			case "enum":
			$detailValue = $rd["rd_val"];
			break;

			case "file":
			$fres = mysql_query(
			    "select file_id as id,
			            file_nonce as nonce,
			            file_orig_name as origName,
			            file_size as size,
			            file_mimetype as type,
			            file_date as date,
			            file_description as description
			       from files
			      where file_id = " . intval($rd["rd_file_id"]));
			$detailValue = array("file" => mysql_fetch_assoc($fres));
			$origName = urlencode($detailValue["file"]["origName"]);
			$detailValue["file"]["URL"] =
				HEURIST_URL_BASE."records/files/fetch_file.php?". (defined('HEURIST_INSTANCE') ? "instance=".HEURIST_INSTANCE."&" : "" )
				."file_id=".$detailValue["file"]["nonce"];
			$detailValue["file"]["thumbURL"] =
				HEURIST_URL_BASE."common/php/resize_image.php?" . (defined('HEURIST_INSTANCE') ? "instance=".HEURIST_INSTANCE."&" : "" )
				."file_id=".$detailValue["file"]["nonce"];
			break;

			case "resource":
			$detailValue = array(
				"id" => $rd["rec_id"],
				"type"=>$rd["rec_type"],
				"title" => $rd["rec_title"],
				"hhash" => $rd["rec_hhash"]
			);
			break;

			case "geo":
			if ($rd["rd_val"]  &&  $rd["rd_geo"]) {
				$detailValue = array(
					"geo" => array(
						"type" => $rd["rd_val"],
						"wkt" => $rd["rd_geo"]
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
			$details[$rd["rd_type"]][$rd["rd_id"]] = $detailValue;
		}
	}

	$record["details"] = $details;
}


function loadUserDependentData(&$record) {
	$recID = $record["rec_id"];
	$res = mysql_query(
	    "select bkm_ID,
	            pers_notes,
	            pers_content_rating,
	            pers_interest_rating,
	            pers_quality_rating
	       from usrBookmarks
	      where bkm_recID = $recID
	        and bkm_UGrpID = ".get_user_id());
	if (mysql_num_rows($res) > 0) {
		$row = mysql_fetch_assoc($res);
		$record = array_merge($record, $row);
	}

	$res = mysql_query(
	    "select rem_rec_id,
	            rem_id,
	            rem_wg_id,
	            rem_cgr_id,
	            rem_usr_id,
	            rem_email,
	            rem_message,
	            rem_startdate,
	            rem_freq
	       from reminders
	      where rem_rec_id = $recID
	        and rem_owner_id=".get_user_id());
	$reminders = array();
	while ($rem = mysql_fetch_row($res)) {
		$rec_id = array_shift($rem);
		array_push($reminders, $rem);
	}

	$res = mysql_query(
	    "select cmt_id,
	            cmt_parent_cmt_id,
	            cmt_date,
	            cmt_modified,
	            cmt_text,
	            cmt_usr_id,
	            cmt_deleted
	       from comments
	      where cmt_rec_id = $recID
	   order by cmt_id");
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
		"keyword_links, keywords",
		"kwd_name",
		"kwd_id = kwl_kwd_id and
		 kwd_usr_id = ".get_user_id()." and
		 kwl_rec_id = $recID
		 order by kwl_order");

	$record["keywords"] = mysql__select_array(
		"keyword_links, keywords, ".USERS_DATABASE.".UserGroups",
		"kwl_kwd_id",
		"kwd_id = kwl_kwd_id and
		 kwd_wg_id = ug_group_id and
		 ug_user_id = ".get_user_id()." and
		 kwl_rec_id = $recID
		 order by kwl_order");

	$record["notifies"] = $reminders;
	$record["comments"] = $comments;
}



?>
