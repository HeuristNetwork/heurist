<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../../common/config/heurist-instances.php");

mysql_connection_select(DATABASE);

$invalidChars = array(chr(0),chr(1),chr(2),chr(3),chr(4),chr(5),chr(6),chr(7),chr(8),chr(11),chr(12),chr(14),chr(15),chr(16),chr(17),chr(18),chr(19),chr(20),chr(21),chr(22),chr(23),chr(24),chr(25),chr(26),chr(27),chr(28),chr(29),chr(30),chr(31)); // invalid chars that need to be stripped from the data.
$replacements = array("?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?"," ","?","?","?","?","?");
$textDetails = array();
$res = mysql_query("SELECT rd_id,rd_rec_id,rd_val,rdt_name ".
					"FROM rec_details left join rec_detail_types on rd_type = rdt_id  ".
					"WHERE rdt_type in ('freetext','blocktext') ORDER BY rd_rec_id");
while ($row = mysql_fetch_assoc($res)) {
	array_push($textDetails, $row);
}

print "<table>\n<tr><td> checking details for invalid characters </td></tr>\n";

mysql_connection_overwrite(DATABASE);

$prevInvalidRecId = 0;
foreach ($textDetails as $textDetail) {
	if (! check($textDetail['rd_val'])){
		if ($prevInvalidRecId < $textDetail['rd_rec_id']) {
			print "<tr><td><a target=_blank href='".HEURIST_URL_BASE."data/records/editrec/heurist-edit.html?bib_id=".
					$textDetail['rd_rec_id'] . "&instance=".HEURIST_INSTANCE. "'> " . $textDetail['rd_rec_id']. "</a></td></tr>\n";
			$prevInvalidRecId = $textDetail['rd_rec_id'];
			mysql__update("records", "rec_id=".$textDetail['rd_rec_id'],array("rec_modified" => $now));
		}
		print "<tr><td><pre>" . "Invalid characters found in ".$textDetail['rdt_name'] . " field :</pre></td></tr>\n";
		$newText = str_replace($invalidChars ,$replacements,$textDetail['rd_val']);
		mysql__update("rec_details", "rd_id=".$textDetail['rd_id'], array("rd_val" =>$newText));
		if (mysql_error()) {
			print "<tr><td><pre>" . "Error ". mysql_error()."while updating to : ".htmlspecialchars($newText) . "</pre></td></tr>\n";
		}else {
			print "<tr><td><pre>" . "Updated to : ".htmlspecialchars($newText) . "</pre></td></tr>\n";
		}
	}
}
print "<tr><td>finished</td></tr></table>\n";

// END of OUTPUT


function check($text) {
	global $invalidChars;
	foreach ($invalidChars as $charCode){
		if (strpos($text,$charCode)) {
			error_log("found invalid char " );
			return false;
		}
	}
	return true;
}

?>
