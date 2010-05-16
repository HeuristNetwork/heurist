<?php

require_once('db.php');


// checks if a records record has been 'replaced' - superceded by another records record.
// there could theoretically be a chain of supercession (is that a word?), so we try to
// follow the chain until we get an authoritative record.
// returns:  - the original rec_id if the record has not been replaced
//           - rec_id of the replacement record if the record has been replaced
//           - null if the chain is broken

function get_replacement_bib_id ($rec_id) {
	$res = mysql_query("select new_rec_id from aliases where old_rec_id=" . intval($rec_id));
	$recurseLimit = 10;
	while (mysql_num_rows($res) > 0) {
		$row = mysql_fetch_row($res);
		$rec_id = $row[0];
		$replaced = true;
		$res = mysql_query("select new_rec_id from aliases where old_rec_id=" . $rec_id);

		if ($recurseLimit-- === 0) { break; }
	}

	return $rec_id;
}

?>
