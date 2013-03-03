<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

?>

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

define('ISSERVICE',1);
define("SAVE_URI", "disabled");

require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
//require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
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

$addRecDefaults = @$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']["display-preferences"]['addRecDefaults'];
if ($addRecDefaults){
	if ($addRecDefaults[0]){
		$userDefaultRectype = intval($addRecDefaults[0]);
	}
	if ($addRecDefaults[1]){
		$userDefaultOwnerGroupID = intval($addRecDefaults[1]);
	}
	if ($addRecDefaults[2]){
		$userDefaultVisibility = $addRecDefaults[2];
	}
}

if (@$_REQUEST["delete"]  && $recID) {
	$deletions = array_map("intval", $_REQUEST["delete"]);
}

if (count(@$deletions) > 0) {
	/* check the deletion recIDs to make sure they actually involve the given rec_ID */
	$res = mysql_query("select rec_ID from Records, recDetails
		where dtl_RecID=rec_ID and dtl_DetailTypeID in (".
								(defined('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:"0").",".
								(defined('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:"0").") and dtl_Value=$recID and rec_ID in (" . join(",", $deletions) . ")");

	$deletions = array();
	while ($row = mysql_fetch_row($res)) array_push($deletions, $row[0]);
	if ($deletions) {
		foreach ($deletions as $del_recID) {
			/* one delete query per rec_ID, this way the archive_bib* versioning stuff works */
			mysql_query("update Records set rec_Modified=now() where rec_ID = $del_recID");
/*****DEBUG****///error_log("in delete code $del_recID ");
			mysql_query("delete from recDetails where dtl_RecID = $del_recID");
/*****DEBUG****///error_log("in deleted details for record $del_recID ".mysql_error());
			mysql_query("delete from Records where rec_ID = $del_recID");
/*****DEBUG****///error_log("in deleted delete record $del_recID ".mysql_error());
			if (mysql_error()) {
				print "(" . json_format(array("error" => slash(mysql_error()))) . ")";
				return;
			}
		}


		$relatedRecords = getAllRelatedRecords($recID);

		print "(" . json_format($relatedRecords) . ")";
	}
}else if (@$_REQUEST["save-mode"] == "new") {
	if ($recID) {
		print "(" . json_format(saveRelationship(
			$recID,
			intval(@$_REQUEST["RelTermID"]),
			intval(@$_REQUEST["RelatedRecID"]),
			intval(@$_REQUEST["InterpRecID"]),
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
				intval(@$_REQUEST["RelatedRecID"]),
				intval(@$_REQUEST["InterpRecID"]),
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
	mysql__insert("Records", array("rec_Title" => "$title ($srcTitle $relval $trgTitle)",
					"rec_Added"     => date('Y-m-d H:i:s'),
					"rec_Modified"  => date('Y-m-d H:i:s'),
					"rec_RecTypeID"   => RT_RELATION,
					'rec_OwnerUGrpID' => (intval(@$_REQUEST['rec_owner'])?intval($_REQUEST['rec_owner']):
											(@$userDefaultOwnerGroupID ? $userDefaultOwnerGroupID :
											(defined('HEURIST_NEWREC_OWNER_ID') ? HEURIST_NEWREC_OWNER_ID: get_user_id()))),
					"rec_AddedByUGrpID" => get_user_id()));

	if (mysql_error()) {
		return array("error" => slash(mysql_error()));
	}

	$relnRecID = mysql_insert_id();
	$res = null;
/*****DEBUG****///error_log("defines title=".DT_NAME.", prim = ".DT_PRIMARY_RESOURCE);
	if ($relnRecID > 0 &&  defined('DT_NAME') &&
				defined('DT_RELATION_TYPE') &&
				defined('DT_TARGET_RESOURCE') &&
				defined('DT_PRIMARY_RESOURCE')) {
		$query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ";
		$query .=   "($relnRecID, ".DT_NAME.", '" . addslashes($title) . "')";
		$query .= ", ($relnRecID, ".DT_PRIMARY_RESOURCE.", $recID)";
		$query .= ", ($relnRecID, ".DT_TARGET_RESOURCE.", $trgRecID)";
		$query .= ", ($relnRecID, ".DT_RELATION_TYPE.", $relTermID)";
		if ($interpRecID && defined('DT_INTERPRETATION_REFERENCE'))
			$query .= ", ($relnRecID, ".DT_INTERPRETATION_REFERENCE.", $interpRecID)";
		if ($notes && defined('DT_SHORT_SUMMARY'))
			$query .= ", ($relnRecID, ".DT_SHORT_SUMMARY.", '" . addslashes($notes) . "')";
		if ($start_date && defined('DT_START_DATE'))
			$query .= ", ($relnRecID, ".DT_START_DATE.", '" . addslashes($start_date) . "')";
		if ($end_date && defined('DT_END_DATE'))
			$query .= ", ($relnRecID, ".DT_END_DATE.", '" . addslashes($end_date) . "')";
/*****DEBUG****///error_log(" rel save query = $query");
		$res = mysql_query($query);
/*****DEBUG****///error_log("res = $res  error " .mysql_error());
	}

	if (mysql_error($res)) {
		return array("error" => slash(mysql_error($res)));
	} else {
//		$related = getAllRelatedRecords($recID, $relnRecID);
		$related = getAllRelatedRecords($recID);
		return array("relationship" => $related,"relnRecID" => $relnRecID);
	}
}

?>
