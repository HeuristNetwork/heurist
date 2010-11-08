<?php

function have_bkmk_permissions($pers_id) {

	$pers_id = intval($pers_id);
	$res = mysql_query('select * from usrBookmarks left join records on pers_rec_id=rec_id where pers_id='.$pers_id.' and pers_usr_id='.get_user_id());

	// they're not the owner
	if (mysql_num_rows($res) <= 0) return false;

return true;
	$bkmkbib = mysql_fetch_assoc($res);
	if ($bkmkbib['rec_wg_id']  &&  $bkmkbib['rec_visibility'] == 'Hidden') {
		$res = mysql_query('select * from '.USERS_DATABASE.'.UserGroups where ug_group_id='.intval($bkmkbib['rec_wg_id']).' and ug_user_id='.get_user_id());
		// they're not in the restricted workgroup
		if (mysql_num_rows($res) <= 0) return false;
	}

	return true;
}


function have_bib_permissions($rec_id) {

	$rec_id = intval($rec_id);
	$res = mysql_query('select * from records where rec_id='.$rec_id);
	if (mysql_num_rows($res) < 1) return false;

	$bib = mysql_fetch_assoc($res);
	if ($bib['rec_wg_id']  &&  $bib['rec_visibility'] == 'Hidden') {
		$res = mysql_query('select * from '.USERS_DATABASE.'.UserGroups where ug_group_id='.intval($bib['rec_wg_id']).' and ug_user_id='.get_user_id());
		// they're not in the restricted workgroup
		if (mysql_num_rows($res) <= 0) return false;
	}

	return true;
}

?>
