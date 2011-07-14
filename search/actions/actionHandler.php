<?php

/*<!--
 * actionHandler.php  TODO brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/


define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
if (! is_logged_in()) return;

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

session_start();
$script = "";
switch (@$_REQUEST['action']) {
	case 'delete_bookmark':
		$script = delete_bookmarks();
		break;

	case 'add_tags':
		$script = add_tags();
		break;

	case 'add_wgTags_by_id':
		$script = add_wgTags_by_id();
		break;

	case 'remove_tags':
		$script = remove_tags();
		break;

	case 'remove_wgTags_by_id':
		$script = remove_wgTags_by_id();
		break;

	case 'set_ratings':
		$script = set_ratings();
		break;

	case 'bookmark_reference':
		$script = bookmark_references();
		break;

	case 'save_search':
		$script = save_search();
		break;

	case 'bookmark_and_tag':
		$script = bookmark_and_tag_bibids(false);
		break;

	case 'bookmark_tag_and_ssearch':
		$script = bookmark_tag_and_save_seach();
		break;

	case 'set_wg_and_vis':
		$script = set_wg_and_vis();
		break;
}

if (@$script) {
	print "<script>$script</script>\n";
}

if (@$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message']) {
	$msg = $_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message'];
	unset($_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message']);
	session_write_close();
	print "<script>alert('" . $msg . "'); top.location.reload();</script>\n";
} else {
	print_input_form();
}
exit();

/* END OF LOGIC */

function delete_bookmarks() {
	$bkmk_ids = array_map('intval', explode(',', $_REQUEST['bkmk_ids']));

	mysql_connection_db_overwrite(DATABASE);
	mysql_query('delete usrRecTagLinks from usrBookmarks left join usrRecTagLinks on rtl_RecID=bkm_RecID where bkm_ID in ('.join(',', $bkmk_ids).') and bkm_UGrpID=' . get_user_id());
	mysql_query('delete from usrBookmarks where bkm_ID in ('.join(',', $bkmk_ids).') and bkm_UGrpID=' . get_user_id());
	$deleted_count = mysql_affected_rows();

	if (mysql_error()) {
		$onload = 'alert(\'Database problem - no bookmarks deleted\'); location.replace(\'actionHandler.php\');';
	} else if ($deleted_count == 0) {
		$onload = 'location.replace(\'actionHandler.php\');';
	} else if ($deleted_count == 1) {
		$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message'] = 'Deleted one bookmark';
		session_write_close();
		header('Location: actionHandler.php');
		exit();
	} else {
		$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message'] = 'Deleted ' . $deleted_count . ' bookmarks';
		session_write_close();
		header('Location: actionHandler.php');
		exit();
	}
	return $onload;
}

function add_tags() {
	$bkmk_ids = array_map('intval', explode(',', $_REQUEST['bkmk_ids']));

	if (trim($_REQUEST["tagString"])) {
		mysql_connection_db_overwrite(DATABASE);

		$tags = get_ids_for_tags(array_filter(explode(',', $_REQUEST['tagString'])), true);
		mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
				  . 'select bkm_recID, tag_ID from usrBookmarks, usrTags '
				  . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id()
				  . ' and tag_ID in (' . join(',', $tags) . ')');
		$tag_count = mysql_affected_rows();
	}

	if (! trim($_REQUEST['tagString'])) {
		$onload = 'top.HEURIST.search.popupNotice(\'No tags have been added\'); location.replace(\'actionHandler.php\');';
	} else if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - no tags added\'); location.replace(\'actionHandler.php\');';
	} else if ($tag_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No new tags needed to be added\'); location.replace(\'actionHandler.php\');';
	} else if ($tag_count > 0) {
		if ($_REQUEST['reload']) {
			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message'] = 'Tags added';
			session_write_close();
			header('Location: actionHandler.php'); //TODO: add instance code here
			exit();
		} else {
			$onload = 'top.HEURIST.search.popupNotice(\''.$tag_count.' Tags added\'); location.replace(\'actionHandler.php\');'; //TODO: add instance code here
		}
	}
	return $onload;
}

function add_wgTags_by_id() {
	$bib_ids = array_map('intval', explode(',', $_REQUEST['bib_ids']));

	if ($_REQUEST["wgTag_ids"] && count($bib_ids)) {
		mysql_connection_db_overwrite(DATABASE);

		$wgTags = array_map('intval', explode(',', $_REQUEST["wgTag_ids"]));
		mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
				  . 'select rec_ID, tag_ID from usrTags, '.USERS_DATABASE.'.sysUsrGrpLinks, Records '
				  . ' where rec_ID in (' . join(',', $bib_ids) . ') '
				  . ' and ugl_GroupID=tag_UGrpID and ugl_UserID='.get_user_id()	//make sure the user blongs to the workgroup
				  . ' and tag_ID in (' . join(',', $wgTags) . ')');
		$wgTag_count = mysql_affected_rows();
	}

	if (! @$wgTags) {
		$onload = 'top.HEURIST.search.popupNotice(\'No workgroup tags have been added\'); location.replace(\'actionHandler.php\');';
	} else if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - no workgroup tags added\'); location.replace(\'actionHandler.php\');';
	} else if ($wgTag_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No new workgroup tags needed to be added\'); location.replace(\'actionHandler.php\');';
	} else if ($wgTag_count > 0) {
		if ($_REQUEST['reload']) {
			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message'] = 'Workgroup tags added';
			session_write_close();
			header('Location: actionHandler.php');
			exit();
		} else {
			$onload = 'top.HEURIST.search.popupNotice(\''.$wgTag_count.' workgroup tags added\'); location.replace(\'actionHandler.php\');';
		}
	}
	return $onload;
}

function remove_wgTags_by_id() {
	$bib_ids = array_map('intval', explode(',', $_REQUEST['bib_ids']));

	$wgTag_count = 0;

	if ($_REQUEST["wgTag_ids"] && count($bib_ids)) {
		mysql_connection_db_overwrite(DATABASE);

		mysql_query('delete usrRecTagLinks from usrRecTagLinks'
				 . ' left join usrTags on tag_ID = rtl_TagID'
				 . ' left join '.USERS_DATABASE.'.sysUsrGrpLinks on ugl_GroupID = tag_UGrpID'
		         . ' where rtl_RecID in (' . join(',', $bib_ids) . ')'
				 . ' and ugl_UserID = ' . get_user_id()
		         . ' and tag_ID in (' . join(',', $wgTags) . ')');
		$wgTag_count += mysql_affected_rows();
	}

	if (! @$wgTags) {
		$onload = 'top.HEURIST.search.popupNotice(\'No workgroup tags have been removed\'); location.replace(\'actionHandler.php\');';
	} else if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - no workgroup tags removed\'); location.replace(\'actionHandler.php\');';
	} else if ($wgTag_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No workgroup tags matched, none removed\'); location.replace(\'actionHandler.php\');';
	} else if ($wgTag_count > 0) {
		if ($_REQUEST['reload']) {
			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message'] = $wgTag_count.' workgroup tags removed';
			session_write_close();
			header('Location: actionHandler.php');	// saw TODO: make instance aware
			exit();
		} else {
			$onload = 'top.HEURIST.search.popupNotice(\''.$wgTag_count.' workgroup tags removed\'); location.replace(\'actionHandler.php\');';
		}
	}
	return $onload;
}

function remove_tags() {
	$bkmk_ids = array_map('intval', explode(',', $_REQUEST['bkmk_ids']));

	if ($_REQUEST["tagString"]) {
		mysql_connection_db_overwrite(DATABASE);

		$tag_count = 0;
		$tags = get_ids_for_tags(array_filter(explode(',', $_REQUEST['tagString'])), false);
		if (count($bkmk_ids)  &&  $tags  &&  count($tags)) {
			mysql_query('delete usrRecTagLinks from usrBookmarks'
					 . ' left join usrRecTagLinks on rtl_RecID = bkm_RecID'
					 . ' left join usrTags on tag_ID = rtl_TagID'
					 . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id()
					 . ' and tag_ID in (' . join(',', $tags) . ')'
					 . ' and tag_UGrpID = bkm_UGrpID');
			$tag_count = mysql_affected_rows();
		}
	}

	if (! trim($_REQUEST['tagString'])) {
		$onload = 'top.HEURIST.search.popupNotice(\'No tags removed\'); location.replace(\'actionHandler.php\');';	// saw TODO: make instance aware
	} else if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - no tags removed\'); location.replace(\'actionHandler.php\');';	// saw TODO: make instance aware
	} else if ($tag_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No tags matched, none removed\'); location.replace(\'actionHandler.php\');';	// saw TODO: make instance aware
	} else if ($tag_count > 0) {
		if ($_REQUEST['reload']) {
			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message'] = $tag_count.' tags removed';
			session_write_close();
			header('Location: actionHandler.php');	// saw TODO: make instance aware
			exit();
		} else {
			$onload = 'top.HEURIST.search.popupNotice(\''.$tag_count.' tags removed\'); location.replace(\'actionHandler.php\');';	// saw TODO: make instance aware
		}
	}
	return $onload;
}

function set_ratings() {
	$bkmk_ids = array_map('intval', explode(',', $_REQUEST['bkmk_ids']));
	$rating = intval($_REQUEST['rating']);

	mysql_connection_db_overwrite(DATABASE);
	mysql_query('update usrBookmarks set bkm_Rating = ' . $rating
			  . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id());
	$update_count = mysql_affected_rows();
	if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - ratings not set\'); location.replace(\'actionHandler.php\');';
	} else if ($update_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No changes made - ratings are up-to-date\'); location.replace(\'actionHandler.php\');';
	} else {
		$onload = 'top.HEURIST.search.popupNotice(\'Ratings have been set\'); location.replace(\'actionHandler.php\');';
	}
	return $onload;
}

function bookmark_references() {
	mysql_connection_db_overwrite(DATABASE);

	$bib_ids = bib_filter(explode(',', $_REQUEST['bib_ids']));
	$new_bib_ids = mysql__select_array('Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id(),
	                                   'rec_ID', 'bkm_ID is null and rec_ID in (' . join(',', $bib_ids) . ')');
	$existing_bkmk_ids = mysql__select_array('Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id(),
	                                   'concat(bkm_ID,":true")', 'bkm_ID is not null and rec_ID in (' . join(',', $bib_ids) . ')');

	if ($new_bib_ids) {
		mysql_query('insert into usrBookmarks
		                  (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)
		                  select ' . get_user_id() . ', now(), now(), rec_ID
		                    from Records where rec_ID in (' . join(',', $new_bib_ids) . ')');
		$inserted_count = mysql_affected_rows();
		$bkmk_ids = mysql__select_array('usrBookmarks', 'concat(bkm_ID,":true")', 'bkm_recID in ('.join(',',$new_bib_ids).') and bkm_UGrpID = ' . get_user_id());
	} else {
		$inserted_count = -1;
	}

	if (mysql_error()) {
		$onload = 'alert(\'Database problem (' . addslashes(mysql_error()) . ') - no bookmarks added\'); location.replace(\'actionHandler.php\');';
	} else if ($inserted_count < 1  &&  count($existing_bkmk_ids) < 1) {
		$onload = 'location.replace(\'actionHandler.php\');';
	} else {
		$bkmk_ids_string = join(',',$bkmk_ids);
		if (count($existing_bkmk_ids))
			$bkmk_ids_string .= ($bkmk_ids_string?',':'') . join(',',$existing_bkmk_ids);
		$onload = 'location.replace(\'actionHandler.php\'); top.HEURIST.search.bkmk_ids = {'.$bkmk_ids_string.'}; top.HEURIST.search.addTagsPopup(true);';
	}
	return $onload;
}

function bookmark_and_tag_bibids ( $phpReturn ) {
	mysql_connection_db_overwrite(DATABASE);

	$bib_ids = bib_filter(explode(',', $_REQUEST['bib_ids']));
	$new_bib_ids = mysql__select_array('Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id(),
										'rec_ID', 'bkm_ID is null and rec_ID in (' . join(',', $bib_ids) . ')');

	if ($new_bib_ids) {
		mysql_query('insert into usrBookmarks
					  (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)
					  select ' . get_user_id() . ', now(), now(), rec_ID
						from Records where rec_ID in (' . join(',', $new_bib_ids) . ')');
		$inserted_count = mysql_affected_rows();
	}

	$bkmk_ids = mysql__select_array('usrBookmarks', 'bkm_ID', 'bkm_recID in ('.join(',',$bib_ids).') and bkm_UGrpID = ' . get_user_id());

	if (mysql_error()) {
		$message = 'Database problem (' . addslashes(mysql_error()) . ') - no bookmarks added';
	} else if (count($bkmk_ids) < 1) {
		$message = 'No bookmark found or created for selected records.';
	} else if (! trim($_REQUEST['tagString'])) {

	} else {	//we have bookmarks lets add the tags
		$tags = get_ids_for_tags(array_filter(explode(',', $_REQUEST['tagString'])), true);
		mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
				  . 'select bkm_recID, tag_ID from usrBookmarks, usrTags '
				  . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id()
				  . ' and tag_ID in (' . join(',', $tags) . ') and tag_UGrpID = bkm_UGrpID');
		$tag_count = mysql_affected_rows();
		if (mysql_error()) {
			$message = 'Database problem - ' . addslashes(mysql_error()) . ' - no tags added.';
		} else if (! $phpReturn) {
			if ($tag_count == 0) {
				$message = 'Success No new tags needed to be added' ;
			} else {
				$message = 'Tagged '.count($bkmk_ids). ' records';
			}
		} else {
			$message = "Success";
		}
		$message .= ''.($inserted_count ? ' ('. $inserted_count . ' new bookmarks, ' : ' (').
					($tag_count ? $tag_count . ' tags)' : ')');
	}
	if ($phpReturn) {
		return $message;
	} else {
		return  'top.HEURIST.search.popupNotice(\''. $message .'\'); location.replace(\'actionHandler.php\');';
	}
}

function bookmark_tag_and_save_seach() {
	mysql_connection_db_overwrite(DATABASE);
	$message = bookmark_and_tag_bibids(true);
	if ( preg_match('/Success/i',$message) == "0") {
		return 'alert(\' Error while bookmarking and tagging - ' .
				$message . ' - Search not saved.\'); location.replace(\'actionHandler.php\');';
	} else {
		preg_replace('/Success/','',$message);
	}
	$wg = intval(@$_REQUEST['svs_UGrpID']);

	$now = date('Y-m-d');
	$cmb = Array(
		'svs_Name'     => $_REQUEST['svs_Name'],
		'svs_Query'    => urldecode($_REQUEST['svs_Query']),
		'svs_UGrpID'   => get_user_id(),
		'svs_Added'     => $now,
		'svs_Modified'  => $now);

	if ($wg) {	// user / group id was passed in so use it over teh current user id.
		$cmb['svs_UGrpID'] = $wg;
	}

	// overwrites saved search with same name
	$res = mysql_query('select svs_ID, svs_UGrpID from usrSavedSearches where svs_Name="'.slash($_REQUEST['svs_Name']).'" and '.
									($wg ? 'svs_UGrpID='.$wg : 'svs_UGrpID='.get_user_id()));
	$row = mysql_fetch_row($res);

	if ($row) {
		if ($row && $row[1] == $wg) {
			$ss = intval($row[0]);
		}
		mysql__update('usrSavedSearches', 'svs_ID='.$ss, $cmb);
	} else {
		mysql__insert('usrSavedSearches', $cmb);
		$ss = mysql_insert_id();
	}

	if (mysql_error()) {
		$onload = 'alert(\'Database problem (' . addslashes(mysql_error()).') - search not saved\'); location.replace(\'actionHandler.php\');';
	} else {
		$onload = 'location.replace(\'actionHandler.php\');'.
					'top.HEURIST.search.insertSavedSearch(\''.slash($_REQUEST['svs_Name']).'\', \''.slash($_REQUEST['svs_Query']).'\', '.$wg.', '.$ss.');'.
					'top.HEURIST.util.getJsonData("'. BASE_PATH.'search/saved/manageCollection.php?clear", top.HEURIST.search.addRemoveCollectionCB);'.
					'top.HEURIST.search.popupNotice(\' Search \"'.$_REQUEST['svs_Name'].'\" saved. '. $message.'\');' .
					'top.location.href = top.location.href + (top.location.href.match(/\?/) ? \'&\' : \'?\') + \'sid='.$ss.'\';';
	}
	return $onload;
}

function save_search() {
    define('T1000_DEBUG', 1);
	mysql_connection_db_overwrite(DATABASE);
	$wg = intval(@$_REQUEST['svs_UGrpID']);
	$ss = $_REQUEST['svs_ID'];
	$publish = $_REQUEST['publish'];
	$label = @$_REQUEST['svs_Name'];

	$now = date('Y-m-d');
	$cmb = Array(
		'svs_Name'     => $_REQUEST['svs_Name'],
		'svs_Query'    => urldecode($_REQUEST['svs_Query']),
		'svs_UGrpID'   => get_user_id(),
		'svs_Added'     => $now,
		'svs_Modified'  => $now);

	if ($wg) {	// user / group id was passed in so use it over teh current user id.
		$cmb['svs_UGrpID'] = $wg;
	}

	// overwrites saved search with same name
	$res = mysql_query('select svs_ID, svs_UGrpID from usrSavedSearches where svs_Name="'.slash($_REQUEST['svs_Name']).'" and '.
									($wg ? 'svs_UGrpID='.$wg : 'svs_UGrpID='.get_user_id()));
	$row = mysql_fetch_row($res);

	if ($row || $ss) {
		if ($row ) {
		 	$ss = intval($row[0]);
		}
		mysql__update('usrSavedSearches', 'svs_ID='.$ss, $cmb);
	} else {
		mysql__insert('usrSavedSearches', $cmb);
		$ss = mysql_insert_id();
	}

	if (mysql_error()) {

		$onload = 'alert(\'Database problem (' . addslashes(mysql_error()).') - search not saved\'); location.replace(\'actionHandler.php\');';
	} else {
		$onload = 'location.replace(\'actionHandler.php\'); top.HEURIST.search.insertSavedSearch(\''.slash($_REQUEST['svs_Name']).'\', \''.slash($_REQUEST['svs_Query']).'\', '.$wg.', '.$ss.');';
		if ($publish) {
			$onload .= ' top.location.href = top.location.href + (top.location.href.match(/\?/) ? \'&\' : \'?\') + \'pub=1&label='.$label.'&sid='.$ss.'\';';
		}else{
			$onload .= ' top.location.href = top.location.href + (top.location.href.match(/\?/) ? \'&\' : \'?\') + \'label='.$label.'&sid='.$ss.'\';';
		}
	}
	return $onload;
}

function set_wg_and_vis() {
	if (is_admin()) {
		$bib_ids = array_map('intval', explode(',', $_REQUEST['bib_ids']));
		$wg = intval(@$_REQUEST['wg_id']);
		$vis = $_REQUEST['vis'];

		if (($wg == 0  ||  in_array($wg, get_group_ids()))  &&  ($vis == 'viewable'  ||  $vis == 'hidden')) {
			mysql_connection_db_overwrite(DATABASE);

			if ($wg === 0) $vis = 'NULL';
			else $vis = '"' . $vis . '"';

			mysql_query('update Records
			                set rec_OwnerUGrpID = ' . $wg . ', rec_NonOwnerVisibility = ' . $vis . '
			              where rec_ID in (' . join(',', $bib_ids) . ')');

			$_SESSION[HEURIST_SESSION_DB_PREFIX.'heurist']['action-message'] = mysql_affected_rows().' records updated';
			session_write_close();
			header('Location: actionHandler.php');
			exit();
		} else {
			$onload = 'alert(\'Invalid arguments\'); location.replace(\'actionHandler.php\');';
		}
	} else {
		$onload = 'alert(\'Permission denied\'); location.replace(\'actionHandler.php\');';
	}
	return $onload;
}

function print_input_form() {
?>
<form action="actionHandler.php" method="get" id="action_form">
<input type="hidden" name="bkmk_ids" id="bkmk_ids" value="">
<input type="hidden" name="bib_ids" id="bib_ids" value="">
<input type="hidden" name="tagString" id="tagString" value="">
<input type="hidden" name="wgTag_ids" id="wgTag_ids" value="">
<input type="hidden" name="rating" id="rating" value="">
<input type="hidden" name="svs_ID" id="svs_ID" value="">
<input type="hidden" name="svs_Name" id="svs_Name" value="">
<input type="hidden" name="svs_Query" id="svs_Query" value="">
<input type="hidden" name="svs_UGrpID" id="svs_UGrpID" value="">
<input type="hidden" name="publish" id="publish" value="">
<input type="hidden" name="wg_id" id="wg_id" value="">
<input type="hidden" name="vis" id="vis" value="">
<input type="hidden" name="db" id="db" value="">
<input type="hidden" name="action" id="action" value="">
<input type="hidden" name="reload" id="reload" value="">
</form>
<?php
}

function bib_filter($bib_ids) {
	// return an array of only the bib_ids that exist and the user has access to (workgroup filtered)

	$wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks', 'ugl_GroupID', 'ugl_UserID='.get_user_id());
	array_push($wg_ids, 0);
	$f_bib_ids = mysql__select_array('Records', 'rec_ID',
	                                 'rec_ID in ('.join(',', array_map('intval', $bib_ids)).') and (rec_OwnerUGrpID in ('.join(',', $wg_ids).') or rec_NonOwnerVisibility = "viewable")');

	return $f_bib_ids;
}

function get_ids_for_tags($tags, $add) {
	$tag_ids = array();
	foreach ($tags as $tag_name) {
		$tag_name = preg_replace('/\\s+/', ' ', trim($tag_name));
		if ( ($slashpos = strpos($tag_name, '\\')) ) {	// it's a workgroup tag
			$grp_name = substr($tag_name, 0, $slashpos);
			$tag_name = substr($tag_name, $slashpos+1);
			$res = mysql_query('select tag_ID from usrTags, '.USERS_DATABASE.'.sysUsrGrpLinks, '.USERS_DATABASE.'.sysUGrps grp where ugr_Type != "User" and tag_UGrpID=ugl_GroupID and ugl_GroupID=grp.ugr_ID and ugl_UserID='.get_user_id().' and grp.ugr_Name="'.addslashes($grp_name).'" and lower(tag_Text)=lower("'.addslashes($tag_name).'")');
		}
		else {
			$res = mysql_query('select tag_ID from usrTags where lower(tag_Text)=lower("'.addslashes($tag_name).'") and tag_UGrpID='.get_user_id());
		}

		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_row($res);
			array_push($tag_ids, $row[0]);
		}
		else if ($add) {
			// non-existent tag ... add it
			$tag_name = str_replace("\\", "/", $tag_name);	// replace backslashes with forwardslashes
			mysql_query("insert into usrTags (tag_Text, tag_UGrpID) values (\"" . addslashes($tag_name) . "\", " . get_user_id() . ")");
			// saw TODO: add error coding here
			array_push($tag_ids, mysql_insert_id());
		}
	}

	return $tag_ids;
}

?>
