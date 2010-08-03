<?php

require_once(dirname(__FILE__)."/../../../common/connect/db.php");
require_once(dirname(__FILE__)."/../../../common/connect/cred.php");

if (! is_logged_in()) return;

define("SAVE_URI", "disabled");

mysql_connection_db_select(DATABASE);


$bib_ids_to_fetch = array_map('intval', explode(',', $_REQUEST['ids']));

$bib_data = array();

while (count($bib_ids_to_fetch) > 0) {
	$rec_id = array_shift($bib_ids_to_fetch);
	if ($bib_data[$rec_id]) continue;

	$res = mysql_query("select rec_title, rec_type, rec_scratchpad from records where rec_id = $rec_id");
	$row = mysql_fetch_assoc($res);
	if (! @$row) continue;

	$bib_data[$rec_id] = array();
	$bib_data[$rec_id]["title"] = $row["rec_title"];
	$bib_data[$rec_id]["reftype"] = $row["rec_type"];
	$bib_data[$rec_id]["notes"] = $row["rec_scratchpad"];

	$bib_data[$rec_id]["values"] = array();
	$res = mysql_query("select rd_type, rd_val, rdt_type from rec_details left join rec_detail_types on rdt_id=rd_type left join rec_detail_requirements on rdr_rdt_id=rdt_id and rdr_rec_type = " . intval($row["rec_type"]) . " where rd_rec_id = $rec_id order by rdr_order, rdt_id, rd_id");
	while ($bd = mysql_fetch_assoc($res)) {
		if (! @$bib_data[$rec_id]["values"][$bd["rd_type"]]) $bib_data[$rec_id]["values"][$bd["rd_type"]] = array();
		array_push($bib_data[$rec_id]["values"][$bd["rd_type"]], $bd["rd_val"]);

		if ($bd["rdt_type"] == "resource") array_push($bib_ids_to_fetch, intval($bd["rd_val"]));
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

  <script src="<?=HEURIST_SITE_PATH?>common/js/heurist.js"></script>
  <script src="<?=HEURIST_SITE_PATH?>common/php/heurist-obj-common.php"></script>
  <script src="side-by-side.js"></script>

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
