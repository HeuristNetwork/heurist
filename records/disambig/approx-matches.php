<?php

function findFuzzyMatches($fields, $rec_types, $rec_id=NULL, $fuzziness=NULL) {

	if (! $fuzziness) $fuzziness = 0.5;

	// Get some data about the matching data for the given record type
	$types = mysql__select_assoc('rec_detail_requirements left join rec_detail_types on rdr_rdt_id=rdt_id',
	                             'rdt_id', 'rdt_type', 'rdr_rec_type=' . $rec_types[0] . ' and rdr_match or rdr_rdt_id=160');
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
	$predicates = "rec_type=$rec_types[0] and ! rec_temporary and (rec_wg_id=0 or rec_visibility='viewable')" . ($rec_id ? " and rec_id != $rec_id" : "");
	$N = 0;
	foreach ($fuzzyFields as $field) {
		list($rdt_id, $val) = $field;
		$threshold = intval((strlen($val)+1) * $fuzziness);

		++$N;
		$tables .= ", rec_details bd$N";
		$predicates .= " and (bd$N.rd_rec_id=rec_id and bd$N.rd_type=$rdt_id and limited_levenshtein(bd$N.rd_val, '".addslashes($val)."', $threshold) is not null)";
	}
	foreach ($strictFields as $field) {
		list($rdt_id, $val) = $field;

		++$N;
		$tables .= ", rec_details bd$N";
		$predicates .= " and (bd$N.rd_rec_id=rec_id and bd$N.rd_type=$rdt_id and bd$N.rd_val = '".addslashes($val)."')";
	}

	$matches = array();
	$res = mysql_query("select rec_id as id, rec_title as title, rec_hhash as hhash from $tables where $predicates order by rec_title limit 100");
	error_log("approx-matching: select rec_id as id, rec_title as title, rec_hhash as hhash from $tables where $predicates order by rec_title limit 100");
	while ($bib = mysql_fetch_assoc($res)) {
		array_push($matches, $bib);
	}

	return $matches;
}

?>
