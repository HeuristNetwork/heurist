<?php

/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


function getRecordRequirements($rt_id) {
	// returns [ rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayPrompt, rst_DefaultValue,
	//           rst_RequirementType, rst_MaxValues, rst_DisplayWidth, rst_RecordMatchOrder, rst_DisplayOrder ]
	$rdrs = array();
//	$rdros = array();
//	$patched_rdrs = array();
	$colNames = array("rst_RecTypeID", "rst_DetailTypeID", "rst_DisplayName", "rst_DisplayPrompt", "rst_DefaultValue",
	                  "rst_RequirementType", "rst_MaxValues", "rst_MinValues","rst_DisplayWidth", "rst_RecordMatchOrder", "rst_DisplayOrder");

	$res = mysql_query("select ".join(",", $colNames)." from defRecStructure where rst_RecTypeID=".$rt_id." order by rst_DetailTypeID");
	while ($row = mysql_fetch_assoc($res)) {
		$rdrs[$row["rst_DetailTypeID"]] = $row;
	}
/* Override merge code removed by SAW on 13/1/11

	$res = mysql_query("select ".join(",", $colNames)." from rec_detail_requirements_overrides where rst_RecTypeID=".$rt_id." and ! rdr_wg_id order by rst_DetailTypeID");
	while ($row = mysql_fetch_assoc($res)) {
		$rdros[$row["rst_DetailTypeID"]] = $row;
	}

	// calculate overridden fields
	foreach ($rdrs as $detail_type => $rdr) {
		$rdro = @$rdros[$detail_type];
		$patched_rdrs[$detail_type] = override($rdr, $rdro);
	}

	// add any additional fields
	foreach ($rdros as $detail_type => $rdro) {
		if (! @$patched_rdrs[$detail_type]) {
			$patched_rdrs[$detail_type] = $rdro;
		}
	}

	// remove any "not permitted" fields
	foreach ($patched_rdrs as $detail_type => $rdr) {
		if ($rdr["rst_RequirementType"] == 'Forbidden') {
			unset($patched_rdrs[$detail_type]);
		}
	}
*/
	// sort by rst_DisplayOrder
	uasort($rdrs, "cmp");
	return $rdrs;
}
/* Override merge code removed by SAW on 13/1/11
function override($base, $override) {
	// We don't use the idea of precedence any more.  Instead, ANY requiremence EXCEPT "required" can be overridden.
	// Other fields are also overridden regardless of requiremence.  --kj, 2008-08-05
	if (@$override) {
		$final = $base;
		$fields = array("rst_DisplayName", "rst_DisplayPrompt", "rst_DefaultValue",
		                "rst_MaxValues", "rst_DisplayOrder", "rst_DisplayWidth");
		foreach ($fields as $f) {
			if (@$override[$f]) $final[$f] = $override[$f];
		}
		if ($base["rst_RequirementType"] != 'Required'  &&  @$override["rst_RequirementType"]) {
			$final["rst_RequirementType"] = $override["rst_RequirementType"];
		}
		return $final;
	} else {
		return $base;
	}
}
*/
function cmp($a, $b) {
	if ($a["rst_DisplayOrder"] == $b["rst_DisplayOrder"]) return 0;
	return ($a["rst_DisplayOrder"] < $b["rst_DisplayOrder"]) ? -1 : 1;
}

/*no carriage returns after closing script tags please, it breaks xml script genenerator that uses this file as include */
?>
