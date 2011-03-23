<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


	/*<!-- saveRelations.php

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


define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
if (! is_logged_in()) return;

require_once(dirname(__FILE__)."/../../common/php/getRecordInfoLibrary.php");

mysql_connection_overwrite(DATABASE);


header("Content-type: text/javascript");

if (strpos(@$_REQUEST["recID"], ",") !== false) {
	$recIDs = array_map("intval", explode(",", $_REQUEST["recID"]));
	if (count($recIDs) < 1) return;
	$recID = null;
} else {
	$recID = intval(@$_REQUEST["recID"]);
	if (! $recID) return;
}

if (@$_REQUEST["delete"]  && $recID) {
	$deletions = array_map("intval", $_REQUEST["delete"]);
}

if (count(@$deletions) > 0) {
	/* check the deletion recIDs to make sure they actually involve the given rec_ID */
	$res = mysql_query("select rec_ID from Records, recDetails
		where dtl_RecID=rec_ID and dtl_DetailTypeID in (202, 199) and dtl_Value=$recID and rec_ID in (" . join(",", $deletions) . ")");

	$deletions = array();
	while ($row = mysql_fetch_row($res)) array_push($deletions, $row[0]);
	if ($deletions) {
		foreach ($deletions as $del_recID) {
			/* one delete query per rec_ID, this way the archive_bib* versioning stuff works */
			mysql_query("update Records set rec_Modified=now() where rec_ID = $del_recID");
//error_log("in delete code $del_recID ");
			mysql_query("delete from recDetails where dtl_RecID = $del_recID");
//error_log("in deleted details for record $del_recID ".mysql_error());
			mysql_query("delete from Records where rec_ID = $del_recID");
//error_log("in deleted delete record $del_recID ".mysql_error());
		}

		$relatedRecords = getAllRelatedRecords($recID);

		print "(" . json_format($relatedRecords) . ")";
	}
}
else if (@$_REQUEST["save-mode"] == "new") {
	if ($recID) {
		print "(" . json_format(saveRelationship(
			$recID,
			intval(@$_REQUEST["RelTermID"]),
			intval($_REQUEST["RelatedRecID"]),
			intval($_REQUEST["InterpRecID"]),
			@$_REQUEST["Title"],
			@$_REQUEST["Notes"],
			@$_REQUEST["StartDate"],
			@$_REQUEST["EndDate"]
		)) . ")";
	} else {
		$rv = array();
		foreach ($recIDs as $recID) {
			array_push($rv, saveRelationship(
				$recID,
				intval(@$_REQUEST["RelTermID"]),
				intval($_REQUEST["RelatedRecID"]),
				intval($_REQUEST["InterpRecID"]),
				@$_REQUEST["Title"],
				@$_REQUEST["Notes"],
				@$_REQUEST["StartDate"],
				@$_REQUEST["EndDate"]
			));
		}
		print "(" . json_format($rv) . ")";
	}
}

function saveRelationship($recID, $relTermID, $trgRecID, $interpRecID, $title, $notes, $start_date, $end_date) {
	$relval = mysql_fetch_assoc(mysql_query("select trm_Label from defTerms where trm_ID = $relTermID"));
	$relval = $relval['trm_Label'];
	$srcTitle = mysql_fetch_assoc(mysql_query("select rec_Title from Records where rec_ID = $recID"));
	$srcTitle = $srcTitle['rec_Title'];
	$trgTitle = mysql_fetch_assoc(mysql_query("select rec_Title from Records where rec_ID = $trgRecID"));
	$trgTitle = $trgTitle['rec_Title'];
	mysql__insert("Records", array(
		"rec_Title" => "$title ($srcTitle $relval $trgTitle)",
                "rec_Added"     => date('Y-m-d H:i:s'),
                "rec_Modified"  => date('Y-m-d H:i:s'),
                "rec_RecTypeID"   => 52,
									"rec_AddedByUGrpID" => get_user_id()));

	if (mysql_error()) {
		return array("error" => slash(mysql_error()));
	}

	$relnRecID = mysql_insert_id();

	if ($relnRecID > 0) {
		$query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ";
		$query .=   "($relnRecID, 160, '" . addslashes($title) . "')";
		$query .= ", ($relnRecID, 202, $recID)";
		$query .= ", ($relnRecID, 199, $trgRecID)";
		$query .= ", ($relnRecID, 200, $relTermID)";
		if ($interpRecID) $query .= ", ($relnRecID, 638, $interpRecID)";
		if ($notes) $query .= ", ($relnRecID, 201, '" . addslashes($notes) . "')";
		if ($start_date) $query .= ", ($relnRecID, 177, '" . addslashes($start_date) . "')";
		if ($end_date) $query .= ", ($relnRecID, 178, '" . addslashes($end_date) . "')";

		mysql_query($query);
	}

	if (mysql_error()) {
		return array("error" => slash(mysql_error()));
	} else {
//		$related = getAllRelatedRecords($recID, $relnRecID);
		$related = getAllRelatedRecords($recID);
		return array("relationship" => $related,"relnRecID" => $relnRecID);
	}
}

?>
