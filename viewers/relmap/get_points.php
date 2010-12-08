<?php

session_start();

require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");


$bib_id = intval($_REQUEST['bib_id']);

if (!$bib_id)
	$bib_id = $_SESSION['3dvisa_bib_id'];


mysql_connection_db_select(DATABASE);

header('Content-type: text/plain');

// print "id,hlat,hlong\n";

if (!$bib_id) {
	print '{ 1: [ 53, 0 ] }';
	return;
}


$locations = array();
$locations0 = get_locations(array($bib_id));
$relatives0 = get_relatives($bib_id);
if (count($relatives0)) {
	$locations1 = get_locations($relatives0);
} else { $locations1 = array(); }

if ($locations0) {
	// if our bib_id has its own location(s), use those
	$locations[$bib_id] = $locations0[$bib_id];
}
else {
	// otherwise, inherit the locations from its relatives
	$locations[$bib_id] = array();
	foreach ($locations1 as $locationArray) {
		foreach ($locationArray as $location) {
			$locations[$bib_id][$location[0].",".$location[1]] = array($location[0], $location[1], $location[2]+1);
		}
		break;
	}
	$locations[$bib_id] = array_values($locations[$bib_id]);
}

foreach ($relatives0 as $relative) {
	if (@$locations1[$relative]) {
		$locations[$relative] = $locations1[$relative];
	}
	else {
		$relatives1 = get_relatives($relative);
		if (count($relatives1)) {
			$locations2 = get_locations($relatives1);
			foreach ($locations2 as $locationArray) {
				foreach ($locationArray as $location) {
					$locations[$relative][$location[0].",".$location[1]] = array($location[0], $location[1], $location[2]+1);
				}
				break;
			}
		}
		if (@$locations[$relative]) {
			$locations[$relative] = array_values($locations[$relative]);
		}
	}
}


print json_format($locations);



function get_relatives($bibID) {
	$res = mysql_query("select distinct rec_ID from Records, recRelationshipsCache where (rrc_RecID=rec_ID or rrc_TargetRecID=rec_ID or rrc_SourceRecID=rec_ID)
		                                                                    and (rrc_SourceRecID = $bibID or rrc_TargetRecID = $bibID) and rec_ID != $bibID");
	$ids = array();
	while ($row = mysql_fetch_row($res)) { array_push($ids, $row[0]); }
	return $ids;
}

function get_locations($ids) {
	$ids = join(",", $ids);
	$res = mysql_query("select rec_ID, group_concat(astext(envelope(A.dtl_Geo))), A.dtl_Value, B.dtl_Value from Records left join recDetails A on A.dtl_RecID=rec_ID and (A.dtl_Geo is not null or A.dtl_DetailTypeID=210) left join recDetails B on B.dtl_RecID=rec_ID and B.dtl_DetailTypeID=211 where (A.dtl_Geo is not null or B.dtl_Value is not null) and rec_ID in ($ids) group by rec_ID, A.dtl_Geo is null");
	$locs = array();
	while ($row = mysql_fetch_row($res)) {
		$relBibID = $row[0];
		if (! @$locs[$relBibID]) { $locs[$relBibID] = array(); }

		if ($row[1]) {
			$bits = explode("POLYGON((", $row[1]);
			foreach ($bits as $bit) {
				if (preg_match("/[^,]+/", $bit, $matches)) {
					$locs[$relBibID][$bit] = explode(" ", $matches[0]);
				}
			}
		}
		else if ($row[2] && $row[3]) {
			$locs[$relBibID][$row[2]." ".$row[3]] = array($row[2], $row[3], 0);
		}
	}

	$locations = array();
	foreach ($locs as $relBibID => $points) {
		$locations[$relBibID] = array_values($points);
	}

	return $locations;
}

?>
