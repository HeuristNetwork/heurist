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



/*<!--
 * actionMethods.php
 *
 *  manipulations for bookmarks, tags and user/group access/visibility
 *
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

function delete_bookmarks($data) {

	$res = array();
	$bkmk_ids = $data['bkmk_ids'];

	mysql_connection_overwrite(DATABASE);
	mysql_query('delete usrRecTagLinks from usrBookmarks left join usrRecTagLinks on rtl_RecID=bkm_RecID where bkm_ID in ('.join(',', $bkmk_ids).') and bkm_UGrpID=' . get_user_id());
	mysql_query('delete from usrBookmarks where bkm_ID in ('.join(',', $bkmk_ids).') and bkm_UGrpID=' . get_user_id());
	$deleted_count = mysql_affected_rows();

	if (mysql_error()) {
		$res['problem'] = "Database problem - no bookmarks deleted";
	}else{
		$res['ok'] = "Deleted ". $deleted_count. " bookmark".($deleted_count>1?"s":"");
	}
	return $res;
}

function add_tags($data) {

	$res = array();
	$bkmk_ids = $data['bkmk_ids'];
	$tagString = trim($data['tagString']);

	if ($tagString) {
		mysql_connection_overwrite(DATABASE);

		$tags = get_ids_for_tags(array_filter(explode(',', $tagString)), true);
		mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
				  . 'select bkm_recID, tag_ID from usrBookmarks, usrTags '
				  . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id()
				  . ' and tag_ID in (' . join(',', $tags) . ')');
		$tag_count = mysql_affected_rows();

		if (mysql_error()) {
			$res['problem'] = 'Database problem - ' . addslashes(mysql_error()) . ' - no tags added';
		} else if ($tag_count == 0) {

			$res = bookmark_and_tag_record_ids($data);

		} else if ($tag_count > 0) {

			$res['ok'] = ($tag_count.' Tags added');
		}

	} else {
		$res['problem'] = "No tags have been added";
	}

	return $res;
}

function add_wgTags_by_id($data) {

	$res = array();
	$rec_ids = $data['rec_ids'];
	$wgTags = $data['wgTag_ids'];

	if (count($wgTags) && count($rec_ids)) {
		mysql_connection_overwrite(DATABASE);

		mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
				  . 'select rec_ID, tag_ID from usrTags, '.USERS_DATABASE.'.sysUsrGrpLinks, Records '
				  . ' where rec_ID in (' . join(',', $rec_ids) . ') '
				  . ' and ugl_GroupID=tag_UGrpID and ugl_UserID='.get_user_id()	//make sure the user blongs to the workgroup
				  . ' and tag_ID in (' . join(',', $wgTags) . ')');
		$wgTag_count = mysql_affected_rows();

		if (mysql_error()) {
			$res['problem'] = 'Database problem - ' . addslashes(mysql_error()) . ' - no workgroup tags added';
		} else if ($wgTag_count == 0) {
			$res['none'] = 'No new workgroup tags needed to be added';
		} else {
			$res['ok'] = $wgTag_count.' workgroup tags added';
		}

	}else{
		$res['problem'] = 'No workgroup tags have been added';
	}

	return $res;
}

function remove_wgTags_by_id($data) {

	$res = array();
	$rec_ids = $data['rec_ids'];
	$wgTags = $data['wgTag_ids'];

	$wgTag_count = 0;

	if (count($wgTags) && count($rec_ids)) {

		mysql_connection_overwrite(DATABASE);

		mysql_query('delete usrRecTagLinks from usrRecTagLinks'
				 . ' left join usrTags on tag_ID = rtl_TagID'
				 . ' left join '.USERS_DATABASE.'.sysUsrGrpLinks on ugl_GroupID = tag_UGrpID'
		         . ' where rtl_RecID in (' . join(',', $rec_ids) . ')'
				 . ' and ugl_UserID = ' . get_user_id()
		         . ' and tag_ID in (' . join(',', $wgTags) . ')');
		$wgTag_count = mysql_affected_rows();

		if (mysql_error()) {
			$res['problem'] = 'Database problem - ' . addslashes(mysql_error()) . ' - no workgroup tags added';
		} else if ($wgTag_count == 0) {
			$res['none'] = 'No workgroup tags matched, none removed';
		} else {
			$res['ok'] = $wgTag_count.' workgroup tags removed';
		}

	}else{
		$res['problem'] = 'No workgroup tags have been removed';
	}

	return $res;
}

function remove_tags($data) {

	$res = array();
	$bkmk_ids = $data['bkmk_ids'];
	$tagString = trim($data['tagString']);

	if ($tagString) {
		mysql_connection_overwrite(DATABASE);

		$tag_count = 0;
		$tags = get_ids_for_tags(array_filter(explode(',', $tagString)), false);
		if (count($bkmk_ids)  &&  $tags  &&  count($tags)) {
			mysql_query('delete usrRecTagLinks from usrBookmarks'
					 . ' left join usrRecTagLinks on rtl_RecID = bkm_RecID'
					 . ' left join usrTags on tag_ID = rtl_TagID'
					 . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id()
					 . ' and tag_ID in (' . join(',', $tags) . ')'
					 . ' and tag_UGrpID = bkm_UGrpID');
			$tag_count = mysql_affected_rows();
		}
		if (mysql_error()) {
			$res['problem'] = 'Database problem - ' . addslashes(mysql_error()) . ' - no tags removed';
		} else if ($tag_count == 0) {

			$res['none'] = "No tags matched, none removed";

		} else {

			$res['ok'] = ($tag_count.' Tags removed');
		}

	} else {
		$res['problem'] = "No tags removed";
	}

	return $res;
}

function set_ratings($data)
{
	$res = array();
	$bkmk_ids = $data['bkmk_ids'];
	$rating = intval($data['ratings']);

	mysql_connection_overwrite(DATABASE);
	$query =  'update usrBookmarks set bkm_Rating = ' . $rating . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id();
	mysql_query($query);
	$update_count = mysql_affected_rows();

	if (mysql_error()) {
		$res['problem'] = 'Database problem - ' . addslashes(mysql_error()) . ' - ratings not set';
	}else  if ($update_count == 0) {
		$res['none'] = "No changes made - ratings are up-to-date";
	} else {
		$res['none'] = "Ratings have been set";
	}

	return $res;
}

function bookmark_references($data) {

	$res = array();

	mysql_connection_overwrite(DATABASE);

	$rec_ids = record_filter($data['rec_ids']);
	$new_rec_ids = mysql__select_array('Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id(),
	                                   'rec_ID', 'bkm_ID is null and rec_ID in (' . join(',', $rec_ids) . ')');
	//find bookmarks for given list of records
	$existing_bkmk_ids = mysql__select_array('Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id(),
	                                   'concat(bkm_ID,":true")', 'bkm_ID is not null and rec_ID in (' . join(',', $rec_ids) . ')');

	if ($new_rec_ids) {
		//add new bookmarks
		mysql_query('insert into usrBookmarks
		                  (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)
		                  select ' . get_user_id() . ', now(), now(), rec_ID
		                    from Records where rec_ID in (' . join(',', $new_rec_ids) . ')');
		$inserted_count = mysql_affected_rows();
		$bkmk_ids = mysql__select_array('usrBookmarks', 'bkm_ID', 'bkm_recID in ('.join(',',$new_rec_ids).') and bkm_UGrpID = ' . get_user_id());
	} else {
		$inserted_count = -1;
	}

	if (mysql_error()) {
		$res['problem'] = 'Database problem - ' . addslashes(mysql_error()) . ' - no bookmarks added';
	}else  if ($inserted_count < 1  &&  count($existing_bkmk_ids) < 1) {

		//neither new bookmarks, nor existing ones - try to add tags in this case
		//was $res['execute'] = array('addTagsPopup', true);
		$res['none'] = 'No boomarks added (2)';

	} else {

			if(isset($bkmk_ids)){ //fresh bookmarks
//error_log(">>>>".print_r($bkmk_ids, true));
				$res['execute'] = array('addRemoveTagsPopup', true, $rec_ids , $bkmk_ids);
			}else{
				$rec_ids = null;
				$bkmk_ids = null;
				$res['none'] = 'No boomarks added';
			}

	}

	return $res;
}

//
//
//
function bookmark_and_tag_record_ids ( $data ) {

	$res = array();

	mysql_connection_overwrite(DATABASE);

	$rec_ids = record_filter($data['rec_ids']);
	$tagString = trim($data['tagString']);

	if (!$tagString) {
		$res['none'] = 'No tags selected for records.';
	}else{

		$new_rec_ids = mysql__select_array('Records left join usrBookmarks on bkm_recID=rec_ID and bkm_UGrpID='.get_user_id(),
										'rec_ID', 'bkm_ID is null and rec_ID in (' . join(',', $rec_ids) . ')');

		if ($new_rec_ids) {
			mysql_query('insert into usrBookmarks
					  (bkm_UGrpID, bkm_Added, bkm_Modified, bkm_recID)
					  select ' . get_user_id() . ', now(), now(), rec_ID
						from Records where rec_ID in (' . join(',', $new_rec_ids) . ')');
			$inserted_count = mysql_affected_rows();
		}

		$bkmk_ids = mysql__select_array('usrBookmarks', 'bkm_ID', 'bkm_recID in ('.join(',',$rec_ids).') and bkm_UGrpID = ' . get_user_id());

		if (mysql_error()) {
			$res['problem'] = 'Database problem - ' . addslashes(mysql_error()) . ' - no bookmarks added';
		} else if (count($bkmk_ids) < 1) {
			$res['none'] = 'No bookmark found or created for selected records.';
		} else {	//we have bookmarks lets add the tags

			$tags = get_ids_for_tags(array_filter(explode(',', $tagString)), true);
			mysql_query('insert ignore into usrRecTagLinks (rtl_RecID, rtl_TagID) '
					  . 'select bkm_recID, tag_ID from usrBookmarks, usrTags '
					  . ' where bkm_ID in (' . join(',', $bkmk_ids) . ') and bkm_UGrpID = ' . get_user_id()
					  . ' and tag_ID in (' . join(',', $tags) . ') and tag_UGrpID = bkm_UGrpID');
			$tag_count = mysql_affected_rows();

			if (mysql_error()) {
				$res['problem'] = 'Database problem - ' . addslashes(mysql_error()) . ' - no tags added.';
			} else {
				if ($tag_count == 0) {
					$message = 'No new tags needed to be added' ;
				} else {
					$message = 'Tagged '.count($bkmk_ids). ' records';
				}
				$message .= ''.($inserted_count ? ' ('. $inserted_count . ' new bookmarks, ' : ' (').
							($tag_count ? $tag_count . ' tags)' : ')');

				$res[(($inserted_count>0 || $tag_count>0)?'ok':'none')] = $message;
			}
		}
	}
	return $res;
}

//not used since 2012-02-13
function bookmark_tag_and_save_search($data) {
/*
	$res = array();

	mysql_connection_overwrite(DATABASE);
	$res = bookmark_and_tag_record_ids($data);

	if (@$res['problem']) {
		return $res['problem'] = 'Error while bookmarking and tagging '.$res['problem'].' - Search not saved.';
	}

	$wg = intval(@$data['svs_UGrpID']);

	$now = date('Y-m-d');
	$cmb = Array(
		'svs_Name'     => $data['svs_Name'],
		'svs_Query'    => urldecode($data['svs_Query']),
		'svs_UGrpID'   => get_user_id(),
		'svs_Added'     => $now,
		'svs_Modified'  => $now);

	if ($wg) {	// user / group id was passed in so use it over teh current user id.
		$cmb['svs_UGrpID'] = $wg;
	}

	// overwrites saved search with same name
	$res = mysql_query('select svs_ID, svs_UGrpID from usrSavedSearches where svs_Name="'.slash($data['svs_Name']).'" and '.
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
		$onload = 'alert(\'Database problem (' . addslashes(mysql_error()).') - search not saved\'); location.replace(\'actionHandler.php?db='.HEURIST_DBNAME.'\');';
	} else {
		$onload = 'location.replace(\'actionHandler.php?db='.HEURIST_DBNAME.'\');'.
					'top.HEURIST.search.insertSavedSearch(\''.slash($_REQUEST['svs_Name']).'\', \''.slash($_REQUEST['svs_Query']).'\', '.$wg.', '.$ss.');'.
					'top.HEURIST.util.getJsonData("'. HEURIST_BASE_URL.'search/saved/manageCollection.php?clear&db='.HEURIST_DBNAME.'", top.HEURIST.search.addRemoveCollectionCB);'.
					'top.HEURIST.search.popupNotice(\' Search \"'.$_REQUEST['svs_Name'].'\" saved. '. $message.'\');' .
					'top.location.href = top.location.href + (top.location.href.match(/\?/) ? \'&\' : \'?\') + \'sid='.$ss.'\'+(top.location.href.match(/db=/) ? \'\' : &db='.HEURIST_DBNAME.');';
	}
	return $onload;
*/
}

//
function save_search($data) {

	$res = array();

	mysql_connection_overwrite(DATABASE);
	$wg = intval(@$data['svs_UGrpID']);
	$sID = $data['svs_ID'];
	//$publish = $data['publish'];
	$label = @$data['svs_Name'];

	$now = date('Y-m-d');
	$cmb = Array(
		'svs_Name'     => $data['svs_Name'],
		'svs_Query'    => urldecode($data['svs_Query']),
		'svs_UGrpID'   => ($wg>0?$wg:get_user_id()),
		'svs_Added'     => $now,
		'svs_Modified'  => $now);

	/* overwrites saved search with same name
	$res = mysql_query('select svs_ID, svs_UGrpID from usrSavedSearches where svs_Name="'.slash($_REQUEST['svs_Name']).'"'.
						' and svs_UGrpID='.$cmb['svs_UGrpID']);
	$row = mysql_fetch_row($res);*/

	if ($sID) {
		/*$row ||  if ($row ) {
		 	$ss = intval($row[0]);
		}*/
		mysql__update('usrSavedSearches', 'svs_ID='.$sID, $cmb);
	} else {
		mysql__insert('usrSavedSearches', $cmb);
		$sID = mysql_insert_id();
	}

	if (mysql_error()) {

		$res['problem'] = 'Database problem (' . addslashes(mysql_error()).') - search not saved';
	} else {
		$res['execute'] = array('insertSavedSearch', $data['svs_Name'], $data['svs_Query'] , $wg, $sID);

		//$onload = "location.replace('actionHandler.php?db=".HEURIST_DBNAME."'); top.HEURIST.search.insertSavedSearch('".slash($data['svs_Name'])."', '".slash($data['svs_Query'])."', ".$wg.", ".$sID.");";
		/*if ($publish) {
			$onload .= " top.location.href = top.location.href + (top.location.href.match(/\?/) ? '&' : '?') + 'pub=1&label=".$label."&sid=".$ss."'+(top.location.href.match(/db=/) ? '' : '&db=".HEURIST_DBNAME."');";
		}else{
			$onload .= ' top.location.href = top.location.href + (top.location.href.match(/\?/) ? \'&\' : \'?\') + \'label='.$label.'&sid='.$ss.'\'+(top.location.href.match(/db=/) ? \'\' : \'&db='.HEURIST_DBNAME.'\');';
		}*/
	}
	return $res;
}

//
// set access and visibility
//
function set_wg_and_vis($data) {

	$res = array();

	if (is_admin()) {
		$rec_ids = $data['rec_ids'];
		$wg = intval(@$data['wg_id']);
		$vis = $data['vis'];

		if (($wg == -1 ||  $wg == 0 || in_array($wg, get_group_ids()))  &&
			(in_array(strtolower($vis),array('viewable','hidden','pending','public'))))
		{
			mysql_connection_overwrite(DATABASE);

			if ($wg === 0 && $vis === 'hidden') $vis = 'viewable';
			if ($wg >= 0){
				$editable = ' rec_OwnerUGrpID = ' . $wg . ', ';
			}else{
				$editable = '';
			}

			$query = 'update Records set '.$editable.'rec_NonOwnerVisibility = "' . $vis . '"'.
							' where rec_ID in (' . join(',', $rec_ids) . ')';

			mysql_query($query);
			if (mysql_error()) {
				$res['problem'] = 'Database problem (' . addslashes(mysql_error()).')';
			}else{
				$res['ok'] = mysql_affected_rows().' records updated';
			}

		} else {
			$res['problem'] = 'Invalid arguments for workgoup or visibility';
		}
	} else {
		$res['problem'] = 'Permission denied for workgroup or visibility setting';
	}
	return $res;
}

/* not used anymore
function print_input_form() {
?>
<form action="actionHandler.php?db=<?=HEURIST_DBNAME?>" method="get" id="action_form">
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
<input type="hidden" name="db" id="db" value="<?=HEURIST_DBNAME?>">
<input type="hidden" name="action" id="action" value="">
<input type="hidden" name="reload" id="reload" value="">
</form>
<?php
}
*/

function record_filter($rec_ids) {
	// return an array of only the rec_ids that exist and the user has access to (workgroup filtered)

	$wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks', 'ugl_GroupID', 'ugl_UserID='.get_user_id());
	array_push($wg_ids, 0);	// zero as record owner means owned by all
	if (! in_array(get_user_id(),$wg_ids)) {
		array_push($wg_ids, get_user_id());
	}
	$f_rec_ids = mysql__select_array('Records', 'rec_ID',
	                                 'rec_ID in ('.join(',', array_map('intval', $rec_ids)).') and (rec_OwnerUGrpID in ('.join(',', $wg_ids).') or rec_NonOwnerVisibility = "viewable")');

	return $f_rec_ids;
}

//
//
function get_ids_for_tags($tags, $add) {
	$tag_ids = array();
	foreach ($tags as $tag_name) {
		$tag_name = preg_replace('/\\s+/', ' ', trim($tag_name));
		if(strlen($tag_name)>0){

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
	}

	return $tag_ids;
}
?>
