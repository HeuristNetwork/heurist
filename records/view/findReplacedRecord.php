<?php

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');


// checks if a records record has been 'replaced' - superceded by another records record.
// there could theoretically be a chain of supercession (is that a word?), so we try to
// follow the chain until we get an authoritative record.
// returns:  - the original rec_ID if the record has not been replaced
//           - rec_ID of the replacement record if the record has been replaced
//           - null if the chain is broken

function get_replacement_bib_id ($rec_id) {
	$res = mysql_query("select rfw_NewRecID from recForwarding where rfw_OldRecID=" . intval($rec_id));
	$recurseLimit = 10;
	while (mysql_num_rows($res) > 0) {
		$row = mysql_fetch_row($res);
		$rec_id = $row[0];
		$replaced = true;
		$res = mysql_query("select rfw_NewRecID from recForwarding where rfw_OldRecID=" . $rec_id);

		if ($recurseLimit-- === 0) { break; }
	}

	return $rec_id;
}

?>
