<?php

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php

function have_bkmk_permissions($bkm_ID) {

	$bkm_ID = intval($bkm_ID);
	$res = mysql_query('select * from usrBookmarks left join Records on bkm_recID=rec_ID where bkm_ID='.$bkm_ID.' and bkm_UGrpID='.get_user_id());

	// they're not the owner
	if (mysql_num_rows($res) <= 0) return false;

return true;
	$bkmkbib = mysql_fetch_assoc($res);
	if ($bkmkbib['rec_OwnerUGrpID']  &&  $bkmkbib['rec_NonOwnerVisibility'] == 'Hidden') {
		$res = mysql_query('select * from '.USERS_DATABASE.'.sysUsrGrpLinks where ugl_GroupID='.intval($bkmkbib['rec_OwnerUGrpID']).' and ugl_UserID='.get_user_id());
		// they're not in the restricted workgroup
		if (mysql_num_rows($res) <= 0) return false;
	}

	return true;
}


function have_bib_permissions($rec_id) {

	$rec_id = intval($rec_id);
	$res = mysql_query('select * from Records where rec_ID='.$rec_id);
	if (mysql_num_rows($res) < 1) return false;

	$bib = mysql_fetch_assoc($res);
	if ($bib['rec_OwnerUGrpID']  &&  $bib['rec_NonOwnerVisibility'] == 'Hidden') {
		$res = mysql_query('select * from '.USERS_DATABASE.'.sysUsrGrpLinks where ugl_GroupID='.intval($bib['rec_OwnerUGrpID']).' and ugl_UserID='.get_user_id());
		// they're not in the restricted workgroup
		if (mysql_num_rows($res) <= 0) return false;
	}

	return true;
}

?>
