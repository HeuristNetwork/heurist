<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

mysql_connection_db_select(DATABASE);

$ref_detail_types = mysql__select_array('defDetailTypes', 'dty_ID', 'dty_Type="resource"');

function ref_detail_types () {
	global $ref_detail_types;
	return $ref_detail_types;
}

function parent_detail_types () {
	return array('217', '225', '226', '227', '228', '229', '236', '237', '238', '241', '242');
}

function fetch_bib_details ($rec_id, $recurse=false, $visited=array()) {

	array_push($visited, $rec_id);

	$details = array();
	$res = mysql_query('select dtl_DetailTypeID, dtl_Value
	                      from recDetails
	                     where dtl_RecID = ' . $rec_id . '
	                  order by dtl_DetailTypeID, dtl_ID');
	while ($row = mysql_fetch_assoc($res)) {

		$type = $row['dtl_DetailTypeID'];
		$val = $row['dtl_Value'];

		if (! $details[$type]) {
			$details[$type] = $val;
		} else if ($details[$type]  &&  ! is_array($details[$type])) {
			$details[$type] = array($details[$type] , $val);
		} else if ($details[$type]  &&  is_array($details[$type])) {
			array_push($details[$type], $val);
		}
	}

	if ($recurse) {
		// fetch parent details
		foreach (ref_detail_types() as $parent_detail_type) {
			if ($details[$parent_detail_type]) {
				$parent_bib_id = $details[$parent_detail_type];
				if (! in_array($parent_detail_type, $visited))	// avoid infinite recursion
					$details[$parent_detail_type] = fetch_bib_details($parent_bib_id, true, $visited);
			}
		}
	}

	return $details;
}

