<?php

if (@$_REQUEST['t']) $_REQUEST['bkmrk_bkmk_title'] = $_REQUEST['t'];
if (@$_REQUEST['u']) $_REQUEST['bkmrk_bkmk_url'] = $_REQUEST['u'];
if (@$_REQUEST['d']) $_REQUEST['bkmrk_bkmk_description'] = $_REQUEST['d'];
if (@$_REQUEST['v']) $_REQUEST['version'] = $_REQUEST['v'];
if (@$_REQUEST['k']) $_REQUEST['keyword'] = $_REQUEST['k'];

// $_REQUEST['bkmrk_bkmk_description'] = mb_convert_encoding($_REQUEST['bkmrk_bkmk_description'], 'utf-8');

if (! @$_REQUEST['bkmrk_bkmk_title']) $_REQUEST['bkmrk_bkmk_title'] = '';


define('LATEST_BOOKMARKLET_VERSION', '20060713');
if (@$_REQUEST['addref']) {
	if (@$_REQUEST['bib_type'])
		$outdate = '&edit_type=records';
	else if (@$_REQUEST['edit_type'])
		$outdate .= '&edit_type=' . @$_REQUEST['edit_type'];
	else
		$outdate = '';
} else {
	if (@$_REQUEST['version'] == LATEST_BOOKMARKLET_VERSION)
		$outdate = '&bookmarklet';
	else if (@$_REQUEST['version'])
		$outdate = '&outdated=1';
	else
		$outdate = '';
}
// url with no type specified gets treated as an internet bookmark
if (@$_REQUEST['bkmrk_bkmk_url']  &&  ! @$_REQUEST['bib_reftype'])
	$_REQUEST['bib_reftype'] = 1;


require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");
require_once(dirname(__FILE__).'/../disambig/similar.php');
require_once(dirname(__FILE__).'/../woot/woot.php');

if (!is_logged_in()) {
	if (! (@$_REQUEST['bkmrk_bkmk_url'] or @$_REQUEST['bkmrk_bkmk_title'] or @$_REQUEST['bkmrk_bkmk_description']))
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?instance='.HEURIST_INSTANCE);
	else
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?instance='.HEURIST_INSTANCE.'&bkmrk_bkmk_title='.urlencode($_REQUEST['bkmrk_bkmk_title']).'&bkmrk_bkmk_url='.urlencode($_REQUEST['bkmrk_bkmk_url']).'&bkmrk_bkmk_description='.urlencode($_REQUEST['bkmrk_bkmk_description']));
	return;
}

mysql_connection_db_overwrite(DATABASE);
mysql_query('set @logged_in_user_id = ' . get_user_id());

/* preprocess any description */
if (@$_REQUEST['bkmrk_bkmk_description']) {
	$description = $_REQUEST['bkmrk_bkmk_description'];

/* use UNIX-style lines */
	$description = str_replace("\r\n", "\n", $description);
	$description = str_replace("\r", "\n", $description);

/* liposuction away those unsightly double, triple and quadruple spaces */
	$description = preg_replace('/ +/', ' ', $description);

/* trim() each line */
	$description = preg_replace('/^[ \t\v\f]+|[ \t\v\f]+$/m', '', $description);
	$description = preg_replace('/^\s+|\s+$/s', '', $description);

/* reduce anything more than two newlines in a row */
	$description = preg_replace("/\n\n\n+/s", "\n\n", $description);

	if (@$_REQUEST['version']) {
		$description .= ' [source: web page ' . date('Y-m-d') . ']';
	}
} else {
	$description = NULL;
}

/*  extract all id from descriptions for bibliographic references */
$dois = array();
if (preg_match_all('!DOI:\s*(10\.[-a-zA-Z.0-9]+/\S+)!i', $description, $matches, PREG_PATTERN_ORDER))
	$dois = array_unique($matches[1]);

$isbns = array();
if (preg_match_all('!ISBN(?:-?1[03])?[^a-z]*?(97[89][-0-9]{9,13}[0-9]|[0-9][-0-9]{7,10}[0-9X])\\b!i', $description, $matches, PREG_PATTERN_ORDER)) {
	$isbns = array_unique($matches[1]);
	if (! @$_REQUEST['bib_reftype']) $_REQUEST['bib_reftype'] = 5;
}

$issns = array();
if (preg_match_all('!ISSN(?:-?1[03])?[^a-z]*?([0-9]{4}-?[0-9]{3}[0-9X])!i', $description, $matches, PREG_PATTERN_ORDER)) {
	$issns = array_unique($matches[1]);
	if (! @$_REQUEST['bib_reftype']) $_REQUEST['bib_reftype'] = 3;
}

/*  fix url to be complete with protocol and remove any trailing slash */
if ($_REQUEST['bkmrk_bkmk_url']  &&  ! preg_match('!^[a-z]+:!i', $_REQUEST['bkmrk_bkmk_url']))
	// prefix http:// if no protocol specified
	$_REQUEST['bkmrk_bkmk_url'] = 'http://' . $_REQUEST['bkmrk_bkmk_url'];

if ($_REQUEST['bkmrk_bkmk_url']) {
	$burl = $_REQUEST['bkmrk_bkmk_url'];
	if (substr($burl, -1) == '/') $burl = substr($burl, 0, strlen($burl)-1);

	/* look up the user's bookmark (usrBookmarks) table, see if they've already got this URL bookmarked -- if so, just edit it */
	$res = mysql_query('select bkm_ID from usrBookmarks left join records on rec_id=pers_rec_id where bkm_UGrpID="'.addslashes(get_user_id()).'"
							and (rec_url="'.addslashes($burl).'" or rec_url="'.addslashes($burl).'/")');
	if (mysql_num_rows($res) > 0) {
		$bkmk = mysql_fetch_assoc($res);
		$bkm_ID = $bkmk['bkm_ID'];
		header('Location: ' . HEURIST_URL_BASE . 'records/editrec/edit.html?instance='.HEURIST_INSTANCE.'&bkmk_id='.$bkm_ID.'&fromadd=exists' . $outdate);
		return;
	}

	$url = $_REQUEST['bkmrk_bkmk_url'];
}

if ($_REQUEST['bib_id'] == -1) {
	$rec_id = NULL;
	$force_new = 1;
} else {
	$rec_id = intval($_REQUEST['bib_id']);
	$force_new = 0;
}

// check workgroup permissions
if (@$_REQUEST['bib_workgroup']) {
	$res = mysql_query("select ug_group_id from ".USERS_DATABASE.".UserGroups where ug_group_id=".intval($_REQUEST['bib_workgroup'])." and ug_user_id=".get_user_id());
	if (mysql_num_rows($res) == 0) {
		$wg = '&wg=' . intval($_REQUEST['bib_workgroup']);
		unset($_REQUEST['bib_workgroup']);
		// print "You are not a member of workgroup ".$_REQUEST['bib_workgroup'].".  You may not add records to that workgroup.";
		// return;
	}
}
//  Process keywords for workgroups ensuring that the user is a memeber of the workgroup
if (@$_REQUEST['keyword']  &&  strpos($_REQUEST['keyword'], "\\")) {
	// workgroup keyword
	// workgroup is ...
	$keywords = explode(',', $_REQUEST['keyword']);
	$outKeywords = array();
	foreach ($keywords as $keyword) {
		$pos = strpos($keyword, "\\");
		if ($pos !== false) {
			$grpName = substr($keyword, 0, $pos);
			$res = mysql_query("select grp_id from ".USERS_DATABASE.".Groups, ".USERS_DATABASE.".UserGroups where grp_name='".addslashes($grpName)."' and ug_group_id=grp_id and ug_user_id=".get_user_id());
			if (mysql_num_rows($res) == 0) {
				$wg .= '&wgkwd=' . urlencode($keyword);
				array_push($outKeywords, str_replace("\\", "", $keyword));
				// print "You are not a member of workgroup ".$grpName.".  You may not use keywords belonging to that workgroup.";
				// return;
			}
			else {
				array_push($outKeywords, $keyword);
			}
		}
		else {
			array_push($outKeywords, $keyword);
		}
	}
	if (count($outKeywords)) {
		$_REQUEST['keyword'] = join(',', $outKeywords);
	}
	else {
		unset($_REQUEST['keyword']);
	}
}

$new_rec_id = false;

/* arrive with a new (un-bookmarked) URL to process */
if (! @$_REQUEST['_submit']  &&  $_REQUEST['bkmrk_bkmk_url']) {

	if (! $rec_id  &&  ! $force_new) {
		/* look up the records table, see if the requested URL is already in the database; if not, add it */

		$res = mysql_query('select * from records where rec_url = "'.addslashes($url).'" and (rec_wg_id=0 or rec_visibility="viewable")');
		if (($row = mysql_fetch_assoc($res))) {
			$rec_id = intval($row['rec_id']);
			$fav = $_REQUEST["f"];

			$bd = mysql__select_assoc('rec_details', 'concat(rd_type, ".", rd_val)', '1',
				'rd_rec_id='.$rec_id.' and ((rd_type = 198 and rd_val in ("'.join('","', array_map("addslashes", $dois)).'"))
				                        or  (rd_type = 347 and rd_val = "'.addslashes($fav).'"))
				                        or  (rd_type = 188 and rd_val in ("'.join('","', array_map("addslashes", $issns)).'"))
				                        or  (rd_type = 187 and rd_val in ("'.join('","', array_map("addslashes", $isbns)).'")))');

			$inserts = array();
			foreach ($dois as $doi) if (! $bd["198.$doi"]) array_push($inserts, "($rec_id, 198, '" . addslashes($doi) . "')");
			if ($fav  &&  ! $bd["347.$fav"]) array_push($inserts, "($rec_id, 347, '" . addslashes($fav) . "')");
			foreach ($isbns as $isbn) if (! $bd["187.$isbn"]) array_push($inserts, "($rec_id, 187, '" . addslashes($isbn) . "')");
			foreach ($issns as $issn) if (! $bd["188.$issn"]) array_push($inserts, "($rec_id, 188, '" . addslashes($issn) . "')");

			if ($inserts) {
				mysql_query("update records set rec_modified = now() where rec_id = $rec_id");
				mysql_query("insert into rec_details (rd_rec_id, rd_type, rd_val) values " . join(",", $inserts));
			}
		}
	}
//if no record found check for similar url's
	if (! $rec_id  &&  ! $force_new) {
		if (exist_similar($url)) {
			/* there is/are at least one: redirect to a disambiguation page */
			header('Location: ' . HEURIST_URL_BASE . 'records/addrec/add_disambiguate.php'
								. '?instance='.HEURIST_INSTANCE
								. '&bkmk_title=' . urlencode($_REQUEST['bkmrk_bkmk_title'])
								. '&f=' . urlencode($_REQUEST["f"])
								. '&bkmk_url=' . urlencode($url)
								. '&bkmk_description=' . urlencode($description)
								. '&keyword=' . urlencode($_REQUEST['keyword'])
								. (@$_REQUEST['bib_reftype'] ? '&bib_reftype=' . urlencode($_REQUEST['bib_reftype']) : ''));
			return;
		}
	}
//if no similar url's and bib_id was -1 then force a new record of bib_reftype supplied
	if (! $rec_id  ||  $force_new) {
		$new_rec_id = true;
		$rt = intval($_REQUEST['bib_reftype']);
		if (! $rt) {
			if ($url) $_REQUEST['bib_reftype'] = 1;	/* Internet bookmark */
			else $_REQUEST['bib_reftype'] = 2;	/* Floating note */
		} else if (!check_reftype_exist($rt)) {
			// the reftype passed in is not available on this instance  send them to the  add resource popup
			header('Location: ' . BASE_PATH . 'records/addrec/popup_add_resource.php'
								. '?instance='.HEURIST_INSTANCE
								. '&t=' . urlencode($_REQUEST['t'])
								. '&error_msg=' . urlencode('Record Type #'. $rt . ' does not exist in this instance of Heurist'
								. ' (it may not have been enabled). Please choose the record type from the pulldown ')
								. '&wg_id=' . urlencode(intval($_REQUEST['bib_workgroup'])));
			return;
		}

		mysql__insert('records', array('rec_url' => $url,
		                              'rec_title' => $_REQUEST['bkmrk_bkmk_title'],
	                                      'rec_scratchpad' => $description,
		                              'rec_added' => date('Y-m-d H:i:s'),
		                              'rec_modified' => date('Y-m-d H:i:s'),
		                              'rec_added_by_usr_id' => intval(get_user_id()),
		                              'rec_type' => $_REQUEST['bib_reftype'],
		                              'rec_wg_id' => intval($_REQUEST['bib_workgroup']),
		                              'rec_visibility' => (intval($_REQUEST['bib_workgroup'])? ((strtolower($_REQUEST['bib_visibility']) == 'hidden')? 'Hidden' : 'Viewable') : NULL),
		                              'rec_temporary' => ! ($url  ||  $_REQUEST['bkmrk_bkmk_title'])));
		$rec_id = mysql_insert_id();

		// there are sometimes cases where there is no title set (e.g. webpage with no TITLE tag)
		if ($_REQUEST['bkmrk_bkmk_title']) {
			mysql_query('insert into rec_details (rd_rec_id, rd_type, rd_val) values ('.$rec_id.',160,"'.addslashes($_REQUEST['bkmrk_bkmk_title']).'")');
		}
		$inserts = array();
		foreach ($dois as $doi) array_push($inserts, "($rec_id, 198, '" . addslashes($doi) . "')");
		if (@$_REQUEST["f"]) array_push($inserts, "($rec_id, 347, '" . addslashes($_REQUEST["f"]) . "')");
		foreach ($isbns as $isbn) array_push($inserts, "($rec_id, 187, '" . addslashes($isbn) . "')");
		foreach ($issns as $issn) array_push($inserts, "($rec_id, 188, '" . addslashes($issn) . "')");
		if ($inserts) mysql_query('insert into rec_details (rd_rec_id, rd_type, rd_val) values ' . join(",", $inserts));

		if ($description) insert_woot_content($rec_id, $description);
	}
} // end pre-processing of url

// no bib_id or url passed in so create a new record
if (! $rec_id  and  ! @$_REQUEST['bkmrk_bkmk_url']) {
	/* create a new public note */

	$new_rec_id = true;
	$rt = intval($_REQUEST['bib_reftype']);
	if (!check_reftype_exist($rt)) {
		// the reftype passed in is not available on this instance  send them to the  add resource popup
		header('Location: ' . BASE_PATH . 'records/addrec/popup_add_resource.php'
							. '?instance='.HEURIST_INSTANCE
							. '&t=' . urlencode($_REQUEST['t'])
							. '&error_msg=' . urlencode('Record Type #'. $rt . ' does not exist in this instance of Heurist'
							. ' (it may not have been enabled). Please choose the record type from the pulldown ')
							. '&wg_id=' . urlencode(intval($_REQUEST['bib_workgroup'])));
		return;
	}
	mysql__insert('records', array('rec_title' => $_REQUEST['bkmrk_bkmk_title'],
	                              'rec_scratchpad' => $description,
	                              'rec_added' => date('Y-m-d H:i:s'),
	                              'rec_modified' => date('Y-m-d H:i:s'),
	                              'rec_added_by_usr_id' => intval(get_user_id()),
		                      'rec_type' => ($_REQUEST['bib_reftype']? intval($_REQUEST['bib_reftype']) : NULL),
		                      'rec_wg_id' => intval($_REQUEST['bib_workgroup']),
		                      'rec_visibility' => (intval($_REQUEST['bib_workgroup'])? ((strtolower($_REQUEST['bib_visibility']) == 'hidden')? 'Hidden' : 'Viewable') : NULL),
	                              'rec_temporary' => ! $_REQUEST['bkmrk_bkmk_title']));
	$rec_id = mysql_insert_id();
	if ($_REQUEST['bkmrk_bkmk_title']) mysql_query('insert into rec_details (rd_rec_id, rd_type, rd_val) values ('.$rec_id.',160,"'.addslashes($_REQUEST['bkmrk_bkmk_title']).'")');
	$inserts = array();
	foreach ($dois as $doi) array_push($inserts, "($rec_id, 198, '" . addslashes($doi) . "')");
	if (@$_REQUEST["f"]) array_push($inserts, "($rec_id, 347, '" . addslashes($_REQUEST["f"]) . "')");
	foreach ($isbns as $isbn) array_push($inserts, "($rec_id, 187, '" . addslashes($isbn) . "')");
	foreach ($issns as $issn) array_push($inserts, "($rec_id, 188, '" . addslashes($issn) . "')");
	if ($inserts) mysql_query('insert into rec_details (rd_rec_id, rd_type, rd_val) values ' . join(",", $inserts));

	if ($description) insert_woot_content($rec_id, $description);
}

// there is a record and it wasn't forced directly   //SAW shouldn't this test new_rec_id
if ($rec_id  &&  ! @$_REQUEST['force_new']) {
	/* user has selected a bookmark that they may or may not have bookmarked already. FFSI!
	 * If they do in fact have it bookmarked, redirect to the edit bookmark page
	 * and add the new notes to the end of their existing notes.  FMS
	 */
	$res = mysql_query('select * from usrBookmarks where bkm_UGrpID='.get_user_id().' and pers_rec_id = '.$rec_id);
	$bkmk = mysql_fetch_assoc($res);
	if ($bkmk  &&  $bkmk['bkm_ID']) {
		if ($description) {
			$dres = mysql_query("select rec_scratchpad from records where rec_id = " . $rec_id);
			$existingDescription = mysql_fetch_row($dres);
			$existingDescription = $existingDescription[0];

			$notesIn = $existingDescription? ($existingDescription . "\n\n" . $description) : $description;
			$notesIn = explode("\n", str_replace("\r", "", $notesIn));
			$notesOut = "";
			$notesMap = array();
			for ($i=0; $i < count($notesIn); ++$i) {
				if (! @$notesMap[$notesIn[$i]]  ||  ! $notesIn[$i]) {	// preserve blank lines
					$notesOut .= $notesIn[$i] . "\n";
					$notesMap[$notesIn[$i]] = true;
				}
			}
			$notesOut = preg_replace("/\n\n+/", "\n", $notesOut);
			mysql_query("update records set rec_scratchpad = '" . addslashes($notesOut) . "' where rec_id = $rec_id");

			insert_woot_content($rec_id, $description);
		}

		header('Location: ' . HEURIST_URL_BASE . 'records/editrec/edit.html?instance='.HEURIST_INSTANCE.'&bkmk_id='.$bkmk['bkm_ID'].'&fromadd=exists' . $outdate . "#personal");
		return;
	}
}

// if there is a record now then add any extras that have been passed in - tags or related records
if ($rec_id) {
	if ($rec_id  &&  ! $url) {
		$res = mysql_query('select * from records where rec_id = "'.addslashes($rec_id).'" and (rec_wg_id=0 or rec_visibility="viewable")');
		$row = mysql_fetch_assoc($res);
		$url = $row['rec_url'];
	}

	mysql__insert('usrBookmarks', array(
		'pers_rec_id' => $rec_id,
		'bkm_Added' => date('Y-m-d H:i:s'),
		'bkm_Modified' => date('Y-m-d H:i:s'),
		'bkm_UGrpID' => get_user_id()
	));

	$bkm_ID = mysql_insert_id();

	// add keyword
	if (@$_REQUEST['keyword']) {
		$keywords = explode(',', $_REQUEST['keyword']);
		foreach ($keywords as $keyword) {
			if (strpos($keyword, "\\")) {
				// workgroup keyword
				// workgroup is ...
				$pos = strpos($keyword, "\\");
				$grpName = substr($keyword, 0, $pos);
				$kwdName = substr($keyword, $pos+1);
				$res = mysql_query("select kwd_id from keywords, ".USERS_DATABASE.".Groups, ".USERS_DATABASE.".UserGroups where kwd_name='".addslashes($kwdName)."' and grp_name='".addslashes($grpName)."' and kwd_wg_id=grp_id and ug_group_id=grp_id and ug_user_id=".get_user_id());
				$kwd_id = mysql_fetch_row($res);
				$kwd_id = $kwd_id[0];
			}
			else {
				$res = mysql_query('select kwd_id from keywords where kwd_name = "'.addslashes($keyword).'" and kwd_usr_id = ' . get_user_id());
				if ($row = mysql_fetch_assoc($res)) {
					$kwd_id = $row['kwd_id'];
				} else {
					mysql__insert('keywords', array(
						'kwd_usr_id' => get_user_id(),
						'kwd_name' => $keyword
					));
					$kwd_id = mysql_insert_id();
				}
			}

			if ($kwd_id) {
				mysql__insert('keyword_links', array(
					'kwl_pers_id' => $bkm_ID,
					'kwl_rec_id' => $rec_id,
					'kwl_kwd_id' => $kwd_id
				));
			}
		}
	}

	if (@$_REQUEST["related"]) {
		$other_bib_id = $_REQUEST["related"];
		$reln_type = "IsRelatedTo";
		if (@$_REQUEST["reltype"]) {
			mysql_query("select rdl_value from rec_detail_lookups where rdl_rdt_id = 200 and rdl_value like '".addslashes($_REQUEST["reltype"])."' limit 1;");
			if (mysql_num_rows($res) > 0) {
				$row = mysql_fetch_assoc($res);
				$reln_type = $row["rdl_value"];
			}
		}
		mysql__insert("records", array(
			"rec_title" => "Relationship ($rec_id $reln_type $other_bib_id)",
					"rec_added"     => date('Y-m-d H:i:s'),
					"rec_modified"  => date('Y-m-d H:i:s'),
					"rec_type"   => 52,
					"rec_added_by_usr_id" => get_user_id()
		));
		$relnBibID = mysql_insert_id();

		if ($relnBibID > 0) {
			$query = "insert into rec_details (rd_rec_id, rd_type, rd_val) values ";
			$query .=   "($relnBibID, 160, 'Relationship')";
			$query .= ", ($relnBibID, 202, $rec_id)";
			$query .= ", ($relnBibID, 199, $other_bib_id)";
			$query .= ", ($relnBibID, 200, '" . addslashes($reln_type) . "')";
			mysql_query($query);
		}
	}


	if ($bkm_ID) {
		if ($new_rec_id) {
			header('Location: ' . HEURIST_URL_BASE . 'records/editrec/edit.html?instance='.HEURIST_INSTANCE.'&bkmk_id=' . $bkm_ID . '&fromadd=new_bib' . $outdate . $wg);
		} else {
			header('Location: ' . HEURIST_URL_BASE . 'records/editrec/edit.html?instance='.HEURIST_INSTANCE.'&bkmk_id=' . $bkm_ID . '&fromadd=new_bkmk' . $outdate . $wg);
		}
		return;
	}
}

function insert_woot_content($rec_id, $content) {
	$result = loadWoot(array("title" => "record:$rec_id"));
	if (! $result["success"]) {
		return;
	}

	$woot = $result["woot"];

	if ($woot["chunks"]) {
		foreach ($woot["chunks"] as $chunk) {
			$chunk["unmodified"] = 1;
		}
	} else {
		$woot["chunks"] = array();
	}

	if (! $woot["permissions"]) {
		$woot["permissions"] = array(array("type" => "RW", "groupId" => -1));
	}

	$new_chunk = array(
		"text" => "<p>$content</p>",
		"permissions" => array(array("type" => "RW", "groupId" => -1))
	);

	array_push($woot["chunks"], $new_chunk);

	$result = saveWoot($woot);
	if (! $result["success"]) error_log($result["errorType"]);
}

function check_reftype_exist($rt) {
	$res = mysql_query("select distinct rt_id,rt_name from active_rec_types
	                 left join rec_types on rt_id=art_id
	                 left join ".USERS_DATABASE.".".USER_GROUPS_TABLE." on ".USER_GROUPS_USER_ID_FIELD."=".get_user_id()."
	                 left join rec_detail_requirements_overrides on rdr_rec_type=rt_id
	                 left join ".USERS_DATABASE.".".GROUPS_TABLE." on ".GROUPS_ID_FIELD."=".USER_GROUPS_GROUP_ID_FIELD." and ".GROUPS_ID_FIELD."=rdr_wg_id
	                  where rt_id
	                  order by ".GROUPS_NAME_FIELD." is null, ".GROUPS_NAME_FIELD.", ! rt_primary, rt_name");
	while ($row = mysql_fetch_assoc($res)) {
		if ($row["rt_id"] == $rt) {
			return true;
		}
	}
	return false;
}
?>
