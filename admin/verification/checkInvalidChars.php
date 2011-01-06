<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../../common/config/initialise.php");

mysql_connection_select(DATABASE);

$invalidChars = array(chr(0),chr(1),chr(2),chr(3),chr(4),chr(5),chr(6),chr(7),chr(8),chr(11),chr(12),chr(14),chr(15),chr(16),chr(17),chr(18),chr(19),chr(20),chr(21),chr(22),chr(23),chr(24),chr(25),chr(26),chr(27),chr(28),chr(29),chr(30),chr(31)); // invalid chars that need to be stripped from the data.
$replacements = array("?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?","?"," ","?","?","?","?","?");
$textDetails = array();
$res = mysql_query("SELECT dtl_ID,dtl_RecID,dtl_Value,dty_Name ".
					"FROM recDetails left join defDetailTypes on dtl_DetailTypeID = dty_ID  ".
					"WHERE dty_Type in ('freetext','blocktext') ORDER BY dtl_RecID");
while ($row = mysql_fetch_assoc($res)) {
	array_push($textDetails, $row);
}

print "<table>\n<tr><td> checking details for invalid characters </td></tr>\n";

$prevInvalidRecId = 0;
foreach ($textDetails as $textDetail) {
	if (! check($textDetail['dtl_Value'])){
		if ($prevInvalidRecId < $textDetail['dtl_RecID']) {
			print "<tr><td><a target=_blank href='".HEURIST_URL_BASE."records/edit/editRecord.html?bib_id=".
					$textDetail['dtl_RecID'] . "&instance=".HEURIST_INSTANCE. "'> " . $textDetail['dtl_RecID']. "</a></td></tr>\n";
			$prevInvalidRecId = $textDetail['dtl_RecID'];
		}
		print "<tr><td><pre>" . "Invalid characters found in ".$textDetail['dty_Name'] . " field :</pre></td></tr>\n";
		print "<tr><td><pre>" . "Corrected text : ".htmlspecialchars( str_replace($invalidChars ,$replacements,$textDetail['dtl_Value'])) . "</pre></td></tr>\n";
		print "<tr><td><pre>" . "Invalid text : ". htmlspecialchars($textDetail['dtl_Value']) . "</pre></td></tr>\n";
	}
}
print "<tr><td>finished</td></tr></table>\n";

// END of OUTPUT


function check($text) {
	global $invalidChars;
	foreach ($invalidChars as $charCode){
		//$pattern = "". chr($charCode);
		if (strpos($text,$charCode)) {
			error_log("found invalid char " );
			return false;
		}
	}
	return true;
}

?>
