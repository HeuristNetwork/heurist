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
* editGroupTags.php
* workgroup tags addition/deletion/statistics
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



define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

if (! is_logged_in()) {
	header('Location: ' . HEURIST_BASE_URL);
	return;
}

mysql_connection_select(DATABASE);

?>
<html>
<head>
 <title>Workgroup Tags</title>

<link rel="stylesheet" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
<link rel="stylesheet" href="<?=HEURIST_SITE_PATH?>common/css/edit.css">
<link rel="stylesheet" href="<?=HEURIST_SITE_PATH?>common/css/admin.css">
 <style type="text/css">
.tbox { border: 1px solid black; margin: 1px; }
.gr_div { padding: 4px 0px; }
ul { margin: 2px; }
 </style>

<script type="text/javascript">
function delete_tag(tag_ID) {
	if (! confirm('Really delete this tag?')) return;
	var kd = document.getElementById('kwd_delete');
	kd.value = tag_ID + '';
	kd.form.submit();
}
</script>

</head>

<body class="popup" width="840" height="550" style="padding: 0; overflow:hidden;">
<?php if(@$_REQUEST['popup']!="yes") { ?>
<div class="banner"><h2>Workgroup Tags</h2></div>
<?php } ?>
<div id="page-inner">
Unlike personal tags, which can be freely added by individual users while editing data and apply only to that user,
workgroup tags are a controlled list of shared tags established by a workgroup administrator.
<br>The list below only shows workgroups of which you are an administrator. <br>

<?php
	if (array_key_exists('deleting', $_REQUEST) && $_REQUEST['deleting']) {
		delete_tag();
	} else if (array_key_exists('adding', $_REQUEST) && $_REQUEST['adding']) {
		add_tags();
	}
?>

<form method="post">

<br>Note: Deleting a tag deletes all references to that tag.
<p>To add new tags, type a tag into the blank field at the end of a workgroup and hit [enter] or click this button:


<input type="submit" value="Add workgroup tag(s)">
<?php if(@$_REQUEST['popup']=="yes") { ?>
<input type="button" value="Close Form" onclick="{window.close('bla');}">
<?php } ?>
<input type="hidden" name="adding" value="1">
<input type="hidden" name="deleting" value="0" id="kwd_delete">

<?php
	$adminGroupList = array();
	// This retrieves all the user groups of which the user is a member IN THE DEFAULT DATABASE, ratehr than the current
	// foreach ($_SESSION['heurist']['user_access'] as $grp_id => $access) {
	//	if ($access == "admin") array_push($adminGroupList,$grp_id);
	$q='select distinct ugl_GroupID from '.USERS_DATABASE.'.sysUsrGrpLinks where ugl_UserID='.get_user_id().' and ugl_Role="admin"';
	$gres = mysql_query($q);
	while ($grp = mysql_fetch_assoc($gres)) {
		array_push($adminGroupList,$grp['ugl_GroupID']);
	}
	if (count($adminGroupList) < 1){
		print '<html><body> <h2>No workgroups</h2>You must have administration rights to a work group in order to add workgroup tags to it. You are not an adminstrator of any workgroups in this database.</body></html>';
		return;
	}
	$adminGroupList = join(',', $adminGroupList);
	$gres = mysql_query('select grp.ugr_ID, grp.ugr_Name from '.USERS_DATABASE.'.sysUGrps grp where grp.ugr_ID in ('.$adminGroupList.') order by grp.ugr_Name');
	while ($grp = mysql_fetch_assoc($gres)) {
		print '<div class="gr_div">';
		print '<b>' . htmlspecialchars($grp['ugr_Name']) . '</b>';

		print '<ul>';
		$res = mysql_query('select tag_ID, tag_Text, count(rtl_ID) as tgi_count from usrTags left join usrRecTagLinks on rtl_TagID=tag_ID where tag_UGrpID='.$grp['ugr_ID'].' group by tag_ID, rtl_TagID order by tag_Text');
		while ($tag = mysql_fetch_assoc($res)) {
			$searchlink = HEURIST_BASE_URL.'search/search.html?q=tag%3A%22'.$grp['ugr_Name'].'%5C'.$tag['tag_Text'].'%22&w=all&stype=';
			if ($tag['tgi_count'] == 0) $used = '';
			else $used = '<i>(<a target=_blank href="'.$searchlink.'">used '.($tag['tgi_count'] == 1 ? 'once' : $tag['tgi_count'].' times').'</a>)</i>';
?>
 <li><b><?= htmlspecialchars($tag['tag_Text']) ?></b> <?= $used ?> [<a href="#" onClick="delete_tag(<?= $tag['tag_ID'] ?>); return false;">delete</a>]</li>
<?php
		}
		// TODO: this is giving undefined index error for 'new' in the log  7/11/11
		print ' <li>add: <input type="text" class="tbox" name="new[' . htmlspecialchars($grp['ugr_ID']) . ']" onkeypress="return (event.which != 92  &&  event.keyCode != 92);" value="' . (  array_key_exists('new', $_REQUEST)?htmlspecialchars($_REQUEST['new'][$grp['ugr_ID']]):'') . '"></li>';
		print '</ul>';
		print '</div>';
	}
?>

</form>
</div>
</body>
</html>
<?php
/***** END OF OUTPUT *****/


function add_tags() {
	$insert_stmt = '';

	foreach ($_REQUEST['new'] as $key => $value) {
		$_REQUEST['new'][$key] = '';	// clear existing value
		$value = trim($value);
		if ($value == '' || intval($key) == 0) continue;	// saw NOTE: assumes UGrpID 0 is not valid for tagging

		if ($insert_stmt) $insert_stmt .= ', ';
		$insert_stmt .= '("' . addslashes($value) . '", ' . intval($key) . ')';
	}
	if (! $insert_stmt) return;

	$insert_stmt = 'insert into usrTags (tag_Text, tag_UGrpID) values ' . $insert_stmt;
	mysql_connection_overwrite(DATABASE);
	mysql_query($insert_stmt);
	if (mysql_affected_rows() == 1) {
		print '<div style="color: red;">1 tag added</div>';
	} else if (mysql_affected_rows() >= 0) {
		print '<div style="color: red;">' . mysql_affected_rows() . ' tags added</div>';
	} else {
		print '<div style="color: red;">Error: ' . mysql_error() . '</div>';
	}
}


function delete_tag() {
	$tag_id = intval($_REQUEST['deleting']);
	mysql_connection_overwrite(DATABASE);
	mysql_query('delete from usrTags where tag_ID = ' . $tag_id );
	if (mysql_affected_rows() >= 1) {	// overkill
		print '<div style="color: red;">1 tag deleted</div>';
	} else {
		print '<div style="color: red;">No tags deleted</div>';
	}

	mysql_query('delete from usrRecTagLinks where rtl_TagID = ' . $tag_id);
}

?>
