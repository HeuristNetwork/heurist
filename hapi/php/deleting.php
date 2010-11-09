<?php

function deleteRecord($id) {
	$id = intval($id);
	$res = mysql_query("SELECT rec_added_by_usr_id FROM records WHERE rec_id = " . $id);
	$row = mysql_fetch_assoc($res);
	$owner = $row["rec_added_by_usr_id"];


	// find any references to the record
	$res = mysql_query("SELECT DISTINCT rd_rec_id
	                      FROM rec_detail_types
	                 LEFT JOIN rec_details ON rd_type = rdt_id
	                     WHERE rdt_type = 'resource'
	                       AND rd_val = " . $id);
	$reference_count = mysql_num_rows($res);
	$reference_ids = array();
	while ($row = mysql_fetch_assoc($res)) array_push($reference_ids, $row["rd_rec_id"]);

	// find any bookmarks of the record
	$res = mysql_query("select bkm_ID from records left join usrBookmarks on bkm_recID=rec_id where rec_id = " . $id . " and bkm_ID is not null");
	$bkmk_count = mysql_num_rows($res);
	$bkmk_ids = array();
	while ($row = mysql_fetch_assoc($res)) array_push($bkmk_ids, $row["bkm_ID"]);


	if (is_admin()  ||  $owner === get_user_id()) {
		if ($reference_count === 0  &&  $bkmk_count === 0) {

			mysql_query('delete from records where rec_id = ' . $id);
			if (mysql_error()) jsonError("database error - " . mysql_error());
			$deleted = mysql_affected_rows();

			mysql_query('delete from rec_details where rd_rec_id = ' . $id);
			if (mysql_error()) jsonError("database error - " . mysql_error());

			mysql_query('delete from reminders where rem_rec_id = ' . $id);
			if (mysql_error()) jsonError("database error - " . mysql_error());

			mysql_query('delete from usrRecTagLinks where kwl_rec_id = ' . $id);
			if (mysql_error()) jsonError("database error - " . mysql_error());

			return array("deleted" => $id);

		} else {
			jsonError("record cannot be deleted - there are existing references to it");
		}
	} else {
		jsonError("user not authorised to delete record");
	}
}



?>
