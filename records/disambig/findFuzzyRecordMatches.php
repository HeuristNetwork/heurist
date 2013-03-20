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
* util to find like records
*
* @author      Tom Murtagh
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Util
*/



function findFuzzyMatches($fields, $rec_types, $rec_id=NULL, $fuzziness=NULL) {

	if (! $fuzziness) $fuzziness = 0.5;

	// Get some data about the matching data for the given record type
	$types = mysql__select_assoc('defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID',
								'dty_ID', 'dty_Type', 'rst_RecTypeID=' . $rec_types[0] .
														' and rst_RecordMatchOrder or rst_DetailTypeID='.DT_NAME);
	$fuzzyFields = array();
	$strictFields = array();
	foreach ($fields as $key => $vals) {
		if (! preg_match('/^t:(\d+)/', $key, $matches)) continue;
		$rdt_id = $matches[1];

		if (! @$types[$rdt_id]) continue;
		if (! $vals) continue;

		switch ($types[$rdt_id]) {
			case "blocktext": case "freetext": case "urlinclude":
			foreach ($vals as $val)
				if (trim($val)) array_push($fuzzyFields, array($rdt_id, trim($val)));
			break;

			case "integer": case "float":
			case "date": case "year": case "file":
			case "enum": case "boolean":
			case "urlinclude":
			case "relationtype": case "resource":
			foreach ($vals as $val)
				if (trim($val)) array_push($strictFields, array($rdt_id, trim($val)));
			break;

			case "separator":	// this should never happen since separators are not saved as details, skip if it does
			case "relmarker": // saw seems like relmarkers are external to the record and should not be part of matching
			case "fieldsetmarker":
			case "calculated":
			default:
			continue;
		}
	}
	if (count($fuzzyFields) == 0  &&  count($strictFields) == 0) return;

	$groups = get_group_ids();
	if (!is_array($groups)){
		$groups = array();
	}
	if(is_logged_in()){
		array_push($groups,get_user_id());
		array_push($groups,0);
	}
	$groupPred = count($groups) > 0 ? "rec_OwnerUGrpID in (".join(",",$groups).") or ":"";
	$tables = "records";
	$predicates = "rec_RecTypeID=$rec_types[0] and ! rec_FlagTemporary and ($groupPred not rec_NonOwnerVisibility='hidden')" . ($rec_id ? " and rec_ID != $rec_id" : "");
	$N = 0;
	foreach ($fuzzyFields as $field) {
		list($rdt_id, $val) = $field;
		$threshold = intval((strlen($val)+1) * $fuzziness);

		++$N;
		$tables .= ", recDetails bd$N";
		$predicates .= " and (bd$N.dtl_RecID=rec_ID and bd$N.dtl_DetailTypeID=$rdt_id and limited_levenshtein(bd$N.dtl_Value, '".addslashes($val)."', $threshold) is not null)";
	}
	foreach ($strictFields as $field) {
		list($rdt_id, $val) = $field;

		++$N;
		$tables .= ", recDetails bd$N";
		$predicates .= " and (bd$N.dtl_RecID=rec_ID and bd$N.dtl_DetailTypeID=$rdt_id and bd$N.dtl_Value = '".addslashes($val)."')";
	}

	$matches = array();
	$res = mysql_query("select rec_ID as id, rec_Title as title, rec_Hash as hhash from $tables where $predicates order by rec_Title limit 100");
/*****DEBUG****///error_log("approx-matching: select rec_ID as id, rec_Title as title, rec_Hash as hhash from $tables where $predicates order by rec_Title limit 100");
	while ($bib = mysql_fetch_assoc($res)) {
		array_push($matches, $bib);
	}

	return $matches;
}

?>
