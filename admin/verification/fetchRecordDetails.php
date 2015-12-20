<?php

/*
* Copyright (C) 2005-2015 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

mysql_connection_select(DATABASE);

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

