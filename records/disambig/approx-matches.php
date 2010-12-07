<?php

function findFuzzyMatches($fields, $rec_types, $rec_id=NULL, $fuzziness=NULL) {

	if (! $fuzziness) $fuzziness = 0.5;

	// Get some data about the matching data for the given record type
	$types = mysql__select_assoc('defRecStructure left join defDetailTypes on rst_DetailTypeID=dty_ID',
	                             'dty_ID', 'dty_Type', 'rst_RecTypeID=' . $rec_types[0] . ' and rst_RecordMatchOrder or rst_DetailTypeID=160');
	$fuzzyFields = array();
	$strictFields = array();
	foreach ($fields as $key => $vals) {
		if (! preg_match('/^t:(\d+)/', $key, $matches)) continue;
		$rdt_id = $matches[1];

		if (! @$types[$rdt_id]) continue;
		if (! $vals) continue;

		switch ($types[$rdt_id]) {
		    case "blocktext": case "freetext":
			foreach ($vals as $val)
				if (trim($val)) array_push($fuzzyFields, array($rdt_id, trim($val)));
			break;

		    case "integer": case "float":
		    case "date": case "year":
		    case "enum": case "boolean":
		    case "resource":
			foreach ($vals as $val)
				if (trim($val)) array_push($strictFields, array($rdt_id, trim($val)));
			break;

			case "separator":	// this should never happen since separators are not saved as details, skip if it does
			case "relmarker" : // saw seems like relmarkers are external to the record and should not be part of matching
			default:
			continue;
		}
	}
	if (count($fuzzyFields) == 0  &&  count($strictFields) == 0) return;


	$tables = "records";
	$predicates = "rec_RecTypeID=$rec_types[0] and ! rec_FlagTemporary and (rec_OwnerUGrpID=0 or rec_NonOwnerVisibility='viewable')" . ($rec_id ? " and rec_ID != $rec_id" : "");
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
	error_log("approx-matching: select rec_ID as id, rec_Title as title, rec_Hash as hhash from $tables where $predicates order by rec_Title limit 100");
	while ($bib = mysql_fetch_assoc($res)) {
		array_push($matches, $bib);
	}

	return $matches;
}

?>
