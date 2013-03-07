<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



/**
 * findReplaceRecord.php - where a record has been merged into another, this one finds the record into which it has been merged
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/



require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');


// checks if a records record has been 'replaced' - superceded by another records record.
// there could theoretically be a chain of supercession (is that a word?), so we try to
// follow the chain until we get an authoritative record.
// returns:  - the original rec_ID if the record has not been replaced
//           - rec_ID of the replacement record if the record has been replaced
//           - null if the chain is broken

function get_replacement_bib_id ($rec_id) {
	$res = mysql_query("select rfw_NewRecID from recForwarding where rfw_OldRecID=" . intval($rec_id));
	$recurseLimit = 10;
	while (mysql_num_rows($res) > 0) {
		$row = mysql_fetch_row($res);
		$rec_id = $row[0];
		$replaced = true;
		$res = mysql_query("select rfw_NewRecID from recForwarding where rfw_OldRecID=" . $rec_id);

		if ($recurseLimit-- === 0) { break; }
	}

	return $rec_id;
}

?>
