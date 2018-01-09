<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* service to add a record, will load editor for temporary record
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Add
*/

if (@$_REQUEST['h4']==2){
    header( 'Location: ../../hclient/framecontent/recordEdit.php?'.$_SERVER['QUERY_STRING'] );
    return;
}

// translate variable names
if (@$_REQUEST['t']) $_REQUEST['bkmrk_bkmk_title'] = $_REQUEST['t'];
if (@$_REQUEST['u']) $_REQUEST['bkmrk_bkmk_url'] = $_REQUEST['u'];
if (@$_REQUEST['d']) $_REQUEST['bkmrk_bkmk_description'] = $_REQUEST['d'];
if (@$_REQUEST['v']) $_REQUEST['version'] = $_REQUEST['v'];
if (@$_REQUEST['k']) $_REQUEST['tag'] = $_REQUEST['k'];
// $_REQUEST['bkmrk_bkmk_description'] = mb_convert_encoding($_REQUEST['bkmrk_bkmk_description'], 'utf-8');

if (! @$_REQUEST['bkmrk_bkmk_title']) $_REQUEST['bkmrk_bkmk_title'] = '';
if (! @$_REQUEST['bkmrk_bkmk_description']) $_REQUEST['bkmrk_bkmk_description'] = '';


define('LATEST_BOOKMARKLET_VERSION', '20060713');	//note! this must be in synch with import/bookmarklet/bookmarkletPopup.php
// setup parameters for call to editRecord
if (@$_REQUEST['addref']) {	// add a record		//saw TODO: change this to addrec
	if (@$_REQUEST['rec_rectype'])
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


require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../files/saveURLasFile.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");
require_once(dirname(__FILE__).'/../disambig/testSimilarURLs.php');
require_once(dirname(__FILE__).'/../woot/woot.php');


// url with no rectype specified gets treated as an internet bookmark
if (@$_REQUEST['bkmrk_bkmk_url']  &&  ! @$_REQUEST['rec_rectype']){
    $_REQUEST['rec_rectype'] = (defined('RT_INTERNET_BOOKMARK')?RT_INTERNET_BOOKMARK:0);
}


if (!is_logged_in()) {
	if (! (@$_REQUEST['bkmrk_bkmk_url'] || @$_REQUEST['bkmrk_bkmk_title'] || @$_REQUEST['bkmrk_bkmk_description']))
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
	else
		header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME.'&bkmrk_bkmk_title='.urlencode(@$_REQUEST['bkmrk_bkmk_title']).'&bkmrk_bkmk_url='.urlencode(@$_REQUEST['bkmrk_bkmk_url']).'&bkmrk_bkmk_description='.urlencode(@$_REQUEST['bkmrk_bkmk_description']));
	return;
}
$usrID = get_user_id();
mysql_connection_overwrite(DATABASE);
mysql_query("set @logged_in_user_id = $usrID");	//saw TODO: check where else this needs to be used

list($userDefaultRectype, $userDefaultOwnerGroupID, $userDefaultVisibility) = getDefaultOwnerAndibility($_REQUEST);

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
	if (! @$_REQUEST['rec_rectype']) $_REQUEST['rec_rectype'] = (defined('RT_BOOK')?RT_BOOK:0);
}

$issns = array();
if (preg_match_all('!ISSN(?:-?1[03])?[^a-z]*?([0-9]{4}-?[0-9]{3}[0-9X])!i', $description, $matches, PREG_PATTERN_ORDER)) {
	$issns = array_unique($matches[1]);
	if (! @$_REQUEST['rec_rectype']) $_REQUEST['rec_rectype'] = (defined('RT_JOURNAL_ARTICLE')?RT_JOURNAL_ARTICLE:0);
}

/*  fix url to be complete with protocol and remove any trailing slash */
if (@$_REQUEST['bkmrk_bkmk_url']  &&  ! preg_match('!^[a-z]+:!i', $_REQUEST['bkmrk_bkmk_url']))
	// prefix http:// if no protocol specified
	$_REQUEST['bkmrk_bkmk_url'] = 'http://' . $_REQUEST['bkmrk_bkmk_url'];

if (@$_REQUEST['bkmrk_bkmk_url']) {
	$burl = $_REQUEST['bkmrk_bkmk_url'];
	if (substr($burl, -1) == '/') $burl = substr($burl, 0, strlen($burl)-1);

	/* look up the user's bookmark (usrBookmarks) table, see if they've already got this URL bookmarked -- if so, just edit it */
	$res = mysql_query('select bkm_ID, rec_ID from usrBookmarks left join Records on rec_ID=bkm_recID where bkm_UGrpID="'.mysql_real_escape_string($usrID).'"
							and (rec_URL="'.mysql_real_escape_string($burl).'" or rec_URL="'.mysql_real_escape_string($burl).'/")');
	if (mysql_num_rows($res) > 0) {
		$bkmk = mysql_fetch_assoc($res);
		$bkm_ID = $bkmk['bkm_ID'];
        $rec_ID = $bkmk['rec_ID'];
        
        $url = HEURIST_BASE_URL . '?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$rec_ID;
        header('Location: ' . $url);    
		
		return;
	}

	$url = $_REQUEST['bkmrk_bkmk_url'];
}

if (@$_REQUEST['recID'] == -1) { // signalled to create a new record
	$rec_id = NULL;
	$force_new = 1;
} else if (@$_REQUEST['recID'] > 0){
	$rec_id = intval($_REQUEST['recID']);
	$force_new = 0;
}

$wg = "";

if(is_numeric(@$_REQUEST['rec_owner'])){
      $ownership = intval($_REQUEST['rec_owner']);
}else{
      $ownership = (@$userDefaultOwnerGroupID ? $userDefaultOwnerGroupID :
                                                        (defined('HEURIST_NEWREC_OWNER_ID') ? HEURIST_NEWREC_OWNER_ID: intval($usrID)));
}

// check workgroup permissions
if ($ownership>0 && $ownership != $usrID) {
	$res = mysql_query("select ugl_GroupID from ".USERS_DATABASE.".sysUsrGrpLinks where ugl_GroupID=".$ownership." and ugl_UserID=$usrID");
	if (mysql_num_rows($res) == 0) { // user not a member so add wg to parameters for editRecord
		$wg = '&wg=' . $ownership;
//		unset($_REQUEST['rec_owner']); //remove wg request
	}
}


//  Preprocess tags for workgroups ensuring that the user is a member of the workgroup
if (@$_REQUEST['tag']  &&  strpos($_REQUEST['tag'], "\\")) {
	// workgroup tag
	// workgroup is ...
	$tags = explode(',', $_REQUEST['tag']);
	$outTags = array();
	foreach ($tags as $tag) {
		$pos = strpos($tag, "\\");
		if ($pos !== false) {
			$grpName = substr($tag, 0, $pos);	//extract the name of the workgroup
			$res = mysql_query("select grp.ugr_ID from ".USERS_DATABASE.".sysUGrps grp, ".USERS_DATABASE.".sysUsrGrpLinks where grp.ugr_Name='".mysql_real_escape_string($grpName)."' and ugl_GroupID=grp.ugr_ID and ugl_UserID=$usrID");
			if (mysql_num_rows($res) == 0) { //if the user is not a member
				$wg .= '&wgkwd=' . urlencode($tag);
				array_push($outTags, str_replace("\\", "", $tag));	//this removes the \ from wgname\tagname to create a personal tag of wgnametagname
			}else { // put the workgroup tag as is into the output tags
				array_push($outTags, $tag);
			}
		}
		else {
			array_push($outTags, $tag);
		}
	}
	if (count($outTags)) { //reset tag request param
		$_REQUEST['tag'] = join(',', $outTags);
	}
	else {
		unset($_REQUEST['tag']);
	}
}

$isNewRecID = false;
$doiDT = (defined('DT_DOI')?DT_DOI:0);
$webIconDT = (defined('DT_WEBSITE_ICON')?DT_WEBSITE_ICON:0);
$isbnDT = (defined('DT_ISBN')?DT_ISBN:0);
$issnDT = (defined('DT_ISSN')?DT_ISSN:0);
$titleDT = (defined('DT_NAME')?DT_NAME:0);
$relTypDT = (defined('DT_RELATION_TYPE')?DT_RELATION_TYPE:0);
$relSrcDT = (defined('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
$relTrgDT = (defined('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0);




/* arrive with a new (un-bookmarked) URL to process */
if (! @$_REQUEST['_submit']  &&  @$_REQUEST['bkmrk_bkmk_url']) {

	if (! @$rec_id  &&  ! @$force_new) {

		/* look up the records table, see if the requested URL is already in the database; if not, add it */
		$res = mysql_query('select * from Records where rec_URL = "'.mysql_real_escape_string($url).'" '.
								'and (rec_OwnerUGrpID in (0'.(get_user_id()?','.get_user_id():'').')'.
										' or not rec_NonOwnerVisibility="hidden")');
		if (($row = mysql_fetch_assoc($res))) { // found record
			$rec_id = intval($row['rec_ID']);
			$fav = $_REQUEST["f"];
			$bd = mysql__select_assoc('recDetails', 'concat(dtl_DetailTypeID, ".", dtl_Value)', '1',
				'dtl_RecID='.$rec_id.' and ((dtl_DetailTypeID = '.$doiDT.' and dtl_Value in ("'.join('","', array_map("mysql_real_escape_string", $dois)).'"))'.
					' or  (dtl_DetailTypeID = '.$webIconDT.' and dtl_Value = "'.mysql_real_escape_string($fav).'"))'.
					' or  (dtl_DetailTypeID = '.$issnDT.' and dtl_Value in ("'.join('","', array_map("mysql_real_escape_string", $issns)).'"))'.
					' or  (dtl_DetailTypeID = '.$isbnDT.' and dtl_Value in ("'.join('","', array_map("mysql_real_escape_string", $isbns)).'")))');

			$inserts = array();
			foreach ($dois as $doi) if (! $bd["$doiDT.$doi"]) array_push($inserts, "($rec_id, $doiDT, '" . mysql_real_escape_string($doi) . "')");
			if ($fav  &&  ! $bd["$webIconDT.$fav"]) array_push($inserts, "($rec_id, $webIconDT, '" . mysql_real_escape_string($fav) . "')");
			foreach ($isbns as $isbn) if (! $bd["$isbnDT.$isbn"]) array_push($inserts, "($rec_id, $isbnDT, '" . mysql_real_escape_string($isbn) . "')");
			foreach ($issns as $issn) if (! $bd["$issnDT.$issn"]) array_push($inserts, "($rec_id, $issnDT, '" . mysql_real_escape_string($issn) . "')");

			if ($inserts) {
				mysql_query("update Records set rec_Modified = now() where rec_ID = $rec_id");
				mysql_query("insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values " . join(",", $inserts));
			}
		}
	}
//if no record found check for similar url's
	if (! @$rec_id  &&  ! @$force_new) {
		if (exist_similar($url)) {
			/* there is/are at least one so redirect to a disambiguation page */
			header('Location: ' . HEURIST_BASE_URL . 'records/add/disambiguateRecordURLs.php'
								. '?db='.HEURIST_DBNAME
								. '&bkmk_title=' . urlencode($_REQUEST['bkmrk_bkmk_title'])
								. (@$_REQUEST['f'] ?'&f=' . urlencode($_REQUEST["f"]) : '')
								. '&bkmk_url=' . urlencode($url)
								. '&bkmk_description=' . urlencode($description)
								. (@$_REQUEST['tag'] ?'&tag=' . urlencode($_REQUEST['tag']) : '')
								. (@$_REQUEST['rec_rectype'] ? '&rec_rectype=' . urlencode($_REQUEST['rec_rectype']) : ''));
			return;
		}
	}
//if no similar url's and recID was -1 then force a new record of rec_rectype supplied

	if (!isset($rec_id)  ||  (@$force_new && $force_new)) {
		$isNewRecID = true;
		$rt = intval($_REQUEST['rec_rectype']);
        
		if (! $rt) {
			if ( isset($userDefaultRectype) && check_rectype_exist($userDefaultRectype)) {
				 $_REQUEST['rec_rectype']= $rt = $userDefaultRectype;
			} else if ($url && defined('RT_INTERNET_BOOKMARK')) {
				$_REQUEST['rec_rectype']= $rt = RT_INTERNET_BOOKMARK;	/* Internet bookmark */
			} else if (defined('RT_NOTE')) {
				$_REQUEST['rec_rectype'] = $rt = RT_NOTE;	/* Floating note */
			}
		} else if (!check_rectype_exist($rt)) {
			// the rectype passed in is not available on this instance  send them to the  add resource popup
			header('Location: ' . HEURIST_BASE_URL . 'records/add/addRecord.php'
								. '?db='.HEURIST_DBNAME
                                . '&ver=' . @$_REQUEST['ver']
								. '&t=' . urlencode($_REQUEST['t'])
								. '&error_msg=' . urlencode('Record Type #'. $rt . ' does not exist in this Heurist database'
								. ' (it may not have been enabled). Please choose the record type from the pulldown '));
			return;
		}
        
        $is_AddToExtendedDescription = checkAddToDescription($rt);

		mysql__insert('Records', array('rec_URL' => $url,
										'rec_Title' => $_REQUEST['bkmrk_bkmk_title'],
										'rec_ScratchPad' => ($is_AddToExtendedDescription?'':$description),
										'rec_Added' => date('Y-m-d H:i:s'),
										'rec_Modified' => date('Y-m-d H:i:s'),
										'rec_AddedByUGrpID' => intval($usrID),
										'rec_RecTypeID' => $rt? $rt : RT_INTERNET_BOOKMARK,
										'rec_OwnerUGrpID' => $ownership,
										'rec_NonOwnerVisibility' => (@$_REQUEST['rec_visibility']?(strtolower($_REQUEST['rec_visibility'])):
															(@$userDefaultVisibility ? $userDefaultVisibility :
																(defined('HEURIST_NEWREC_ACCESS') ? HEURIST_NEWREC_ACCESS: 'viewable'))),
										'rec_FlagTemporary' => ($url  ||  @$_REQUEST['bkmrk_bkmk_title'])?0:1 ));

        // TODO: why isn't there some action if ther's a MySQL error other than writing to the error log?


        $rec_id = mysql_insert_id();

		// there are sometimes cases where there is no title set (e.g. webpage with no TITLE tag)
		if (@$_REQUEST['bkmrk_bkmk_title']) {
			mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ('.$rec_id
                    .','.$titleDT.',"'.mysql_real_escape_string($_REQUEST['bkmrk_bkmk_title']).'")');
		}
        if($is_AddToExtendedDescription){
            mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ('.$rec_id
                    .','.DT_SHORT_SUMMARY.',"'.mysql_real_escape_string($description).'")'); //was DT_EXTENDED_DESCRIPTION
        }
		$inserts = array();
		foreach ($dois as $doi) array_push($inserts, "($rec_id, $doiDT, '" . mysql_real_escape_string($doi) . "')");
		if (@$_REQUEST["f"]) array_push($inserts, "($rec_id, $webIconDT, '" . mysql_real_escape_string($_REQUEST["f"]) . "')");
		foreach ($isbns as $isbn) array_push($inserts, "($rec_id, $isbnDT, '" . mysql_real_escape_string($isbn) . "')");
		foreach ($issns as $issn) array_push($inserts, "($rec_id, $issnDT, '" . mysql_real_escape_string($issn) . "')");
        

        //insert arbitrary detail types based on URL parameter defval
        if(@$_REQUEST["defval"]){
            $default_value = json_decode($_REQUEST["defval"],true);
            foreach($default_value as $defval){
                $dtyID = array_keys($defval);
                if(is_array($dtyID)){
                    $dtyID = @$dtyID[0];
                }
                if(intval($dtyID)>0){
                    $res = mysql_query("select dty_ID from defDetailTypes where dty_ID=$dtyID");
                    if ($res && $row = mysql_fetch_row($res)) {
                        if(intval($row[0])>0 && @$defval[$dtyID]){
                            array_push($inserts, "($rec_id, $dtyID, '" . mysql_real_escape_string($defval[$dtyID]) . "')");
                        }
                    }
                }
            }
        }
        
		if ($inserts) mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ' . join(",", $inserts));

		if ($description) insert_woot_content($rec_id, $description);

		//artem - add auto thumbnail creation
		if ($url && $rt == RT_INTERNET_BOOKMARK) {
			insert_thumbnail_content($rec_id, $url);
		}
	}
} // end pre-processing of url

// no recID or url passed in so create a new record
if (! @$rec_id  and  ! @$_REQUEST['bkmrk_bkmk_url']) {
	/* create a new public note */
	$isNewRecID = true;
	$rt = intval($_REQUEST['rec_rectype']);
	if (!check_rectype_exist($rt)) {
		// the rectype passed in is not available on this instance  send them to the  add resource popup
		header('Location: ' . HEURIST_BASE_URL . 'records/add/addRecord.php'
							. '?db='.HEURIST_DBNAME
                            . '&ver=' . @$_REQUEST['ver']
							. '&t=' . urlencode($_REQUEST['t'])
							. '&error_msg=' . urlencode('Record Type #'. $rt . ' does not exist in this Heurist Database'
							. ' (it may not have been enabled). Please choose the record type from the pulldown '));
		return;
	}
    
    $is_AddToExtendedDescription = checkAddToDescription($rt);

	mysql__insert('Records', array('rec_Title' => $_REQUEST['bkmrk_bkmk_title'],
									'rec_ScratchPad' => ($is_AddToExtendedDescription?'':$description),
									'rec_Added' => date('Y-m-d H:i:s'),
									'rec_Modified' => date('Y-m-d H:i:s'),
									'rec_AddedByUGrpID' => intval($usrID),
									'rec_RecTypeID' => $rt? $rt : RT_INTERNET_BOOKMARK,
									'rec_OwnerUGrpID' => $ownership,
									'rec_NonOwnerVisibility' => (@$_REQUEST['rec_visibility']?(strtolower($_REQUEST['rec_visibility'])):
															(@$userDefaultVisibility ? $userDefaultVisibility :
																(defined('HEURIST_NEWREC_ACCESS') ? HEURIST_NEWREC_ACCESS: 'viewable'))),
                                    'rec_FlagTemporary' => (@$_REQUEST['bkmrk_bkmk_title'])?0:1 ));



	$rec_id = mysql_insert_id();
	if (@$_REQUEST['bkmrk_bkmk_title']) {
		mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ('.
						$rec_id.','.$titleDT.',"'.mysql_real_escape_string($_REQUEST['bkmrk_bkmk_title']).'")');
	}
    if($is_AddToExtendedDescription){
            mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ('.$rec_id
                    .','.DT_SHORT_SUMMARY.',"'.mysql_real_escape_string($description).'")'); //was DT_EXTENDED_DESCRIPTION
    }
    
	$inserts = array();
	foreach ($dois as $doi) array_push($inserts, "($rec_id, $doiDT, '" . mysql_real_escape_string($doi) . "')");
	if (@$_REQUEST["f"]) array_push($inserts, "($rec_id, $webIconDT, '" . mysql_real_escape_string($_REQUEST["f"]) . "')");
	foreach ($isbns as $isbn) array_push($inserts, "($rec_id, $isbnDT, '" . mysql_real_escape_string($isbn) . "')");
	foreach ($issns as $issn) array_push($inserts, "($rec_id, $issnDT, '" . mysql_real_escape_string($issn) . "')");
    
    //insert arbitrary detail types based on URL parameter defval
    if(@$_REQUEST["defval"]){
        $default_value = json_decode($_REQUEST["defval"],true);
        foreach($default_value as $defval){
            $dtyID = array_keys($defval);
            if(is_array($dtyID)){
                    $dtyID = @$dtyID[0];
            }
            if(intval($dtyID)>0){
                $res = mysql_query("select dty_ID from defDetailTypes where dty_ID=$dtyID");
                if ($res && $row = mysql_fetch_row($res)) {
                    if(intval($row[0])>0 && @$defval[$dtyID]){
                        $s = "($rec_id, $dtyID, '" . mysql_real_escape_string($defval[$dtyID]) . "')";
//DEBUG error_log('ins: '.$s);                
                        array_push($inserts, $s);
                    }
                }
            }
        }
    }
    
    
    
	if ($inserts) mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ' . join(",", $inserts));

	if ($description) insert_woot_content($rec_id, $description);

}

// there is a record and it wasn't forced directly   //SAW shouldn't this test rfw_NewRecID
if ($rec_id  &&  ! @$_REQUEST['force_new']) {
	/* user has selected a bookmark that they may or may not have bookmarked already. FFSI!
	 * If they do in fact have it bookmarked, redirect to the edit page
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

			mysql_query("update Records set rec_ScratchPad = '" . mysql_real_escape_string($notesOut) . "' where rec_ID = $rec_id");

			insert_woot_content($rec_id, $description);
		}
        
        $url = HEURIST_BASE_URL . '?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$rec_id;
        header('Location: ' . $url);    
        
		return;
	}
}

// POST RecordCreation/Find infomation add/update
// if there is a record now then add any extras that have been passed in - tags or related records
if ($rec_id) {

	if ($usrID && !@$bkmk) {
	mysql__insert('usrBookmarks', array(
		'bkm_recID' => $rec_id,
		'bkm_Added' => date('Y-m-d H:i:s'),
		'bkm_Modified' => date('Y-m-d H:i:s'),
		'bkm_UGrpID' => $usrID
        //'bkm_Notes' => @$description
	));
	}

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
				$res = mysql_query("select tag_ID from usrTags, ".USERS_DATABASE.".sysUGrps grp, ".USERS_DATABASE.".sysUsrGrpLinks where tag_Text='".mysql_real_escape_string($kwdName)."' and grp.ugr_Name='".mysql_real_escape_string($grpName)."' and tag_UGrpID=grp.ugr_ID and ugl_GroupID=grp.ugr_ID and ugl_UserID=$usrID");
				$kwd_id = mysql_fetch_row($res);
				$kwd_id = $kwd_id[0];
			}
			else {
				//check for existing usr personal tag
				$res = mysql_query("select tag_ID from usrTags where tag_Text = \"".mysql_real_escape_string($tag)."\" and tag_UGrpID=$usrID");
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
	// handle request for relationship records
	if (@$_REQUEST["related"]) {
		$other_bib_id = $_REQUEST["related"];
		$reln_type = "IsRelatedTo";
		if (@$_REQUEST["reltype"]) {
			mysql_query("select trm_ID,trm_Label from defTerms where trm_Label like '".mysql_real_escape_string($_REQUEST["reltype"])."' limit 1;");
			if (mysql_num_rows($res) > 0) {
				$row = mysql_fetch_assoc($res);
				$reln_type = $row["trm_ID"];	// saw TODO: check that this is aligned with the enum value change
				// saw TODO check if CONSTRAINTS are fine else give constraint error
			}
		}
		mysql__insert("Records", array(
			"rec_Title" => "Relationship ($rec_id $reln_type $other_bib_id)",	// saw TODO: change this to RecTitle Type RecTitle
					"rec_Added"     => date('Y-m-d H:i:s'),
					"rec_Modified"  => date('Y-m-d H:i:s'),
					"rec_RecTypeID"   => RT_RELATION,
					"rec_AddedByUGrpID" => $usrID
		));
		$relnBibID = mysql_insert_id();

		if ($relnBibID > 0) {
			$query = "insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_Value) values ";
			$query .=   "($relnBibID, $titleDT, 'Relationship')";
			$query .= ", ($relnBibID, $relSrcDT, $rec_id)";
			$query .= ", ($relnBibID, $relTrgDT, $other_bib_id)";
			$query .= ", ($relnBibID, $relTypDT, '" . mysql_real_escape_string($reln_type) . "')"; //saw BUG!!! places in label not ID
			mysql_query($query);
		}
	}


	if ($bkm_ID) {
        
        $url = HEURIST_BASE_URL . '?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$rec_id;
        header('Location: ' . $url);    
		return;
	}
}

function insert_woot_content($rec_id, $content) {
    return; //2017-09-04 no woot anymore
    
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
}

function check_rectype_exist($rt) {
	$res = mysql_query("select distinct rty_ID,rty_Name from defRecTypes where rty_ID = $rt");
    if($res)
	while ($row = mysql_fetch_assoc($res)) {
		if ($row["rty_ID"] == $rt) {
			return true;
		}
	}
	return false;
}

//artem - generate thumbnail and insert detail about it
function insert_thumbnail_content($recid, $url){

	if(defined('DT_THUMBNAIL')){

		$res = generate_thumbnail($url, false);

		if(!array_key_exists("error", $res)){
			mysql_query('insert into recDetails (dtl_RecID, dtl_DetailTypeID, dtl_UploadedFileID) values ('.$recid.','.DT_THUMBNAIL.','.$res['file']['id'].')');
		}
	}
}
        
//check that DT_EXTENDED_DESCRIPTION is defined for given record type
function checkAddToDescription($rt) {
    //before 2017-09-07 it was DT_EXTENDED_DESCRIPTION
    if(defined('DT_SHORT_SUMMARY') ){
        $query = 'select rst_ID from defRecStructure where rst_RecTypeID = '
                   .($rt? $rt : RT_INTERNET_BOOKMARK).' and rst_DetailTypeID='.DT_SHORT_SUMMARY;
        $res = mysql_query($query);
        if (mysql_num_rows($res)>0) {
            return true;
        }
    }
    return false;
}

?>
