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
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/


// ARTEM - used in viewRecord.php only



function have_bkmk_permissions($bkm_ID) {

	$bkm_ID = intval($bkm_ID);
	$res = mysql_query('select * from usrBookmarks left join Records on bkm_recID=rec_ID '.
						'where bkm_ID='.$bkm_ID.' and bkm_UGrpID='.get_user_id());

	// they're not the owner
	if (mysql_num_rows($res) <= 0) return false;

/*	$bkmkID = mysql_fetch_assoc($res);
	if ($bkmkID['rec_OwnerUGrpID']  &&  $bkmkID['rec_NonOwnerVisibility'] == 'hidden') {
		$res = mysql_query('select * from '.USERS_DATABASE.'.sysUsrGrpLinks where ugl_GroupID='.intval($bkmkID['rec_OwnerUGrpID']).' and ugl_UserID='.get_user_id());
		// they're not in the restricted workgroup
		if (mysql_num_rows($res) <= 0) return false;
	}
*/
	return true;
}


function canViewRecord($rec_id) {

	$recID = intval($rec_id);
	$res = mysql_query('select * from Records where rec_ID='.$recID);
	if (mysql_num_rows($res) < 1) return false;

	$rec = mysql_fetch_assoc($res);
	if ($rec['rec_NonOwnerVisibility'] == 'public' ||
			(is_logged_in() && $rec['rec_NonOwnerVisibility'] !== 'hidden') ||
			(function_exists("get_user_id") && $rec['rec_OwnerUGrpID'] == get_user_id())){
		return true;
	}

	if ($rec['rec_OwnerUGrpID'] && function_exists("get_user_id") && get_user_id()) {
		$res = mysql_query('select * from '.USERS_DATABASE.'.sysUsrGrpLinks '.
							'where ugl_GroupID='.intval($rec['rec_OwnerUGrpID']).
							' and ugl_UserID='.get_user_id());
		// they're not in the restricted workgroup
		if (mysql_num_rows($res) > 0) return true;
	}

	return false;
}

?>
