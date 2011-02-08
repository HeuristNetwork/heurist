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

require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");

if (! is_logged_in()) return;

define("SAVE_URI", "disabled");

mysql_connection_db_select(DATABASE);


$bib_ids_to_fetch = array_map('intval', explode(',', $_REQUEST['ids']));

$bib_data = array();

while (count($bib_ids_to_fetch) > 0) {
	$rec_id = array_shift($bib_ids_to_fetch);
	if ($bib_data[$rec_id]) continue;

	$res = mysql_query("select rec_Title, rec_RecTypeID, rec_ScratchPad from Records where rec_ID = $rec_id");
	$row = mysql_fetch_assoc($res);
	if (! @$row) continue;

	$bib_data[$rec_id] = array();
	$bib_data[$rec_id]["title"] = $row["rec_Title"];
	$bib_data[$rec_id]["rectype"] = $row["rec_RecTypeID"];
	$bib_data[$rec_id]["notes"] = $row["rec_ScratchPad"];

	$bib_data[$rec_id]["values"] = array();
	$res = mysql_query("select dtl_DetailTypeID, dtl_Value, dty_Type from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID left join defRecStructure on rst_DetailTypeID=dty_ID and rst_RecTypeID = " . intval($row["rec_RecTypeID"]) . " where dtl_RecID = $rec_id order by rst_OrderInForm, dty_ID, dtl_ID");
	while ($bd = mysql_fetch_assoc($res)) {
		if (! @$bib_data[$rec_id]["values"][$bd["dtl_DetailTypeID"]]) $bib_data[$rec_id]["values"][$bd["dtl_DetailTypeID"]] = array();
		array_push($bib_data[$rec_id]["values"][$bd["dtl_DetailTypeID"]], $bd["dtl_Value"]);

		if ($bd["dty_Type"] == "resource") array_push($bib_ids_to_fetch, intval($bd["dtl_Value"]));
	}
}

?>
<html>
 <head>
  <title>HEURIST - compare resources</title>
  <link rel=stylesheet href="<?=HEURIST_SITE_PATH?>common/css/heurist.css">

 <script>
  var bibs = <?= json_format($bib_data) ?>;
 </script>

 </head>
 <body>

  <script src="<?=HEURIST_SITE_PATH?>common/js/utilsLoad.js"></script>
  <script src="<?=HEURIST_SITE_PATH?>common/php/loadCommonInfo.php"></script>
  <script src="showSideBySide.js"></script>

 <style>
body { margin: 0; }
* { font-size: 11px; }
 </style>

 <div style="width: 100%; padding: 3px 0; background-color: #C00000; font-weight: bold;">&nbsp;Resource comparison</div>

 <table border=0 cellpadding=3 cellspacing=2 style="width: 100%;"><tbody>
  <tr>
   <td></td>
   <td style="width: 50%; background-color: #FFFFDD;" id=bib0></td>
   <td style="width: 50%;" id=bib1></td>
  </tr>

  <tr>
   <td valign=top align=right><b>Title</b></td>
   <td style="background-color: #FFFFDD;" valign=top id=title0></td>
   <td valign=top id=title1></td>
  </tr>

  <tr>
   <td valign=top align=right><b>Details</b></td>
   <td style="background-color: #FFFFDD;" valign=top id=details0></td>
   <td valign=top id=details1></td>
  </tr>

  <tr>
   <td valign=top align=right><b>Notes</b></td>
   <td style="background-color: #FFFFDD;" valign=top id=notes0></td>
   <td valign=top id=notes1></td>
  </tr>

 </tbody></table>

<script>
var bibIDs = (location.search || "").replace(/^.*ids=/, "").split(/,/);
var altBibIDs = [];
if (bibIDs.length > 2) {
	for (var i=1; i < bibIDs.length; ++i)
		altBibIDs.push(bibIDs[i]);
}

if (bibIDs[0]) fillInColumn(0, bibIDs[0]);
if (bibIDs[1]) fillInColumn(1, bibIDs[1]);
</script>

</body>
</html>
