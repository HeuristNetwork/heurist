<?php

define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../common/connect/cred.php');
if (! is_logged_in()) return;

require_once(dirname(__FILE__).'/../common/connect/db.php');

session_start();
$script = "";
switch (@$_REQUEST['action']) {
	case 'delete_bookmark':
		$script = delete_bookmarks();
		break;

	case 'add_keywords':
		$script = add_keywords();
		break;

	case 'add_keywords_by_id':
		$script = add_keywords_by_id();
		break;

	case 'remove_keywords':
		$script = remove_keywords();
		break;

	case 'remove_keywords_by_id':
		$script = remove_keywords_by_id();
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

if (@$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message']) {
	$msg = $_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message'];
	unset($_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message']);
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
	mysql_query('delete keyword_links from usrBookmarks left join keyword_links on kwl_pers_id=bkm_ID where bkm_ID in ('.join(',', $bkmk_ids).') and pers_usr_id=' . get_user_id());
	mysql_query('delete from usrBookmarks where bkm_ID in ('.join(',', $bkmk_ids).') and pers_usr_id=' . get_user_id());
	$deleted_count = mysql_affected_rows();

	if (mysql_error()) {
		$onload = 'alert(\'Database problem - no bookmarks deleted\'); location.replace(\'action.php\');';
	} else if ($deleted_count == 0) {
		$onload = 'location.replace(\'action.php\');';
	} else if ($deleted_count == 1) {
		$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message'] = 'Deleted one bookmark';
		session_write_close();
		header('Location: action.php');
		exit();
	} else {
		$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message'] = 'Deleted ' . $deleted_count . ' bookmarks';
		session_write_close();
		header('Location: action.php');
		exit();
	}
	return $onload;
}

function add_keywords() {
	$bkmk_ids = array_map('intval', explode(',', $_REQUEST['bkmk_ids']));

	if (trim($_REQUEST["keywordstring"])) {
		mysql_connection_db_overwrite(DATABASE);

		$keywords = get_ids_for_keywords(array_filter(explode(',', $_REQUEST['keywordstring'])), true);
		mysql_query('insert ignore into keyword_links (kwl_pers_id, kwl_rec_id, kwl_kwd_id) '
				  . 'select bkm_ID, pers_rec_id, kwd_id from usrBookmarks, keywords '
				  . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and pers_usr_id = ' . get_user_id()
				  . ' and kwd_id in (' . join(',', $keywords) . ')');
		$keyword_count = mysql_affected_rows();
	}

	if (! trim($_REQUEST['keywordstring'])) {
		$onload = 'top.HEURIST.search.popupNotice(\'No tags have been added\'); location.replace(\'action.php\');';
	} else if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - no tags added\'); location.replace(\'action.php\');';
	} else if ($keyword_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No new tags needed to be added\'); location.replace(\'action.php\');';
	} else if ($keyword_count > 0) {
		if ($_REQUEST['reload']) {
			$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message'] = 'Tags added';
			session_write_close();
			header('Location: action.php');
			exit();
		} else {
			$onload = 'top.HEURIST.search.popupNotice(\''.$keyword_count.' Tags added\'); location.replace(\'action.php\');';
		}
	}
	return $onload;
}

function add_keywords_by_id() {
	$bib_ids = array_map('intval', explode(',', $_REQUEST['bib_ids']));

	if ($_REQUEST["kwd_ids"]) {
		mysql_connection_db_overwrite(DATABASE);

		$keywords = array_map('intval', explode(',', $_REQUEST["kwd_ids"]));
		mysql_query('insert ignore into keyword_links (kwl_rec_id, kwl_pers_id, kwl_kwd_id) '
				  . 'select rec_id, bkm_ID, kwd_id from keywords, '.USERS_DATABASE.'.UserGroups, records left join usrBookmarks on rec_id=pers_rec_id and pers_usr_id= '.get_user_id()
				  . ' where rec_id in (' . join(',', $bib_ids) . ') '
				  . ' and ug_group_id=kwd_wg_id and ug_user_id='.get_user_id()
				  . ' and kwd_id in (' . join(',', $keywords) . ')');
		$keyword_count = mysql_affected_rows();
	}

	if (! $keywords) {
		$onload = 'top.HEURIST.search.popupNotice(\'No workgroup tags have been added\'); location.replace(\'action.php\');';
	} else if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - no workgroup tags added\'); location.replace(\'action.php\');';
	} else if ($keyword_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No new workgroup tags needed to be added\'); location.replace(\'action.php\');';
	} else if ($keyword_count > 0) {
		if ($_REQUEST['reload']) {
			$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message'] = 'Workgroup tags added';
			session_write_close();
			header('Location: action.php');
			exit();
		} else {
			$onload = 'top.HEURIST.search.popupNotice(\''.$keyword_count.' workgroup tags added\'); location.replace(\'action.php\');';
		}
	}
	return $onload;
}

function remove_keywords_by_id() {
	$bib_ids = array_map('intval', explode(',', $_REQUEST['bib_ids']));

	$keyword_count = 0;

	if ($_REQUEST["kwd_ids"]) {
		mysql_connection_db_overwrite(DATABASE);

		$keywords = array_map('intval', explode(',', $_REQUEST["kwd_ids"]));
		mysql_query('delete keyword_links from usrBookmarks'
				 . ' left join keyword_links on kwl_pers_id = bkm_ID'
				 . ' left join keywords on kwd_id = kwl_kwd_id'
		         . ' where pers_rec_id in (' . join(',', $bib_ids) . ') and pers_usr_id = ' . get_user_id()
		         . ' and kwd_id in (' . join(',', $keywords) . ')');
		$keyword_count += mysql_affected_rows();
		mysql_query('delete keyword_links from keyword_links'
				 . ' left join keywords on kwd_id = kwl_kwd_id'
				 . ' left join '.USERS_DATABASE.'.UserGroups on ug_group_id = kwd_wg_id'
		         . ' where kwl_pers_id = 0 and kwl_rec_id in (' . join(',', $bib_ids) . ')'
				 . ' and ug_user_id = ' . get_user_id()
		         . ' and kwd_id in (' . join(',', $keywords) . ')');
		$keyword_count += mysql_affected_rows();
	}

	if (! $keywords) {
		$onload = 'top.HEURIST.search.popupNotice(\'No workgroup tags have been removed\'); location.replace(\'action.php\');';
	} else if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - no workgroup tags removed\'); location.replace(\'action.php\');';
	} else if ($keyword_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No workgroup tags matched, none removed\'); location.replace(\'action.php\');';
	} else if ($keyword_count > 0) {
		if ($_REQUEST['reload']) {
			$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message'] = $keyword_count.' workgroup tags removed';
			session_write_close();
			header('Location: action.php');
			exit();
		} else {
			$onload = 'top.HEURIST.search.popupNotice(\''.$keyword_count.' workgroup tags removed\'); location.replace(\'action.php\');';
		}
	}
	return $onload;
}

function remove_keywords() {
	$bkmk_ids = array_map('intval', explode(',', $_REQUEST['bkmk_ids']));

	if ($_REQUEST["keywordstring"]) {
		mysql_connection_db_overwrite(DATABASE);

		$keyword_count = 0;
		$keywords = get_ids_for_keywords(array_filter(explode(',', $_REQUEST['keywordstring'])), false);
		if (count($bkmk_ids)  &&  $keywords  &&  count($keywords)) {
			mysql_query('delete keyword_links from usrBookmarks'
					 . ' left join keyword_links on kwl_pers_id = bkm_ID'
					 . ' left join keywords on kwd_id = kwl_kwd_id'
					 . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and pers_usr_id = ' . get_user_id()
					 . ' and kwd_id in (' . join(',', $keywords) . ')');
			$keyword_count = mysql_affected_rows();
		}
	}

	if (! trim($_REQUEST['keywordstring'])) {
		$onload = 'top.HEURIST.search.popupNotice(\'No tags removed\'); location.replace(\'action.php\');';
	} else if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - no tags removed\'); location.replace(\'action.php\');';
	} else if ($keyword_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No tags matched, none removed\'); location.replace(\'action.php\');';
	} else if ($keyword_count > 0) {
		if ($_REQUEST['reload']) {
			$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message'] = $keyword_count.' tags removed';
			session_write_close();
			header('Location: action.php');
			exit();
		} else {
			$onload = 'top.HEURIST.search.popupNotice(\''.$keyword_count.' tags removed\'); location.replace(\'action.php\');';
		}
	}
	return $onload;
}

function set_ratings() {
	$bkmk_ids = array_map('intval', explode(',', $_REQUEST['bkmk_ids']));
	$content_rating = intval($_REQUEST['c_rating']);
	$quality_rating = intval($_REQUEST['q_rating']);
	$interest_rating = intval($_REQUEST['i_rating']);

	mysql_connection_db_overwrite(DATABASE);
	mysql_query('update usrBookmarks set pers_content_rating = ' . $content_rating
								 . ', pers_quality_rating = ' . $quality_rating
								 . ', pers_interest_rating = ' . $interest_rating
			  . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and pers_usr_id = ' . get_user_id());
	$update_count = mysql_affected_rows();
	if (mysql_error()) {
		$onload = 'alert(\'Database problem - ' . addslashes(mysql_error()) . ' - no ratings set\'); location.replace(\'action.php\');';
	} else if ($update_count == 0) {
		$onload = 'top.HEURIST.search.popupNotice(\'No changes made - all ratings are up-to-date\'); location.replace(\'action.php\');';
	} else {
		$onload = 'top.HEURIST.search.popupNotice(\'Ratings have been set\'); location.replace(\'action.php\');';
	}
	return $onload;
}

function bookmark_references() {
	mysql_connection_db_overwrite(DATABASE);

	$bib_ids = bib_filter(explode(',', $_REQUEST['bib_ids']));
	$new_bib_ids = mysql__select_array('records left join usrBookmarks on pers_rec_id=rec_id and pers_usr_id='.get_user_id(),
	                                   'rec_id', 'bkm_ID is null and rec_id in (' . join(',', $bib_ids) . ')');
	$existing_bkmk_ids = mysql__select_array('records left join usrBookmarks on pers_rec_id=rec_id and pers_usr_id='.get_user_id(),
	                                   'concat(bkm_ID,":true")', 'bkm_ID is not null and rec_id in (' . join(',', $bib_ids) . ')');

	if ($new_bib_ids) {
		mysql_query('insert into usrBookmarks
		                  (pers_usr_id, bkm_Added, bkm_Modified, pers_rec_id)
		                  select ' . get_user_id() . ', now(), now(), rec_id
		                    from records where rec_id in (' . join(',', $new_bib_ids) . ')');
		$inserted_count = mysql_affected_rows();
		$bkmk_ids = mysql__select_array('usrBookmarks', 'concat(bkm_ID,":true")', 'pers_rec_id in ('.join(',',$new_bib_ids).') and pers_usr_id = ' . get_user_id());
	} else {
		$inserted_count = -1;
	}

	if (mysql_error()) {
		$onload = 'alert(\'Database problem (' . addslashes(mysql_error()) . ') - no bookmarks added\'); location.replace(\'action.php\');';
	} else if ($inserted_count < 1  &&  count($existing_bkmk_ids) < 1) {
		$onload = 'location.replace(\'action.php\');';
	} else {
		$bkmk_ids_string = join(',',$bkmk_ids);
		if (count($existing_bkmk_ids))
			$bkmk_ids_string .= ($bkmk_ids_string?',':'') . join(',',$existing_bkmk_ids);
		$onload = 'location.replace(\'action.php\'); top.HEURIST.search.bkmk_ids = {'.$bkmk_ids_string.'}; top.HEURIST.search.addTagsPopup(true);';
	}
	return $onload;
}

function bookmark_and_tag_bibids ( $phpReturn ) {
	mysql_connection_db_overwrite(DATABASE);

	$bib_ids = bib_filter(explode(',', $_REQUEST['bib_ids']));
	$new_bib_ids = mysql__select_array('records left join usrBookmarks on pers_rec_id=rec_id and pers_usr_id='.get_user_id(),
										'rec_id', 'bkm_ID is null and rec_id in (' . join(',', $bib_ids) . ')');

	if ($new_bib_ids) {
		mysql_query('insert into usrBookmarks
					  (pers_usr_id, bkm_Added, bkm_Modified, pers_rec_id)
					  select ' . get_user_id() . ', now(), now(), rec_id
						from records where rec_id in (' . join(',', $new_bib_ids) . ')');
		$inserted_count = mysql_affected_rows();
	}

	$bkmk_ids = mysql__select_array('usrBookmarks', 'bkm_ID', 'pers_rec_id in ('.join(',',$bib_ids).') and pers_usr_id = ' . get_user_id());

	if (mysql_error()) {
		$message = 'Database problem (' . addslashes(mysql_error()) . ') - no bookmarks added';
	} else if (count($bkmk_ids) < 1) {
		$message = 'No bookmark found or created for selected records.';
	} else if (! trim($_REQUEST['keywordstring'])) {

	} else {	//we have bookmarks lets add teh tags
		$keywords = get_ids_for_keywords(array_filter(explode(',', $_REQUEST['keywordstring'])), true);
		mysql_query('insert ignore into keyword_links (kwl_pers_id, kwl_rec_id, kwl_kwd_id) '
				  . 'select bkm_ID, pers_rec_id, kwd_id from usrBookmarks, keywords '
				  . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and pers_usr_id = ' . get_user_id()
				  . ' and kwd_id in (' . join(',', $keywords) . ')');
		$keyword_count = mysql_affected_rows();
		if (mysql_error()) {
			$message = 'Database problem - ' . addslashes(mysql_error()) . ' - no tags added.';
		} else if (! $phpReturn) {
			if ($keyword_count == 0) {
				$message = 'Success No new tags needed to be added' ;
			} else {
				$message = 'Tagged '.count($bkmk_ids). ' records';
			}
		} else {
			$message = "Success";
		}
		$message .= ''.($inserted_count ? ' ('. $inserted_count . ' new bookmarks, ' : ' (').
					($keyword_count ? $keyword_count . ' tags)' : ')');
	}
	if ($phpReturn) {
		return $message;
	} else {
		return  'top.HEURIST.search.popupNotice(\''. $message .'\'); location.replace(\'action.php\');';
	}
}

function bookmark_tag_and_save_seach() {
	mysql_connection_db_overwrite(DATABASE);
	$message = bookmark_and_tag_bibids(true);
	if ( preg_match('/Success/i',$message) == "0") {
		return 'alert(\' Error while bookmarking and tagging - ' .
				$message . ' - Search not saved.\'); location.replace(\'action.php\');';
	} else {
		preg_replace('/Success/','',$message);
	}
	$wg = intval(@$_REQUEST['ss_wg_id']);

	$now = date('Y-m-d');
	$cmb = Array(
		'ss_name'     => $_REQUEST['ss_name'],
		'ss_query'    => urldecode($_REQUEST['ss_query']),
		'ss_usr_id'   => get_user_id(),
		'ss_added'     => $now,
		'ss_modified'  => $now);

	if ($wg) {
		$cmb['ss_wg_id'] = $wg;
	}

	// overwrites saved search with same name
	$res = mysql_query('select ss_id, ss_wg_id from saved_searches where ss_name="'.slash($_REQUEST['ss_name']).'" and '.
									($wg ? 'ss_wg_id='.$wg : 'ss_wg_id=0 and ss_usr_id='.get_user_id()));
	$row = mysql_fetch_row($res);

	if ($row) {
		if ($row && $row[1] == $wg) {
			$ss = intval($row[0]);
		}
		mysql__update('saved_searches', 'ss_id='.$ss, $cmb);
	} else {
		mysql__insert('saved_searches', $cmb);
		$ss = mysql_insert_id();
	}

	if (mysql_error()) {
		$onload = 'alert(\'Database problem (' . addslashes(mysql_error()).') - search not saved\'); location.replace(\'action.php\');';
	} else {
		$onload = 'location.replace(\'action.php\');'.
					'top.HEURIST.search.insertSavedSearch(\''.slash($_REQUEST['ss_name']).'\', \''.slash($_REQUEST['ss_query']).'\', '.$wg.', '.$ss.');'.
					'top.HEURIST.util.getJsonData("'. BASE_PATH.'search/collection.php?clear", top.HEURIST.search.addRemoveCollectionCB);'.
					'top.HEURIST.search.popupNotice(\' Search \"'.$_REQUEST['ss_name'].'\" saved. '. $message.'\');' .
					'top.location.href = top.location.href + (top.location.href.match(/\?/) ? \'&\' : \'?\') + \'sid='.$ss.'\';';
	}
	return $onload;
}

function save_search() {
    define('T1000_DEBUG', 1);
	mysql_connection_db_overwrite(DATABASE);
	$wg = intval(@$_REQUEST['ss_wg_id']);
	$ss = $_REQUEST['ss_id'];
	$publish = $_REQUEST['publish'];
	$label = @$_REQUEST['ss_name'];

	$now = date('Y-m-d');
	$cmb = Array(
		'ss_name'     => $_REQUEST['ss_name'],
		'ss_query'    => urldecode($_REQUEST['ss_query']),
		'ss_usr_id'   => get_user_id(),
		'ss_added'     => $now,
		'ss_modified'  => $now);

	if ($wg) {
		$cmb['ss_wg_id'] = $wg;
	}

	// overwrites saved search with same name
	$res = mysql_query('select ss_id, ss_wg_id from saved_searches where ss_name="'.slash($_REQUEST['ss_name']).'" and '.
									($wg ? 'ss_wg_id='.$wg : 'ss_wg_id=0 and ss_usr_id='.get_user_id()));
	$row = mysql_fetch_row($res);

	if ($row || $ss) {
		if ($row && $row[1] == $wg) {
		 	$ss = intval($row[0]);
		}
		mysql__update('saved_searches', 'ss_id='.$ss, $cmb);
	} else {
		mysql__insert('saved_searches', $cmb);
		$ss = mysql_insert_id();
	}

	if (mysql_error()) {

		$onload = 'alert(\'Database problem (' . addslashes(mysql_error()).') - search not saved\'); location.replace(\'action.php\');';
	} else {
		$onload = 'location.replace(\'action.php\'); top.HEURIST.search.insertSavedSearch(\''.slash($_REQUEST['ss_name']).'\', \''.slash($_REQUEST['ss_query']).'\', '.$wg.', '.$ss.');';
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

		if (($wg == 0  ||  in_array($wg, get_group_ids()))  &&  ($vis == 'Viewable'  ||  $vis == 'Hidden')) {
			mysql_connection_db_overwrite(DATABASE);

			if ($wg === 0) $vis = 'NULL';
			else $vis = '"' . $vis . '"';

			mysql_query('update records
			                set rec_wg_id = ' . $wg . ', rec_visibility = ' . $vis . '
			              where rec_id in (' . join(',', $bib_ids) . ')');

			$_SESSION[HEURIST_INSTANCE_PREFIX.'heurist']['action-message'] = mysql_affected_rows().' records updated';
			session_write_close();
			header('Location: action.php');
			exit();
		} else {
			$onload = 'alert(\'Invalid arguments\'); location.replace(\'action.php\');';
		}
	} else {
		$onload = 'alert(\'Permission denied\'); location.replace(\'action.php\');';
	}
	return $onload;
}

function print_input_form() {
?>
<form action="action.php" method="get" id="action_form">
<input type="hidden" name="bkmk_ids" id="bkmk_ids" value="">
<input type="hidden" name="bib_ids" id="bib_ids" value="">
<input type="hidden" name="keywordstring" id="keywordstring" value="">
<input type="hidden" name="kwd_ids" id="kwd_ids" value="">
<input type="hidden" name="c_rating" id="c_rating" value="">
<input type="hidden" name="q_rating" id="q_rating" value="">
<input type="hidden" name="i_rating" id="i_rating" value="">
<input type="hidden" name="ss_id" id="ss_id" value="">
<input type="hidden" name="ss_name" id="ss_name" value="">
<input type="hidden" name="ss_query" id="ss_query" value="">
<input type="hidden" name="ss_wg_id" id="ss_wg_id" value="">
<input type="hidden" name="publish" id="publish" value="">
<input type="hidden" name="wg_id" id="wg_id" value="">
<input type="hidden" name="vis" id="vis" value="">
<input type="hidden" name="instance" id="instance" value="">
<input type="hidden" name="action" id="action" value="">
<input type="hidden" name="reload" id="reload" value="">
</form>
<?php
}

function bib_filter($bib_ids) {
	// return an array of only the bib_ids that exist and the user has access to (workgroup filtered)

	$wg_ids = mysql__select_array(USERS_DATABASE.'.UserGroups', 'ug_group_id', 'ug_user_id='.get_user_id());
	array_push($wg_ids, 0);
	$f_bib_ids = mysql__select_array('records', 'rec_id',
	                                 'rec_id in ('.join(',', array_map('intval', $bib_ids)).') and (rec_wg_id in ('.join(',', $wg_ids).') or rec_visibility = "viewable")');

	return $f_bib_ids;
}

function get_ids_for_keywords($kwds, $add) {
	$kwd_ids = array();
	foreach ($kwds as $kwd_name) {
		$kwd_name = preg_replace('/\\s+/', ' ', trim($kwd_name));
		if ( ($slashpos = strpos($kwd_name, '\\')) ) {	// it's a workgroup keyword
			$grp_name = substr($kwd_name, 0, $slashpos);
			$kwd_name = substr($kwd_name, $slashpos+1);
			$res = mysql_query('select kwd_id from keywords, '.USERS_DATABASE.'.UserGroups, '.USERS_DATABASE.'.Groups where kwd_wg_id=ug_group_id and ug_group_id=grp_id and ug_user_id='.get_user_id().' and grp_name="'.addslashes($grp_name).'" and lower(kwd_name)=lower("'.addslashes($kwd_name).'")');
		}
		else {
			$res = mysql_query('select kwd_id from keywords where lower(kwd_name)=lower("'.addslashes($kwd_name).'") and kwd_usr_id='.get_user_id());
		}

		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_row($res);
			array_push($kwd_ids, $row[0]);
		}
		else if ($add) {
			// non-existent keyword ... add it
			$kwd_name = str_replace("\\", "/", $kwd_name);	// replace backslashes with forwardslashes
			mysql_query("insert into keywords (kwd_name, kwd_usr_id) values (\"" . addslashes($kwd_name) . "\", " . get_user_id() . ")");
			array_push($kwd_ids, mysql_insert_id());
		}
	}

	return $kwd_ids;
}

?>
