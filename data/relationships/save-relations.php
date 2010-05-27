<?php
	/*<!-- save-relations.php

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

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");
if (! is_logged_in()) return;

require_once("relationships.php");

mysql_connection_overwrite(DATABASE);


header("Content-type: text/javascript");

if (strpos(@$_REQUEST["bib_id"], ",") !== false) {
	$rec_ids = array_map("intval", explode(",", $_REQUEST["bib_id"]));
	if (count($rec_ids) < 1) return;
	$rec_id = null;
} else {
	$rec_id = intval(@$_REQUEST["bib_id"]);
	if (! $rec_id) return;
}

if (@$_REQUEST["delete"]  && $rec_id) {
	$deletions = array_map("intval", $_REQUEST["delete"]);
}

if (count(@$deletions) > 0) {
	/* check the deletion bib_ids to make sure they actually involve the given rec_id */
	$res = mysql_query("select rec_id from records, rec_details
		where rd_rec_id=rec_id and rd_type in (202, 199) and rd_val=$rec_id and rec_id in (" . join(",", $deletions) . ")");

	$deletions = array();
	while ($row = mysql_fetch_row($res)) array_push($deletions, $row[0]);

	if ($deletions) {
		foreach ($deletions as $del_bib_id) {
			/* one delete query per rec_id, this way the archive_bib* versioning stuff works */
			mysql_query("delete from records where rec_id = $del_bib_id");
			mysql_query("delete from rec_details where rd_rec_id = $del_bib_id");
		}

		$relatedRecords = getAllRelatedRecords($rec_id);

		print "(" . json_format($relatedRecords) . ")";
	}
}
else if (@$_REQUEST["save-mode"] == "new") {
	if ($rec_id) {
		print "(" . json_format(saveRelationship(
			$rec_id,
			intval(@$_REQUEST["RelationType"]),
			intval($_REQUEST["OtherResource"]),
			intval($_REQUEST["InterpResource"]),
			@$_REQUEST["Title"],
			@$_REQUEST["Notes"],
			@$_REQUEST["StartDate"],
			@$_REQUEST["EndDate"]
		)) . ")";
	} else {
		$rv = array();
		foreach ($rec_ids as $id) {
			array_push($rv, saveRelationship(
				$id,
				intval(@$_REQUEST["RelationType"]),
				intval($_REQUEST["OtherResource"]),
				intval($_REQUEST["InterpResource"]),
				@$_REQUEST["Title"],
				@$_REQUEST["Notes"],
				@$_REQUEST["StartDate"],
				@$_REQUEST["EndDate"]
			));
		}
		print "(" . json_format($rv) . ")";
	}
}

function saveRelationship($rec_id, $reln_type, $other_bib_id, $interp_id, $title, $notes, $start_date, $end_date) {
	$relval = mysql_fetch_assoc(mysql_query("select rdl_value from rec_detail_lookups where rdl_id = $reln_type"));
	$relval = $relval['rdl_value'];
	mysql__insert("records", array(
		"rec_title" => "$title ($rec_id $relval $other_bib_id)",
                "rec_added"     => date('Y-m-d H:i:s'),
                "rec_modified"  => date('Y-m-d H:i:s'),
                "rec_type"   => 52,
                "rec_added_by_usr_id" => get_user_id()
	));
	$relnBibID = mysql_insert_id();

	if ($relnBibID > 0) {
		$query = "insert into rec_details (rd_rec_id, rd_type, rd_val) values ";
		$query .=   "($relnBibID, 160, '" . addslashes($title) . "')";
		$query .= ", ($relnBibID, 202, $rec_id)";
		$query .= ", ($relnBibID, 199, $other_bib_id)";
		$query .= ", ($relnBibID, 200, $reln_type)";
		if ($interp_id) $query .= ", ($relnBibID, 638, $interp_id)";
		if ($notes) $query .= ", ($relnBibID, 201, '" . addslashes($notes) . "')";
		if ($start_date) $query .= ", ($relnBibID, 177, '" . addslashes($start_date) . "')";
		if ($end_date) $query .= ", ($relnBibID, 178, '" . addslashes($end_date) . "')";

		mysql_query($query);
	}

	if (mysql_error()) {
		return array("error" => slash(mysql_error()));
	}
	else {
		$related = getAllRelatedRecords($rec_id, $relnBibID);
		return array("relationship" => array_pop($related));
	}
}

?>
