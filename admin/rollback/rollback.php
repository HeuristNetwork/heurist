<?php

require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../records/TitleMask.php');

if (! is_admin()) return;

mysql_connection_overwrite(DATABASE);


function getTargetVersions ($rec_ids=null, $before_date=null) {
	// find the last non-false-delta version before a given date,
	// OR if no date is specifed, find the previous version of the given records
	// returns an assoc array of (rec_id => arec_ver)

	if (! $rec_ids  &&  ! $before_date) {
		return null;
	}

	$versions = array();

	$where_clause = $rec_ids ? "arec_id in (" . join(",", $rec_ids) . ")" : "";
	if ($before_date) {
		$where_clause .= $rec_ids ? " and " : "";
		$where_clause .= "arec_date > '$before_date'";
	}

	$arec_ids = mysql__select_array("archive_records", "distinct arec_id", $where_clause);

	$arec_ids_str = join(",", $arec_ids);

	if ($before_date) {
		$res = mysql_query("
			select arec_id, max(arec_date)
			from archive_records
			where arec_id in ($arec_ids_str) and arec_date <= '$before_date'
			group by arec_id
			order by max(arec_date) desc
		");
		while ($row = mysql_fetch_row($res)) {
			// join to archive_rec_details to find the latest non-false-delta version
			$ver_res = mysql_query("
				select arec_ver
				from archive_records, archive_rec_details
				where arec_id = " . $row[0] . "
				and arec_date = '" . $row[1] . "'
				and ard_rec_id = arec_id
				and ard_ver = arec_ver
				order by arec_ver desc
				limit 1
			");
			$arec_ver = mysql_fetch_row($ver_res);
			$versions[$row[0]] = $arec_ver[0];
		}

	} else {
		// join to archive_rec_details to find the latest non-false-delta version
		$res = mysql_query("
			select arec_id, max(arec_ver)
			from archive_records, archive_rec_details
			where arec_id in ($arec_ids_str)
			and ard_rec_id = arec_id
			and ard_ver = arec_ver
			group by arec_id
		");
		while ($row = mysql_fetch_row($res)) {
			// join to archive_rec_details to find the latest non-false-delta version
			$ver_res = mysql_query("
				select arec_ver
				from archive_records, archive_rec_details
				where arec_id = " . $row[0] . "
				and arec_ver < " . $row[1] . "
				and ard_rec_id = arec_id
				and ard_ver = arec_ver
				order by arec_ver desc
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
		"archive_rec_details",
		"distinct ard_id",
		"ard_rec_id = $rec_id and ard_ver > $since_version"
	);
}


function getDetailHistory ($ard_id, $up_to_version) {
	// return deltas from archive_rec_details, latest first
	$deltas = array();
	$res = mysql_query("
		select ard_id, ard_ver, ard_type, ard_val, ard_file_id, astext(ard_geo) as ard_geo
		from archive_rec_details
		where ard_id = $ard_id
		and ard_ver <= $up_to_version
		order by ard_ver desc
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
			if ($latest["ard_val"]  ||  $latest["ard_file_id"]  ||  $latest["ard_geo"]) {
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

	$current_details = mysql__select_array("rec_details", "rd_id", "rd_rec_id = $rec_id");

	foreach ($potential_deletes as $potential_delete) {
		if (in_array($potential_delete, $current_details)) {
			array_push($deletes, $potential_delete);
		}
	}

	foreach ($potential_updates as $potential_update) {
		if (in_array($potential_update["ard_id"], $current_details)) {
			// check if the current value is actually the same
			// (this would happen if the detail has been changed, and changed back)
			$ard_id = $potential_update["ard_id"];
			$ard_val = $potential_update["ard_val"];
			$ard_file_id = $potential_update["ard_file_id"];
			$ard_geo = $potential_update["ard_geo"];
			$res = mysql_query("
				select rd_id
				from rec_details
				where rd_id = $ard_id
				and rd_val " . ($ard_val ? "= '" . addslashes($ard_val) . "'" : "is null") . "
				and rd_file_id " . ($ard_file_id ? "= $ard_file_id" : "is null") . "
				and astext(rd_geo) " . ($ard_geo ? "= '$ard_geo'" : "is null")
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

	mysql_query("update records set rec_modified = now() where rec_id = $rec_id");
	if (mysql_error()) {
		error_log(mysql_error());
		mysql_query("rollback");
		return false;
	}

	foreach ($changes["updates"] as $update) {
		$rd_id       = $update["ard_id"];
		$rd_val      = $update["ard_val"] ? "'" . addslashes($update["ard_val"]) . "'" : "null";
		$rd_file_id  = $update["ard_file_id"] ? $update["ard_file_id"] : "null";
		$rd_geo      = $update["ard_geo"] ? "geomfromtext('" . $update["ard_geo"] . "')" : "null";
		mysql_query("
			update rec_details
			set rd_val = $rd_val, rd_file_id = $rd_file_id, rd_geo = $rd_geo
			where rd_rec_id = $rec_id
			and rd_id = $rd_id
		");
		if (mysql_error()) {
			error_log(mysql_error());
			mysql_query("rollback");
			return false;
		}
	}

	foreach ($changes["inserts"] as $insert) {
		$rd_id       = $insert["ard_id"];
		$rd_type     = $insert["ard_type"];
		$rd_val      = $insert["ard_val"] ? "'" . addslashes($insert["ard_val"]) . "'" : "null";
		$rd_file_id  = $insert["ard_file_id"] ? $insert["ard_file_id"] : "null";
		$rd_geo      = $insert["ard_geo"] ? "geomfromtext('" . $insert["ard_geo"] . "')" : "null";
		mysql_query("
			insert into rec_details (rd_rec_id, rd_type, rd_val, rd_file_id, rd_geo)
			values ($rec_id, $rd_type, $rd_val, $rd_file_id, $rd_geo)
		");
		if (mysql_error()) {
			error_log(mysql_error());
			mysql_query("rollback");
			return false;
		}
	}

	foreach ($changes["deletes"] as $ard_id) {
		mysql_query("delete from rec_details where rd_id = $ard_id");
		if (mysql_error()) {
			error_log(mysql_error());
			mysql_query("rollback");
			return false;
		}
	}

	// update record title if necessary
	$res = mysql_query("
		select rec_type, rty_TitleMask
		from records, defRecTypes
		where rec_id = $rec_id
		and rty_ID = rec_type
	");
	if ($res) {
		$row = mysql_fetch_row($res);
		if ($row) {
			$title = fill_title_mask($row[1], $rec_id, $row[0]);
			if ($title) {
				mysql_query("set @suppress_update_trigger := 1");
				mysql_query("update records set rec_title = '" . addslashes($title) . "' where rec_id = $rec_id");
				if (mysql_error()) {
					error_log(mysql_error());
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
