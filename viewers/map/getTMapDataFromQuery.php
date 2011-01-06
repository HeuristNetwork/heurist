<?php

define('SEARCH_VERSION', 1);

require_once(dirname(__FILE__)."/../../common/connect/db.php");
require_once(dirname(__FILE__)."/../../common/connect/cred.php");

require_once(dirname(__FILE__)."/../../search/advanced/adv-search.php");

require_once("PolylineEncoder.php");

header("Content-type: text/javascript");


mysql_connection_db_select(DATABASE);

if (! @$_REQUEST['q']  ||  (@$_REQUEST['ver'] && intval(@$_REQUEST['ver']) < SEARCH_VERSION))
        construct_legacy_search();      // migration path

if ($_REQUEST['w'] == 'B'  ||  $_REQUEST['w'] == 'bookmark')
	$search_type = BOOKMARK;	// my bookmarks
else
	$search_type = BOTH;	// all records

// find all matching records
$cols = "rec_ID as bibID, rec_RecTypeID as reftype, rec_Title as title, rec_URL as URL";
$query = REQUEST_to_query("select $cols ", $search_type);

error_log($query);
$res = mysql_query($query);
print mysql_error();

$records = array();
$bibIDs = array();
while ($bib = mysql_fetch_assoc($res)) {
	$bibID = $bib["bibID"];
	if (! $bibID) continue;

	$records[$bibID] = $bib;
	array_push($bibIDs, $bibID);
}

foreach ($bibIDs as $bibID) {
	$res = mysql_query("select a.dtl_Value, b.dtl_Value, rec_URL
						  from Records
					 left join recDetails a on a.dtl_RecID=rec_ID and a.dtl_DetailTypeID=303
					 left join recDetails b on b.dtl_RecID=rec_ID and b.dtl_DetailTypeID=191
						 where rec_ID=$bibID");
	$row = mysql_fetch_row($res);
	$records[$bibID]["description"] = ($row[0] ? $row[0] : $row[1]);
	$records[$bibID]["url"] = ($row[2] ? $row[2] : '');
}


// Find the records that actually have any geographic data to plot
$geoObjects = array();
$geoBibIDs = array();
$res = mysql_query("select dtl_RecID, dtl_Value, astext(dtl_Geo), astext(envelope(dtl_Geo)) from recDetails where dtl_Geo is not null and dtl_RecID in (" . join(",", $bibIDs) . ")");
error_log(mysql_error());
while ($val = mysql_fetch_row($res)) {
	// get the bounding box
	if (preg_match("/POLYGON\\(\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*\\S+\\s+\\S+\\)\\)/i", $val[3], $matches)) {
		$bbox = array("w" => floatval($matches[1]), "s" => floatval($matches[2]), "e" => floatval($matches[5]), "n" => floatval($matches[6]));
	}
	else $bbox = null;

	switch ($val[1]) {
	    case "p":
		if (preg_match("/POINT\\((\\S+)\\s+(\\S+)\\)/i", $val[2], $matches)) {
			array_push($geoObjects, array("bibID" => $val[0], "type" => "point", "geo" => array("x" => floatval($matches[1]), "y" => floatval($matches[2]))));
		} else continue;
		break;

	    case "r":
		if (preg_match("/POLYGON\\(\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*(\\S+)\\s+(\\S+),\\s*\\S+\\s+\\S+\\)\\)/i", $val[2], $matches)) {
			array_push($geoObjects, array("bibID" => $val[0], "type" => "rect",
			           "geo" => array("x0" => floatval($matches[1]), "y0" => floatval($matches[2]), "x1" => floatval($matches[5]), "y1" => floatval($matches[6]), "bounds" => $bbox)));
		} else continue;
		break;

	    case "c":
		if (preg_match("/LINESTRING\\((\\S+)\\s+(\\S+),\\s*(\\S+)\\s+\\S+,\\s*\\S+\\s+\\S+,\\s*\\S+\\s+\\S+\\)/i", $val[2], $matches)) {
			array_push($geoObjects, array("bibID" => $val[0], "type" => "circle",
			           "geo" => array("x" => floatval($matches[1]), "y" => floatval($matches[2]), "radius" => floatval($matches[3] - $matches[1]), "bounds" => $bbox)));
		} else continue;
		break;

	    case "pl":
		if (! preg_match("/POLYGON\\(\\((.+)\\)\\)/i", $val[2], $matches)) continue;
		if (! preg_match_all("/\\S+\\s+\\S+(?:,|$)/", $matches[1], $matches)) continue;
		$matches = $matches[0];

		$points = array();
		for ($j=0; $j < count($matches)-1; ++$j) {
			preg_match("/(\\S+)\\s+(\\S+)(?:,|$)/", $matches[$j], $match_matches);
			array_push($points, array("x" => floatval($match_matches[1]), "y" => floatval($match_matches[2])));
		}
		array_push($geoObjects, array("bibID" => $val[0], "type" => "polygon", "geo" => array("points" => $points, "bounds" => $bbox)));
		break;

	    case "l":
		if (! preg_match("/LINESTRING\\((.+)\\)/i", $val[2], $matches)) continue;
		if (! preg_match_all("/\\S+\\s+\\S+(?:,|$)/", $matches[1], $matches)) continue;
		$matches = $matches[0];

		$points = array();
		for ($j=0; $j < count($matches)-1; ++$j) {
			preg_match("/(\\S+)\\s+(\\S+)(?:,|$)/", $matches[$j], $match_matches);
			array_push($points, array(floatval($match_matches[2]), floatval($match_matches[1])));
		}
		if (count($points) <= 1) continue;
		$encoding = dpEncode($points);

		array_push($geoObjects, array("bibID" => $val[0], "type" => "path", "geo" => array("points" => $encoding[0], "levels" => $encoding[1], "bounds" => $bbox)));
	}
	$geoBibIDs[$val[0]] = $val[0];
}

$res = mysql_query("select LAT.dtl_RecID, LNG.dtl_Value, LAT.dtl_Value from recDetails LAT, recDetails LNG where LAT.dtl_DetailTypeID=211 and LNG.dtl_DetailTypeID=210 and LAT.dtl_RecID=LNG.dtl_RecID and LNG.dtl_RecID in (" . join(",", $bibIDs) . ")");
while ($val = mysql_fetch_row($res)) {
	array_push($geoObjects, array("bibID" => $val[0], "type" => "point", "geo" => array("x" => floatval($val[1]), "y" => floatval($val[2]))));
	$geoBibIDs[$val[0]] = $val[0];
}


// Find time extents -- must have at least a start time (end time is optional)
/*
$timeObjects = array();
$res = mysql_query("select START.dtl_RecID, START.dtl_Value, END.dtl_Value from recDetails START left join recDetails END on START.dtl_RecID=END.dtl_RecID and END.dtl_DetailTypeID=178 where START.dtl_DetailTypeID=177 and START.dtl_Value and START.dtl_RecID in (" . join(",", $bibIDs) . ")");
while ($val = mysql_fetch_row($res)) {
	$timeObjects[$val[0]] = array($val[1], $val[2]);
}
*/

$timeObjects = array();
//"
//select d.dtl_RecID, min(d.dtl_Value), max(d.dtl_Value), min(y.dtl_Value), max(y.dtl_Value)
//from recDetails b, recDetails y
//"
$dates = array();
$years =array();
$res = mysql_query("select rec_ID, min(d.dtl_Value), max(d.dtl_Value)
					  from Records
				cross join defDetailTypes dt
				 left join recDetails d on d.dtl_RecID = rec_ID and d.dtl_DetailTypeID = dt.dty_ID
					 where rec_ID in (" . join(",", $bibIDs) . ")
					   and dt.dty_Type = 'date'
				  group by rec_ID");
while ($val = mysql_fetch_row($res)) {
	if (preg_match("/^\\d+\\s*bc/i", $val[1])) {
		$val[1] = -(preg_replace("/\\s*bc/i","",$val[1])) + 1;
	}
	if (preg_match("/^\\d+\\s*bc/i", $val[2])) {
		$val[2] = -(preg_replace("/\\s*bc/i","",$val[2])) + 1;
	}

	$dates[$val[0]] = array($val[1],$val[2]);
}

$res = mysql_query("select rec_ID, min(d.dtl_Value), max(d.dtl_Value)
					  from Records
				cross join defDetailTypes yt
				 left join recDetails y on y.dtl_RecID = rec_ID and y.dtl_DetailTypeID = yt.dty_ID
					 where rec_ID in (" . join(",", $bibIDs) . ")
					   and yt.dty_Type = 'yesr'
				  group by rec_ID");
while ($val = mysql_fetch_row($res)) {
	if (preg_match("/^\\d+\\s*bc/i", $val[1])) {
		$val[1] = -(preg_replace("/\\s*bc/i","",$val[1])) + 1;
	}
	if (preg_match("/^\\d+\\s*bc/i", $val[2])) {
		$val[2] = -(preg_replace("/\\s*bc/i","",$val[2])) + 1;
	}

	$years[$val[0]] = array($val[1],$val[2]);
}

foreach( $bibIDs as $bibID){
	$sd = (@$dates[$bibID][0] ? $dates[$bibID][0]:null);
	$ed = (@$dates[$bibID][1] ? $dates[$bibID][1]:null);
	$sy = (@$years[$bibID][0] ? $years[$bibID][0]:null);
	$ey = (@$years[$bibID][1] ? $years[$bibID][1]:null);

	if (! $sd  &&  ! $ed  && $sy && $sy == $ey)
		$ey = $sy + 1;

	$s = ($sd ? $sd : $sy);
	$e = ($ed ? $ed : $ey);
	if ($s >= $e) $e = null;

	$timeObjects[$bibID] = array($s, $e);
}


sort($geoBibIDs);

$geoRecords = array();
foreach ($geoBibIDs as $bibID) {
	$geoRecords[$bibID] = $records[$bibID];
	if (@$timeObjects[$bibID]) {
		list($start, $end) = $timeObjects[$bibID];
		$geoRecords[$bibID]["start"] = $start;
		if ($end) $geoRecords[$bibID]["end"] = $end;
	}
}

?>
if (! HEURIST) HEURIST = {};
if (! HEURIST.tmap) HEURIST.tmap = {};
HEURIST.tmap.records = <?= json_format($geoRecords) ?>;
HEURIST.tmap.geoObjects = <?= json_format($geoObjects) ?>;
HEURIST.tmap.totalRecordCount = <?= count($geoRecords) ?>;
