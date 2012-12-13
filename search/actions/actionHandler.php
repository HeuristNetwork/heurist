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
if (!is_logged_in()) return;

require_once("actionMethods.php");

//require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

//$reload_message = "\\n\\nInformation changes will be visible on reload of this page.\\nReloading will reset filters and selection.\\n\\n'OK' to reload, 'Cancel' to leave display as-is";

$res = null;

// decode and unpack data
if(@$_REQUEST['data']){
	$data = json_decode(urldecode(@$_REQUEST['data']), true);

	switch (@$_REQUEST['action']) {
		case 'delete_bookmark':
			$res = delete_bookmarks($data);
			break;

		case 'add_wgTags_by_id':
			$res = add_wgTags_by_id($data);
			break;

		case 'remove_wgTags_by_id':
			$res = remove_wgTags_by_id($data);
			break;

		case 'add_tags':
			$res = add_tags($data);
			break;

		case 'remove_tags':
			$res = remove_tags($data);
			break;

		case 'bookmark_reference':
			$res = bookmark_references($data);
			break;

		case 'bookmark_and_tag':
		case 'bookmark_and_tags':   //save collection of ids with some tag
			$res = bookmark_and_tag_record_ids($data);
			break;


		case 'set_ratings':
			$res = set_ratings($data);
			break;

		case 'save_search':
			$res = save_search($data);
			break;

		case 'bookmark_tag_and_ssearch': //from saveCollectionPopup.html   NOT USED SINCE 2012-02-13
			$res = bookmark_tag_and_save_search($data);
			break;

		case 'set_wg_and_vis':
			$res = set_wg_and_vis($data);
			break;
	}

}else{
	$res = array("problem"=>"Parameter 'data' is missed for action script");
}


header('Content-type: text/javascript');
if($res){
	print json_format($res);
}else{
	$res = array("problem"=>"Parameter 'action' is missed for action script");
}

exit();
?>
