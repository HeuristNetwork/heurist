<?php

function getRecordRequirements($rt_id) {
	// returns [ rdr_rec_type, rdr_rdt_id, rdr_name, rdr_prompt, rdr_default,
	//           rdr_required, rdr_repeatable, rdr_size, rdr_match, rdr_order ]
	$rdrs = array();
	$rdros = array();
	$patched_rdrs = array();
	$colNames = array("rdr_rec_type", "rdr_rdt_id", "rdr_name", "rdr_prompt", "rdr_default",
	                  "rdr_required", "rdr_repeatable", "rdr_size", "rdr_match", "rdr_order");

	$res = mysql_query("select ".join(",", $colNames)." from rec_detail_requirements where rdr_rec_type=".$rt_id." order by rdr_rdt_id");
	while ($row = mysql_fetch_assoc($res)) {
		$rdrs[$row["rdr_rdt_id"]] = $row;
	}

	$res = mysql_query("select ".join(",", $colNames)." from rec_detail_requirements_overrides where rdr_rec_type=".$rt_id." and ! rdr_wg_id order by rdr_rdt_id");
	while ($row = mysql_fetch_assoc($res)) {
		$rdros[$row["rdr_rdt_id"]] = $row;
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

	// sort by rdr_order
	uasort($patched_rdrs, "cmp");
	return $patched_rdrs;
}

function override($base, $override) {
	// We don't use the idea of precedence any more.  Instead, ANY requiremence EXCEPT "required" can be overridden.
	// Other fields are also overridden regardless of requiremence.  --kj, 2008-08-05
	if (@$override) {
		$final = $base;
		$fields = array("rdr_name", "rdr_prompt", "rdr_default", 
		                "rdr_repeatable", "rdr_order", "rdr_size");
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
	if ($a["rdr_order"] == $b["rdr_order"]) return 0;
	return ($a["rdr_order"] < $b["rdr_order"]) ? -1 : 1;
}

/*no carriage returns after closing script tags please, it breaks xml script genenerator that uses this file as include */
?>
