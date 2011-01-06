<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../../common/connect/applyCredentials.php");
require_once(dirname(__FILE__)."/../../common/php/dbMySqlWrappers.php");

if (! is_logged_in()) {
	header('Location: ' . BASE_PATH);
	return;
} else if (! is_admin()) {
	print '<html><body>Workgroup tag administration is restricted to administrators only</body></html>';
	return;
}

mysql_connection_db_select(DATABASE);

?>
<html>
<head>
 <title>Workgroup tags</title>

 <link rel="stylesheet" href="<?=HEURIST_SITE_PATH?>common/css/lite.css">
 <style type="text/css">
body { overflow: auto; }
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

<body>

<?php echo file_get_contents('menu.html'); ?>

<div style="margin: 5px;">
<h2>Workgroup tags</h2>

<?php
	if ($_REQUEST['deleting']) delete_tag();
	else if ($_REQUEST['adding']) add_tags();
?>

<div>To add new tags, type in boxes below and hit Enter.</div>
<div>Deleting a tag deletes all references to that tag.</div>


<form method="post">

<?php
	$adminGroupList = array();
	foreach ($_SESSION['heurist']['user_access'] as $grp_id => $access) {
		if ($access == "admin") array_push($adminGroupList,$grp_id);
	}
	if (count($adminGroupList) < 1){
		print '<html><body> You must have administration rights to work groups in order to add tags</body></html>';
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
			$searchlink = HEURIST_URL_BASE.'search/search.html?q=tag%3A%22'.$grp['ugr_Name'].'%5C'.$tag['tag_Text'].'%22&w=all&stype=';
			if ($tag['tgi_count'] == 0) $used = '';
			else $used = '<i>(<a target=_blank href="'.$searchlink.'">used '.($tag['tgi_count'] == 1 ? 'once' : $tag['tgi_count'].' times').'</a>)</i>';
?>
 <li><b><?= htmlspecialchars($tag['tag_Text']) ?></b> <?= $used ?> [<a href="#" onclick="delete_tag(<?= $tag['tag_ID'] ?>); return false;">delete</a>]</li>
<?php
		}
		print ' <li><input type="text" class="tbox" name="new[' . htmlspecialchars($grp['ugr_ID']) . ']" onkeypress="return (event.which != 92  &&  event.keyCode != 92);" value="' . htmlspecialchars($_REQUEST['new'][$grp['ugr_ID']]) . '"></li>';
		print '</ul>';
		print '</div>';
	}
?>

<input type="submit" value="Add workgroup tag(s)">
<input type="hidden" name="adding" value="1">
<input type="hidden" name="deleting" value="0" id="kwd_delete">

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
	mysql_connection_db_overwrite(DATABASE);
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
	mysql_connection_db_overwrite(DATABASE);
	mysql_query('delete from usrTags where tag_ID = ' . $tag_id );
	if (mysql_affected_rows() >= 1) {	// overkill
		print '<div style="color: red;">1 tag deleted</div>';
	} else {
		print '<div style="color: red;">No tags deleted</div>';
	}

	mysql_query('delete from usrRecTagLinks where rtl_TagID = ' . $tag_id);
}

?>
