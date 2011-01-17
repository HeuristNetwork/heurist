<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

function deleteRecord($id) {
	$id = intval($id);
	$res = mysql_query("SELECT rec_AddedByUGrpID FROM Records WHERE rec_ID = " . $id);
	$row = mysql_fetch_assoc($res);
	$owner = $row["rec_AddedByUGrpID"];


	// find any references to the record
	$res = mysql_query("SELECT DISTINCT dtl_RecID
	                      FROM defDetailTypes
	                 LEFT JOIN recDetails ON dtl_DetailTypeID = dty_ID
	                     WHERE dty_Type = 'resource'
	                       AND dtl_Value = " . $id);
	$reference_count = mysql_num_rows($res);
	$reference_ids = array();
	while ($row = mysql_fetch_assoc($res)) array_push($reference_ids, $row["dtl_RecID"]);

	// find any bookmarks of the record
	$res = mysql_query("select bkm_ID from Records left join usrBookmarks on bkm_recID=rec_ID where rec_ID = " . $id . " and bkm_ID is not null");
	$bkmk_count = mysql_num_rows($res);
	$bkmk_ids = array();
	while ($row = mysql_fetch_assoc($res)) array_push($bkmk_ids, $row["bkm_ID"]);


	if (is_admin()  ||  $owner === get_user_id()) {
		if ($reference_count === 0  &&  $bkmk_count === 0) {

			mysql_query('delete from Records where rec_ID = ' . $id);
			if (mysql_error()) jsonError("database error - " . mysql_error());
			$deleted = mysql_affected_rows();

			mysql_query('delete from recDetails where dtl_RecID = ' . $id);
			if (mysql_error()) jsonError("database error - " . mysql_error());

			mysql_query('delete from usrReminders where rem_RecID = ' . $id);
			if (mysql_error()) jsonError("database error - " . mysql_error());

			mysql_query('delete from usrRecTagLinks where rtl_RecID = ' . $id);
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
