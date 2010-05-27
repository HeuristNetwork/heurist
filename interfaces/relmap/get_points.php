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
	$res = mysql_query("select distinct rec_id from records, rec_relationships where (rr_rec_id=rec_id or rr_rec_id199=rec_id or rr_rec_id202=rec_id)
		                                                                    and (rr_rec_id202 = $bibID or rr_rec_id199 = $bibID) and rec_id != $bibID");
	$ids = array();
	while ($row = mysql_fetch_row($res)) { array_push($ids, $row[0]); }
	return $ids;
}

function get_locations($ids) {
	$ids = join(",", $ids);
	$res = mysql_query("select rec_id, group_concat(astext(envelope(A.rd_geo))), A.rd_val, B.rd_val from records left join rec_details A on A.rd_rec_id=rec_id and (A.rd_geo is not null or A.rd_type=210) left join rec_details B on B.rd_rec_id=rec_id and B.rd_type=211 where (A.rd_geo is not null or B.rd_val is not null) and rec_id in ($ids) group by rec_id, A.rd_geo is null");
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
