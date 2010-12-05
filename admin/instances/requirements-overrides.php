<?php

function getRecordRequirements($rt_id) {
	// returns [ rst_RecTypeID, rst_DetailTypeID, rst_NameInForm, rst_Prompt, rst_DefaultValue,
	//           rdr_required, rst_Repeats, rst_DisplayWidth, rst_RecordMatchOrder, rst_OrderInForm ]
	$rdrs = array();
	$rdros = array();
	$patched_rdrs = array();
	$colNames = array("rst_RecTypeID", "rst_DetailTypeID", "rst_NameInForm", "rst_Prompt", "rst_DefaultValue",
	                  "rdr_required", "rst_Repeats", "rst_DisplayWidth", "rst_RecordMatchOrder", "rst_OrderInForm");

	$res = mysql_query("select ".join(",", $colNames)." from defRecStructure where rst_RecTypeID=".$rt_id." order by rst_DetailTypeID");
	while ($row = mysql_fetch_assoc($res)) {
		$rdrs[$row["rst_DetailTypeID"]] = $row;
	}

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
		if ($rdr["rdr_required"] == "X") {
			unset($patched_rdrs[$detail_type]);
		}
	}

	// sort by rst_OrderInForm
	uasort($patched_rdrs, "cmp");
	return $patched_rdrs;
}

function override($base, $override) {
	// We don't use the idea of precedence any more.  Instead, ANY requiremence EXCEPT "required" can be overridden.
	// Other fields are also overridden regardless of requiremence.  --kj, 2008-08-05
	if (@$override) {
		$final = $base;
		$fields = array("rst_NameInForm", "rst_Prompt", "rst_DefaultValue",
		                "rst_Repeats", "rst_OrderInForm", "rst_DisplayWidth");
		foreach ($fields as $f) {
			if (@$override[$f]) $final[$f] = $override[$f];
		}
		if ($base["rdr_required"] != "Y"  &&  @$override["rdr_required"]) {
			$final["rdr_required"] = $override["rdr_required"];
		}
		return $final;
	} else {
		return $base;
	}
}

function cmp($a, $b) {
	if ($a["rst_OrderInForm"] == $b["rst_OrderInForm"]) return 0;
	return ($a["rst_OrderInForm"] < $b["rst_OrderInForm"]) ? -1 : 1;
}

/*no carriage returns after closing script tags please, it breaks xml script genenerator that uses this file as include */
?>
