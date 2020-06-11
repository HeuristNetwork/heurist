<?php
/* 
THIS IS Heurist v.3. 
It is not used anywhere. This code either should be removed or re-implemented wiht new libraries
*/

/*
* Copyright (C) 2005-2020 University of Sydney
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
* @copyright   (C) 2005-2020 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');

if (! is_admin()) return;

mysql_connection_overwrite(DATABASE);


function getTargetVersions ($rec_ids=null, $before_date=null) {
	// find the last non-false-delta version before a given date,
	// OR if no date is specifed, find the previous version of the given records
	// returns an assoc array of (rec_ID => arec_Ver)

	if (! $rec_ids  &&  ! $before_date) {
		return null;
	}

	$versions = array();

	$where_clause = $rec_ids ? "arec_ID in (" . join(",", $rec_ids) . ")" : "";
	if ($before_date) {
		$where_clause .= $rec_ids ? " and " : "";
		$where_clause .= "arec_Date > '$before_date'";
	}

	$arec_ids = mysql__select_array("archiveRecords", "distinct arec_ID", $where_clause);

	$arec_ids_str = join(",", $arec_ids);

	if ($before_date) {
		$res = mysql_query("
			select arec_ID, max(arec_Date)
			from archiveRecords
			where arec_ID in ($arec_ids_str) and arec_Date <= '$before_date'
			group by arec_ID
			order by max(arec_Date) desc
		");
		while ($row = mysql_fetch_row($res)) {
			// join to archiveDetails to find the latest non-false-delta version
			$ver_res = mysql_query("
				select arec_Ver
				from archiveRecords, archiveDetails
				where arec_ID = " . $row[0] . "
				and arec_Date = '" . $row[1] . "'
				and ard_RecID = arec_ID
				and ard_Ver = arec_Ver
				order by arec_Ver desc
				limit 1
			");
			$arec_ver = mysql_fetch_row($ver_res);
			$versions[$row[0]] = $arec_ver[0];
		}

	} else {
		// join to archiveDetails to find the latest non-false-delta version
		$res = mysql_query("
			select arec_ID, max(arec_Ver)
			from archiveRecords, archiveDetails
			where arec_ID in ($arec_ids_str)
			and ard_RecID = arec_ID
			and ard_Ver = arec_Ver
			group by arec_ID
		");
		while ($row = mysql_fetch_row($res)) {
			// join to archiveDetails to find the latest non-false-delta version
			$ver_res = mysql_query("
				select arec_Ver
				from archiveRecords, archiveDetails
				where arec_ID = " . $row[0] . "
				and arec_Ver < " . $row[1] . "
				and ard_RecID = arec_ID
				and ard_Ver = arec_Ver
				order by arec_Ver desc
				limit 1
			");
			$arec_ver = mysql_fetch_row($ver_res);
			$versions[$row[0]] = $arec_ver[0];
		}
	}
	return $versions;
}


function getAffectedDetails ($rec_id, $since_version) {
	return mysql__select_array(
		"archiveDetails",
		"distinct ard_ID",
		"ard_RecID = $rec_id and ard_Ver > $since_version"
	);
}


function getDetailHistory ($ard_id, $up_to_version) {
	// return deltas from archiveDetails, latest first
	$deltas = array();
	$res = mysql_query("
		select ard_ID, ard_Ver, ard_DetailTypeID, ard_Value, ard_UploadedFileID, ST_asWKT(ard_Geo) as ard_Geo
		from archiveDetails
		where ard_ID = $ard_id
		and ard_Ver <= $up_to_version
		order by ard_Ver desc
	");
	while ($row = mysql_fetch_assoc($res)) {
		array_push($deltas, $row);
	}
	return $deltas;
}


function getDetailRollbacks ($rec_id, $version) {

	$potential_updates = array();
	$potential_deletes = array();
	$updates = array();
	$inserts = array();
	$deletes = array();

	$ard_ids = getAffectedDetails($rec_id, $version);

	foreach ($ard_ids as $ard_id) {
		$deltas = getDetailHistory($ard_id, $version);
		if (count($deltas) === 0) {
			// this detail didn't exist before or at the target version
			// delete it if it exists
			array_push($potential_deletes, $ard_id);
		} else {
			$latest = $deltas[0];
			if ($latest["ard_Value"]  ||  $latest["ard_UploadedFileID"]  ||  $latest["ard_Geo"]) {
				// an insert or update
				array_push($potential_updates, $latest);
			} else {
				// a delete
				// this shouldn't be possible - if a detail was deleted before the target version,
				// it would not be in the list returned by getAffectedDetails()
				array_push($potential_deletes, $ard_id);
			}
		}
	}

	$current_details = mysql__select_array("recDetails", "dtl_ID", "dtl_RecID = $rec_id");

	foreach ($potential_deletes as $potential_delete) {
		if (in_array($potential_delete, $current_details)) {
			array_push($deletes, $potential_delete);
		}
	}

	foreach ($potential_updates as $potential_update) {
		if (in_array($potential_update["ard_ID"], $current_details)) {
			// check if the current value is actually the same
			// (this would happen if the detail has been changed, and changed back)
			$ard_id = $potential_update["ard_ID"];
			$ard_val = $potential_update["ard_Value"];
			$ard_file_id = $potential_update["ard_UploadedFileID"];
			$ard_geo = $potential_update["ard_Geo"];
			$res = mysql_query("
				select dtl_ID
				from recDetails
				where dtl_ID = $ard_id
				and dtl_Value " . ($ard_val ? "= '" . mysql_real_escape_string($ard_val) . "'" : "is null") . "
				and dtl_UploadedFileID " . ($ard_file_id ? "= $ard_file_id" : "is null") . "
				and ST_asWKT(dtl_Geo) " . ($ard_geo ? "= '$ard_geo'" : "is null")
			);
			if (mysql_num_rows($res) == 0) {
				array_push($updates, $potential_update);
			}
		} else {
			array_push($inserts, $potential_update);
		}
	}

	return array(
		"updates" => $updates,
		"inserts" => $inserts,
		"deletes" => $deletes
	);
}


function getRecordRollbacks ($rec_ids, $before_date=null) {
	$rollbacks = array();
	$versions = getTargetVersions($rec_ids, $before_date);
	foreach ($versions as $rec_id => $version) {
		$rollbacks[$rec_id] = getDetailRollbacks($rec_id, $version);
	}
	return $rollbacks;
}


function rollRecordBack ($rec_id, $changes) {
	if (count($changes["updates"]) == 0  &&
		count($changes["inserts"]) == 0  &&
		count($changes["deletes"]) == 0) {
		return true;
	}

	mysql_query("start transaction");

	mysql_query("update Records set rec_Modified = now() where rec_ID = $rec_id");
	if (mysql_error()) {
		mysql_query("rollback");
		return false;
	}

	foreach ($changes["updates"] as $update) {
		$rd_id       = $update["ard_ID"];
		$rd_val      = $update["ard_Value"] ? "'" . mysql_real_escape_string($update["ard_Value"]) . "'" : "null";
		$rd_file_id  = $update["ard_UploadedFileID"] ? $update["ard_UploadedFileID"] : "null";
		$rd_geo      = $update["ard_Geo"] ? "ST_GeomFromText('" . $update["ard_Geo"] . "')" : "null";
		mysql_query("
			update recDetails
			set dtl_Value = $rd_val, dtl_UploadedFileID = $rd_file_id, dtl_Geo = $rd_geo
			where dtl_RecID = $rec_id
			and dtl_ID = $rd_id
		");
		if (mysql_error()) {
			mysql_query("rollback");
			return false;
		}
	}

	foreach ($changes["inserts"] as $insert) {
		$rd_id       = $insert["ard_ID"];
		$rd_type     = $insert["ard_DetailTypeID"];
		$rd_val      = $insert["ard_Value"] ? "'" . mysql_real_escape_string($insert["ard_Value"]) . "'" : "null";
		$rd_file_id  = $insert["ard_UploadedFileID"] ? $insert["ard_UploadedFileID"] : "null";
		$rd_geo      = $insert["ard_Geo"] ? "ST_GeomFromText('" . $insert["ard_Geo"] . "')" : "null";
		mysql_query("
			insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value, dtl_UploadedFileID, dtl_Geo)
			values ($rec_id, $rd_type, $rd_val, $rd_file_id, $rd_geo)
		");
		if (mysql_error()) {
			mysql_query("rollback");
			return false;
		}
	}

	foreach ($changes["deletes"] as $ard_id) {
		mysql_query("delete from recDetails where dtl_ID = $ard_id");
		if (mysql_error()) {
			mysql_query("rollback");
			return false;
		}
	}

	// update record title if necessary
	$res = mysql_query("
		select rec_RecTypeID, rty_TitleMask
		from Records, defRecTypes
		where rec_ID = $rec_id
		and rty_ID = rec_RecTypeID
	");
	if ($res) {
		$row = mysql_fetch_row($res);
		if ($row) {
			$title = fill_title_mask($row[1], $rec_id, $row[0]);
			if ($title) {
				mysql_query("set @suppress_update_trigger := 1");
				mysql_query("update Records set rec_Title = '" . mysql_real_escape_string($title) . "' where rec_ID = $rec_id");
				if (mysql_error()) {
					mysql_query("rollback");
					mysql_query("set @suppress_update_trigger := NULL");
					return false;
				}
				mysql_query("set @suppress_update_trigger := NULL");
			}
		}
	}

	mysql_query("commit");
	updateCachedRecord($rec_id);
	return true;
}


function rollRecordsBack ($rec_ids, $before_date=null) {
	$n = 0;
	$rollbacks = getRecordRollbacks($rec_ids, $before_date);
	foreach ($rollbacks as $rec_id => $changes) {
		if (rollRecordBack($rec_id, $changes)) {
			$n++;
		}
	}
	return $n;
}

?>
