<html>
 <style>
  #form { width: 500px }
  #form input[type=text], #form textarea { width: 100% }
  .header div { font-weight: bold; margin-bottom: 10px }
  .detail-type { float: left; width: 200px }
  .current-val, .new-val { float: left; width: 400px }
  .clearall { clear: both }
  .shade div { background-color: #eeeeee }
  .detail-row .delete { background-color: #ff8888 }
  .detail-row.shade .delete { background-color: #ee8888 }
  .detail-row .insert { background-color: #88ff88 }
  .detail-row.shade .insert { background-color: #88ee88 }
 </style>
 <script type="text/javascript" src="../../external/jquery/jquery.js"></script>
 <script type="text/javascript">
	$(function () {
		$(".record .detail-row:even").addClass("shade");
	});
 </script>
 <h1>Record rollback</h1>
<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../search/saved/loading.php");
require_once(dirname(__FILE__)."/../../common/php/requirements-overrides.php");
require_once("rollback.php");

if (! is_admin()) {
	print "Administrator access required.";
	return;
}

$ids = @$_REQUEST["ids"];
$date = @$_REQUEST["date"];
$rollback = @$_REQUEST["rollback"];

if (! $ids  && ! $date) {
	showForm();
	return;
}

if ($rollback) {
	$n = rollRecordsBack(split(",", $ids), $date);
	$s = $n != 1 ? "s" : "";
	$state = $date ? "state as of $date" : "previous version";
	print "<p>$n record$s rolled back to $state</p>";
	print '<p><a href="'.HEURIST_URL_BASE.'search/search.html?q=ids:' . $ids . '">View updated records</a></p>';
} else {
	$rollbacks = getRecordRollbacks(split(",", $ids), $date);
	showRollbacks($rollbacks);
	showConfirm($ids, $date);
}


function showForm () {
?>
 <form method="get">
  <div id="form">
   <p>
    Rollback records to an earlier version.  Specify record IDs, or date, or both.
    If no date is given, records will be rolled back to their immediate previous version.
    If no record IDs are given, ALL records changed since the given date will be rolled
    back to their state as at the given date.
   </p>
   <div>Record IDs (comma-separated):</div>
   <textarea name="ids"></textarea>
   <div>Date (YYYY-MM-DD hh:mm:ss):</div>
   <input type="text" name="date">
   <br><br>
   <input type="submit" value="show rollback changes">
  </div>
 </form>
<?php
}


function showConfirm ($ids, $date) {
?>
 <br><br>
 <form method="get">
   <input type="hidden" name="ids" value="<?= $ids ?>">
   <input type="hidden" name="date" value="<?= $date ?>">
   <input type="hidden" name="rollback" value="1">
   <input type="submit" value="roll back">
 </form>
<?php
}


function showRollbacks ($rollbacks) {
	$s = count($rollbacks) != 1 ? "s" : "";
	print "<p>" . count($rollbacks) . " record$s can be rolled back.</p>";

	foreach ($rollbacks as $rec_id => $changes) {
		$record = loadRecord($rec_id);
		showRecordRollback($record, $changes);
	}
}


function showRecordRollback ($record, $changes) {
	print '<div class="record">';
	print '<div class="header">';
	print '<div class="detail-type">';
	print $record["rec_ID"] . ": " . $record["rec_Title"];
	print '</div>';
	print '<div class="current-val">Current values</div>';
	print '<div class="new-val">Will be changed to</div>';
	print '<div class="clearall"></div>';
	print '</div>';

	$reqs = getRecordRequirements($record["rec_RecTypeID"]);
	$detail_names = mysql__select_assoc("defDetailTypes", "dty_ID", "dty_Name", 1);


	foreach ($reqs as $dt => $req) {
		if (array_key_exists($dt, $record["details"])) {
			$values = $record["details"][$dt];
			showDetails($dt . ': ' . $req["rst_NameInForm"], $values, $changes);
		}
	}

	foreach ($record["details"] as $dt => $values) {
		if (! array_key_exists($dt, $reqs)) {
			showDetails($dt . ': ' . $detail_names[$dt], $values, $changes);
		}
	}

	foreach ($changes["inserts"] as $insert) {
		if (array_key_exists($insert["ard_DetailTypeID"], $reqs)) {
			$name = $reqs[$insert["ard_DetailTypeID"]]["rst_NameInForm"];
		} else {
			$name = $detail_names[$insert["ard_DetailTypeID"]];
		}
		showDetail(
			$insert["ard_DetailTypeID"] . ': ' . $name,
			"&nbsp;",
			'<div class="insert">' . getDetailUpdateString($insert) . '</div>'
		);
	}

	print '</div>';
}


function showDetails ($name, $values, $changes) {
	foreach ($values as $rd_id => $rd_val) {
		showDetail($name, getCurrentValString($rd_val), getChangedValString($rd_id, $changes));
	}
}


function showDetail ($name, $current, $new) {
	print '<div class="detail-row">';
	print '<div class="detail-type">';
	print $name;
	print '</div>';
	print '<div class="current-val">';
	print $current;
	print '</div>';
	print '<div class="new-val">';
	print $new;
	print '</div>';
	print '<div class="clearall">';
	print '</div>';
	print '</div>';
}


function getCurrentValString ($val) {
	if (is_array($val)) {
		if (array_key_exists("file", $val)) {
			return 'file: [<a href="' . $val["file"]["URL"] . '">' . $val["file"]["origName"] . '</a>]';
		}
		else if (array_key_exists("geo", $val)) {
			return 'geo: ' . $val["geo"]["type"] . ': [' . substr($val["geo"]["wkt"], 0, 30) . ' ... ]';
		}
		else if (array_key_exists("id", $val)) {
			return '=> [' . $val["id"] . '] <a href="'.HEURIST_URL_BASE.'records/viewrec/view.php?bib_id=' . $val["id"] . '">' . $val["title"] . '</a>';
		}
	} else {
		return $val;
	}
}

function getDetailUpdateString ($ard_row) {
	$s = '';
	if ($ard_row["ard_Value"]) {
		$s = $ard_row["ard_Value"];
	}
	if ($ard_row["ard_UploadedFileID"]) {
		$s = $ard_row["ard_UploadedFileID"];
	}
	if ($ard_row["ard_Geo"]) {
		$s .= ' [' . substr($ard_row["ard_Geo"], 0, 30) . ' ... ]';
	}
	return $s;
}

function getChangedValString ($rd_id, $changes) {
	foreach ($changes["updates"] as $update) {
		if ($update["ard_ID"] == $rd_id) {
			return '<div class="update">' . getDetailUpdateString($update) . '</div>';
		}
	}
	foreach ($changes["deletes"] as $delete) {
		if ($delete == $rd_id) {
			return '<div class="delete">[delete]</div>';
		}
	}
	return "&nbsp;";
}

?>
</html>
