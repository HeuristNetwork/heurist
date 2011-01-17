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

if (@$_REQUEST['t']) $_REQUEST['bkmrk_bkmk_title'] = $_REQUEST['t'];
if (@$_REQUEST['u']) $_REQUEST['bkmrk_bkmk_url'] = $_REQUEST['u'];
if (@$_REQUEST['d']) $_REQUEST['bkmrk_bkmk_description'] = $_REQUEST['d'];
if (@$_REQUEST['v']) $_REQUEST['version'] = $_REQUEST['v'];
if (@$_REQUEST['k']) $_REQUEST['tag'] = $_REQUEST['k'];
// $_REQUEST['bkmrk_bkmk_description'] = mb_convert_encoding($_REQUEST['bkmrk_bkmk_description'], 'utf-8');

if (! @$_REQUEST['bkmrk_bkmk_title']) $_REQUEST['bkmrk_bkmk_title'] = '';


define('LATEST_BOOKMARKLET_VERSION', '20060713');	//saw FIXME  update this, what is the latest date
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


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__).'/../disambig/testSimilarURLs.php');
require_once(dirname(__FILE__).'/../woot/woot.php');

if (!is_logged_in()) {
	if (! (@$_REQUEST['bkmrk_bkmk_url'] or @$_REQUEST['bkmrk_bkmk_title'] or @$_REQUEST['bkmrk_bkmk_description']))
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?instance='.HEURIST_INSTANCE);
	else
		header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php?instance='.HEURIST_INSTANCE.'&bkmrk_bkmk_title='.urlencode($_REQUEST['bkmrk_bkmk_title']).'&bkmrk_bkmk_url='.urlencode($_REQUEST['bkmrk_bkmk_url']).'&bkmrk_bkmk_description='.urlencode($_REQUEST['bkmrk_bkmk_description']));
	return;
}
$usrID = get_user_id();
mysql_connection_db_overwrite(DATABASE);
mysql_query("set @logged_in_user_id = $usrID");

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
	$res = mysql_query('select bkm_ID from usrBookmarks left join Records on rec_ID=bkm_recID where bkm_UGrpID="'.addslashes($usrID).'"
							and (rec_URL="'.addslashes($burl).'" or rec_URL="'.addslashes($burl).'/")');
	if (mysql_num_rows($res) > 0) {
		$bkmk = mysql_fetch_assoc($res);
		$bkm_ID = $bkmk['bkm_ID'];
		header('Location: ' . HEURIST_URL_BASE . 'records/edit/editRecord.html?instance='.HEURIST_INSTANCE.'&bkmk_id='.$bkm_ID.'&fromadd=exists' . $outdate);
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
	$res = mysql_query("select ugl_GroupID from ".USERS_DATABASE.".sysUsrGrpLinks where ugl_GroupID=".intval($_REQUEST['bib_workgroup'])." and ugl_UserID=$usrID");
	if (mysql_num_rows($res) == 0) {
		$wg = '&wg=' . intval($_REQUEST['bib_workgroup']);
		unset($_REQUEST['bib_workgroup']);
		// print "You are not a member of workgroup ".$_REQUEST['bib_workgroup'].".  You may not add records to that workgroup.";
		// return;
	}
}
//  Process tags for workgroups ensuring that the user is a memeber of the workgroup
if (@$_REQUEST['tag']  &&  strpos($_REQUEST['tag'], "\\")) {
	// workgroup tag
	// workgroup is ...
	$tags = explode(',', $_REQUEST['tag']);
	$outTags = array();
	foreach ($tags as $tag) {
		$pos = strpos($tag, "\\");
		if ($pos !== false) {
			$grpName = substr($tag, 0, $pos);
			$res = mysql_query("select grp.ugr_ID from ".USERS_DATABASE.".sysUGrps grp, ".USERS_DATABASE.".sysUsrGrpLinks where grp.ugr_Name='".addslashes($grpName)."' and ugl_GroupID=grp.ugr_ID and ugl_UserID=$usrID");
			if (mysql_num_rows($res) == 0) {
				$wg .= '&wgkwd=' . urlencode($tag);
				array_push($outTags, str_replace("\\", "", $tag));
				// print "You are not a member of workgroup ".$grpName.".  You may not use tags belonging to that workgroup.";
				// return;
			}
			else {
				array_push($outTags, $tag);
			}
		}
		else {
			array_push($outTags, $tag);
		}
	}
	if (count($outTags)) {
		$_REQUEST['tag'] = join(',', $outTags);
	}
	else {
		unset($_REQUEST['tag']);
	}
}

$new_rec_id = false;

/* arrive with a new (un-bookmarked) URL to process */
if (! @$_REQUEST['_submit']  &&  $_REQUEST['bkmrk_bkmk_url']) {

	if (! $rec_id  &&  ! $force_new) {
		/* look up the records table, see if the requested URL is already in the database; if not, add it */

		$res = mysql_query('select * from Records where rec_URL = "'.addslashes($url).'" and (rec_OwnerUGrpID=0 or rec_NonOwnerVisibility="viewable")');
		if (($row = mysql_fetch_assoc($res))) {
			$rec_id = intval($row['rec_ID']);
			$fav = $_REQUEST["f"];

			$bd = mysql__select_assoc('recDetails', 'concat(dtl_DetailTypeID, ".", dtl_Value)', '1',
				'dtl_RecID='.$rec_id.' and ((dtl_DetailTypeID = 198 and dtl_Value in ("'.join('","', array_map("addslashes", $dois)).'"))
				                        or  (dtl_DetailTypeID = 347 and dtl_Value = "'.addslashes($fav).'"))
				                        or  (dtl_DetailTypeID = 188 and dtl_Value in ("'.join('","', array_map("addslashes", $issns)).'"))
				                        or  (dtl_DetailTypeID = 187 and dtl_Value in ("'.join('","', array_map("addslashes", $isbns)).'")))');

			$inserts = array();
			foreach ($dois as $doi) if (! $bd["198.$doi"]) array_push($inserts, "($rec_id, 198, '" . addslashes($doi) . "')");
			if ($fav  &&  ! $bd["347.$fav"]) array_push($inserts, "($rec_id, 347, '" . addslashes($fav) . "')");
			foreach ($isbns as $isbn) if (! $bd["187.$isbn"]) array_push($inserts, "($rec_id, 187, '" . addslashes($isbn) . "')");
			foreach ($issns as $issn) if (! $bd["188.$issn"]) array_push($inserts, "($rec_id, 188, '" . addslashes($issn) . "')");

			if ($inserts) {
				mysql_query("update Records set rec_Modified = now() where rec_ID = $rec_id");
				mysql_query("insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values " . join(",", $inserts));
			}
		}
	}
//if no record found check for similar url's
	if (! $rec_id  &&  ! $force_new) {
		if (exist_similar($url)) {
			/* there is/are at least one: redirect to a disambiguation page */
			header('Location: ' . HEURIST_URL_BASE . 'records/add/disambiguateRecordURLs.php'
								. '?instance='.HEURIST_INSTANCE
								. '&bkmk_title=' . urlencode($_REQUEST['bkmrk_bkmk_title'])
								. '&f=' . urlencode($_REQUEST["f"])
								. '&bkmk_url=' . urlencode($url)
								. '&bkmk_description=' . urlencode($description)
								. '&tag=' . urlencode($_REQUEST['tag'])
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
			header('Location: ' . BASE_PATH . 'records/add/addRecord.php'
								. '?instance='.HEURIST_INSTANCE
								. '&t=' . urlencode($_REQUEST['t'])
								. '&error_msg=' . urlencode('Record Type #'. $rt . ' does not exist in this Heurist database'
								. ' (it may not have been enabled). Please choose the record type from the pulldown '));
			return;
		}

		mysql__insert('Records', array('rec_URL' => $url,
		                              'rec_Title' => $_REQUEST['bkmrk_bkmk_title'],
	                                      'rec_ScratchPad' => $description,
		                              'rec_Added' => date('Y-m-d H:i:s'),
		                              'rec_Modified' => date('Y-m-d H:i:s'),
		                              'rec_AddedByUGrpID' => intval($usrID),
		                              'rec_RecTypeID' => $_REQUEST['bib_reftype'],
		                              'rec_OwnerUGrpID' => intval($_REQUEST['bib_workgroup']),
		                              'rec_NonOwnerVisibility' => (intval($_REQUEST['bib_workgroup'])? ((strtolower($_REQUEST['bib_visibility']) == 'hidden')? 'Hidden' : 'Viewable') : NULL),
		                              'rec_FlagTemporary' => ! ($url  ||  $_REQUEST['bkmrk_bkmk_title'])));
		$rec_id = mysql_insert_id();

		// there are sometimes cases where there is no title set (e.g. webpage with no TITLE tag)
		if ($_REQUEST['bkmrk_bkmk_title']) {
			mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ('.$rec_id.',160,"'.addslashes($_REQUEST['bkmrk_bkmk_title']).'")');
		}
		$inserts = array();
		foreach ($dois as $doi) array_push($inserts, "($rec_id, 198, '" . addslashes($doi) . "')");
		if (@$_REQUEST["f"]) array_push($inserts, "($rec_id, 347, '" . addslashes($_REQUEST["f"]) . "')");
		foreach ($isbns as $isbn) array_push($inserts, "($rec_id, 187, '" . addslashes($isbn) . "')");
		foreach ($issns as $issn) array_push($inserts, "($rec_id, 188, '" . addslashes($issn) . "')");
		if ($inserts) mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ' . join(",", $inserts));

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
		header('Location: ' . BASE_PATH . 'records/add/addRecord.php'
							. '?instance='.HEURIST_INSTANCE
							. '&t=' . urlencode($_REQUEST['t'])
							. '&error_msg=' . urlencode('Record Type #'. $rt . ' does not exist in this Heurist Database'
							. ' (it may not have been enabled). Please choose the record type from the pulldown '));
		return;
	}
	mysql__insert('Records', array('rec_Title' => $_REQUEST['bkmrk_bkmk_title'],
	                              'rec_ScratchPad' => $description,
	                              'rec_Added' => date('Y-m-d H:i:s'),
	                              'rec_Modified' => date('Y-m-d H:i:s'),
	                              'rec_AddedByUGrpID' => intval($usrID),
		                      'rec_RecTypeID' => ($_REQUEST['bib_reftype']? intval($_REQUEST['bib_reftype']) : NULL),
		                      'rec_OwnerUGrpID' => intval($_REQUEST['bib_workgroup']),
		                      'rec_NonOwnerVisibility' => (intval($_REQUEST['bib_workgroup'])? ((strtolower($_REQUEST['bib_visibility']) == 'hidden')? 'Hidden' : 'Viewable') : NULL),
	                              'rec_FlagTemporary' => ! $_REQUEST['bkmrk_bkmk_title'])); // saw BUG???
	$rec_id = mysql_insert_id();
	if ($_REQUEST['bkmrk_bkmk_title']) mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ('.$rec_id.',160,"'.addslashes($_REQUEST['bkmrk_bkmk_title']).'")');
	$inserts = array();
	foreach ($dois as $doi) array_push($inserts, "($rec_id, 198, '" . addslashes($doi) . "')");
	if (@$_REQUEST["f"]) array_push($inserts, "($rec_id, 347, '" . addslashes($_REQUEST["f"]) . "')");
	foreach ($isbns as $isbn) array_push($inserts, "($rec_id, 187, '" . addslashes($isbn) . "')");
	foreach ($issns as $issn) array_push($inserts, "($rec_id, 188, '" . addslashes($issn) . "')");
	if ($inserts) mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ' . join(",", $inserts));

	if ($description) insert_woot_content($rec_id, $description);
}

// there is a record and it wasn't forced directly   //SAW shouldn't this test rfw_NewRecID
if ($rec_id  &&  ! @$_REQUEST['force_new']) {
	/* user has selected a bookmark that they may or may not have bookmarked already. FFSI!
	 * If they do in fact have it bookmarked, redirect to the edit bookmark page
	 * and add the new notes to the end of their existing notes.  FMS
	 */
	$res = mysql_query("select * from usrBookmarks where bkm_UGrpID=$usrID and bkm_recID = $rec_id");
	$bkmk = mysql_fetch_assoc($res);
	if ($bkmk  &&  $bkmk['bkm_ID']) {
		if ($description) {
			$dres = mysql_query("select rec_ScratchPad from Records where rec_ID = " . $rec_id);
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
			mysql_query("update Records set rec_ScratchPad = '" . addslashes($notesOut) . "' where rec_ID = $rec_id");

			insert_woot_content($rec_id, $description);
		}

		header('Location: ' . HEURIST_URL_BASE . 'records/edit/editRecord.html?instance='.HEURIST_INSTANCE.'&bkmk_id='.$bkmk['bkm_ID'].'&fromadd=exists' . $outdate . "#personal");
		return;
	}
}

// if there is a record now then add any extras that have been passed in - tags or related records
if ($rec_id) {
	if ($rec_id  &&  ! $url) {
		$res = mysql_query('select * from Records where rec_ID = "'.addslashes($rec_id).'" and (rec_OwnerUGrpID=0 or rec_NonOwnerVisibility="viewable")');
		$row = mysql_fetch_assoc($res);
		$url = $row['rec_URL'];
	}

	mysql__insert('usrBookmarks', array(
		'bkm_recID' => $rec_id,
		'bkm_Added' => date('Y-m-d H:i:s'),
		'bkm_Modified' => date('Y-m-d H:i:s'),
		'bkm_UGrpID' => $usrID
	));

	$bkm_ID = mysql_insert_id();

	// add tag
	if (@$_REQUEST['tag']) {
		$tags = explode(',', $_REQUEST['tag']);
		foreach ($tags as $tag) {
			if (strpos($tag, "\\")) {
				// workgroup tag
				// workgroup is ...
				$pos = strpos($tag, "\\");
				$grpName = substr($tag, 0, $pos);
				$kwdName = substr($tag, $pos+1);
				$res = mysql_query("select tag_ID from usrTags, ".USERS_DATABASE.".sysUGrps grp, ".USERS_DATABASE.".sysUsrGrpLinks where tag_Text='".addslashes($kwdName)."' and grp.ugr_Name='".addslashes($grpName)."' and tag_UGrpID=grp.ugr_ID and ugl_GroupID=grp.ugr_ID and ugl_UserID=$usrID");
				$kwd_id = mysql_fetch_row($res);
				$kwd_id = $kwd_id[0];
			}
			else {
				//check for existing usr personal tag
				$res = mysql_query("select tag_ID from usrTags where tag_Text = \"".addslashes($tag)."\" and tag_UGrpID=$usrID");
				if ($row = mysql_fetch_assoc($res)) {
					$kwd_id = $row['tag_ID'];
				} else {// no existing tag so add it
					mysql__insert('usrTags', array(
						'tag_UGrpID' => $usrID,
						'tag_Text' => $tag
					));
					$kwd_id = mysql_insert_id();
				}
			}

			if ($kwd_id) { //tag was found so link it to the record
				mysql__insert('usrRecTagLinks', array(
					'rtl_RecID' => $rec_id,
					'rtl_TagID' => $kwd_id
				));
			}
		}
	}

	if (@$_REQUEST["related"]) {
		$other_bib_id = $_REQUEST["related"];
		$reln_type = "IsRelatedTo";
		if (@$_REQUEST["reltype"]) {
			mysql_query("select trm_Label from defTerms where trm_VocabID = 1 and trm_Label like '".addslashes($_REQUEST["reltype"])."' limit 1;");
			if (mysql_num_rows($res) > 0) {
				$row = mysql_fetch_assoc($res);
				$reln_type = $row["trm_Label"];	// saw TODO: check that this is aligned with the enum value change
			}
		}
		mysql__insert("Records", array(
			"rec_Title" => "Relationship ($rec_id $reln_type $other_bib_id)",	// saw TODO: do we want to create a human readable string here??
					"rec_Added"     => date('Y-m-d H:i:s'),
					"rec_Modified"  => date('Y-m-d H:i:s'),
					"rec_RecTypeID"   => 52,
					"rec_AddedByUGrpID" => $usrID
		));
		$relnBibID = mysql_insert_id();

		if ($relnBibID > 0) {
			$query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ";
			$query .=   "($relnBibID, 160, 'Relationship')";
			$query .= ", ($relnBibID, 202, $rec_id)";
			$query .= ", ($relnBibID, 199, $other_bib_id)";
			$query .= ", ($relnBibID, 200, '" . addslashes($reln_type) . "')";
			mysql_query($query);
		}
	}


	if ($bkm_ID) {
		if ($new_rec_id) {
			header('Location: ' . HEURIST_URL_BASE . 'records/edit/editRecord.html?instance='.HEURIST_INSTANCE.'&bkmk_id=' . $bkm_ID . '&fromadd=new_bib' . $outdate . $wg);
		} else {
			header('Location: ' . HEURIST_URL_BASE . 'records/edit/editRecord.html?instance='.HEURIST_INSTANCE.'&bkmk_id=' . $bkm_ID . '&fromadd=new_bkmk' . $outdate . $wg);
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
global $usrID;	//saw TODO check that this still works in the new structure.
	$res = mysql_query("select distinct rty_ID,rty_Name from defRecTypes
	                 left join ".USERS_DATABASE.".".USER_GROUPS_TABLE." on ".USER_GROUPS_USER_ID_FIELD."=$usrID
	                 left join ".USERS_DATABASE.".".GROUPS_TABLE." on ".GROUPS_ID_FIELD."=".USER_GROUPS_GROUP_ID_FIELD."
	                  where rty_ID
	                  order by ".GROUPS_NAME_FIELD." is null, ".GROUPS_NAME_FIELD.", rty_RecTypeGroupID > 1, rty_Name");
	while ($row = mysql_fetch_assoc($res)) {
		if ($row["rty_ID"] == $rt) {
			return true;
		}
	}
	return false;
}
?>
