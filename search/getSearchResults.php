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

require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/parseQueryToSQL.php');
require_once(dirname(__FILE__)."../../records/files/uploadFile.php");

$memcache = null;

mysql_connection_overwrite(DATABASE);

function loadSearch($args, $bare = false, $onlyIDs = false, $publicOnly = false)  {
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

	$query = REQUEST_to_query("select SQL_CALC_FOUND_ROWS rec_ID ", $searchType, $args, null, $publicOnly);

	$res = mysql_query($query);
//error_log($query);

	if (mysql_error()) {
		error_log("queryError in getSearchResults -".mysql_error());
	}
	$fres = mysql_query('select found_rows()');
	$resultCount = mysql_fetch_row($fres); $resultCount = $resultCount[0];

	if ($onlyIDs) {
		$row = mysql_fetch_assoc($res);
		$ids = "" . ($row["rec_ID"] ? $row["rec_ID"]:"");
		while ($row = mysql_fetch_assoc($res)) {
			$ids .= ($row["rec_ID"] ? ",".$row["rec_ID"]:"");
		}
		return array("resultCount" => $resultCount, "recordCount" => (strlen($ids)?count(explode(",",$ids)):0), "recIDs" => $ids);
	}else{
		$recs = array();
		while ($row = mysql_fetch_assoc($res)) {

			if (mysql_error()) {
				error_log("2.queryError in getSearchResults -".mysql_error());
			}

			$record = loadRecord($row["rec_ID"], $fresh, $bare);
			if (array_key_exists("error", $record)) {
				return array("error" => $record["error"]);
			}
			array_push($recs, $record);
		}

		return array("resultCount" => $resultCount, "recordCount" => count($recs), "records" => $recs);
	}
}

/*
 *	This function takes a comma separated list of recIDS and for each clection record
 *	finds the recIDs that statisfy the query string, adding any direct recIDs from the
 *	repeatable pointer field and appends any non collection records in the list.
 *
 *
 *
 */
function expandCollections($recIDs, $publicOnly = false){
	$colRT = (defined('RT_COLLECTION')?RT_COLLECTION:0);
	$qStrDT = (defined('DT_QUERY_STRING')?DT_QUERY_STRING:0);
	$resrcDT = (defined('DT_RESOURCE')?DT_RESOURCE:0);
	$expRecIDs = array();
	foreach ( $recIDs as $recID ){
//error_log("recID ".print_r($recID,true));
		$rectype = mysql__select_array("Records","rec_RecTypeID","rec_ID = $recID");
//error_log("rectype ($colRT) ".print_r($rectype,true));
		$rectype = intval($rectype[0]);
		if ($rectype == $colRT) { // collection rec so get query string and expand it and list all ptr recIDs
			$qryStr = mysql__select_array("recDetails","dtl_Value","dtl_DetailTypeID = $qStrDT and dtl_RecID = $recID");
//error_log("query String ".print_r($qryStr,true));
			if (count($qryStr) > 0) {
				// get recIDs only for query. and add them to expanded recs
				$loadResult = loadSearch(array("q"=>$qryStr[0]),true,true,$publicOnly);
//error_log("loadResult ".print_r($loadResult,true));
				if (array_key_exists("recordCount",$loadResult) && $loadResult["recordCount"] > 0){
					foreach (explode(",",$loadResult["recIDs"]) as $resRecID) {
						if (!in_array($resRecID,$expRecIDs)){
							array_push($expRecIDs,$resRecID);
						}
					}
				}
			}
			//add any colected record pointers
			$collRecIDs = mysql__select_array("recDetails","dtl_Value","dtl_DetailTypeID = $resrcDT and dtl_RecID = $recID");
//error_log("recID list ".print_r($collRecIDs,true));
			foreach ($collRecIDs as $collRecID) {
				if (!in_array($collRecID,$expRecIDs)){
					array_push($expRecIDs,$collRecID);
				}
			}
		}else if (!in_array($recID,$expRecIDs)){
			array_push($expRecIDs,$recID);
		}
	}
	return $expRecIDs;
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
	$key = DATABASE . ":record:" . $id;
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
	$key = DATABASE . ":record:" . $id;
	$record = $memcache->get($key);
	if ($record) {	// will only update if previously cached
		$record = loadBareRecordFromDB($id);
		$memcache->set($key, $record);
	}
}

function loadRecordStub($id) {
	$res = mysql_query(
	    "select rec_ID as id,
	            rec_RecTypeID as type,
	            rec_Title as title,
	            rec_URL as url
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
	            rec_NonOwnerVisibility,
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
	$squery =
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
	      where dtl_RecID = $recID";

	$res = mysql_query($squery);

	$details = array();
	while ($rd = mysql_fetch_assoc($res)) {
		if (! @$details[$rd["dtl_DetailTypeID"]]) $details[$rd["dtl_DetailTypeID"]] = array();

		if ( !$rd["dtl_DetailTypeID"] === "file" && $rd["dtl_Value"] === null ) continue;//TODO: check this for URLinclude, etc.

		$detailValue = null;

		switch ($rd["dty_Type"]) {
			case "freetext": case "blocktext":
            case "float":
			case "date":
			case "enum":
			case "relationtype":
            case "integer": case "boolean": case "year": case "urlinclude": // these shoudl no logner exist, retained for backward compatibility
			$detailValue = $rd["dtl_Value"];
			break;

			case "file":

			$detailValue = get_uploaded_file_info($rd["dtl_UploadedFileID"], false);

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
	            bkm_Rating
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
