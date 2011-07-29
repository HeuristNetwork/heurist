<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

define('dirname(__FILE__)', dirname(__FILE__));    // this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
/* require_once(dirname(__FILE__)."/../../common/config/initialise.php"); */
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

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

print "<html><head><link rel=stylesheet href='../../common/css/global.css'></head><body class='popup'>
<h2> Cleaning invalid characters from field values</h2>
This function removes invalid characters in the data fields in the database records.<br>
&nbsp;<hr>";

mysql_connection_overwrite(DATABASE);

$prevInvalidRecId = 0;
foreach ($textDetails as $textDetail) {
	if (! check($textDetail['dtl_Value'])){
		if ($prevInvalidRecId < $textDetail['dtl_RecID']) {
			print "<tr><td><a target=_blank href='".HEURIST_URL_BASE."records/edit/editRecord.html?bib_id=".
					$textDetail['dtl_RecID'] . "&db=".HEURIST_DBNAME. "'> " . $textDetail['dtl_RecID']. "</a></td></tr>\n";
			$prevInvalidRecId = $textDetail['dtl_RecID'];
			mysql__update("Records", "rec_ID=".$textDetail['dtl_RecID'],array("rec_Modified" => $now));
		}
		print "<tr><td><pre>" . "Invalid characters found in ".$textDetail['dty_Name'] . " field :</pre></td></tr>\n";
		$newText = str_replace($invalidChars ,$replacements,$textDetail['dtl_Value']);
		mysql__update("recDetails", "dtl_ID=".$textDetail['dtl_ID'], array("dtl_Value" =>$newText));
		if (mysql_error()) {
			print "<tr><td><pre>" . "Error ". mysql_error()."while updating to : ".htmlspecialchars($newText) . "</pre></td></tr>\n";
		}else {
			print "<tr><td><pre>" . "Updated to : ".htmlspecialchars($newText) . "</pre></td></tr>\n";
		}
	}
}

?>

<tr><td><p>[end of check]
</table>
</body>
</html>


<?php

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
