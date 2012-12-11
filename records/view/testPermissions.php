<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/


// ARTEM - used in viewRecord.php only

?>

<?php

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
