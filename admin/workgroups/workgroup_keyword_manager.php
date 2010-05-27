<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__)."/../../common/connect/cred.php");
require_once(dirname(__FILE__)."/../../common/connect/db.php");

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

 <link rel="stylesheet" href="../../css/lite.css">
 <style type="text/css">
body { overflow: auto; }
.tbox { border: 1px solid black; margin: 1px; }
.gr_div { padding: 4px 0px; }
ul { margin: 2px; }
 </style>

 <script type="text/javascript">
function delete_keyword(kwd_id) {
	if (! confirm('Really delete this tag?')) return;
	var kd = document.getElementById('kwd_delete');
	kd.value = kwd_id + '';
	kd.form.submit();
}
 </script>

</head>

<body>

<?php echo file_get_contents('menu.html'); ?>

<div style="margin: 5px;">
<h2>Workgroup tags</h2>

<?php
	if ($_REQUEST['deleting']) delete_keyword();
	else if ($_REQUEST['adding']) add_keywords();
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
	$gres = mysql_query('select grp_id, grp_name from '.USERS_DATABASE.'.Groups where grp_id in ('.$adminGroupList.') order by grp_name');
	while ($grp = mysql_fetch_assoc($gres)) {
		print '<div class="gr_div">';
		print '<b>' . htmlspecialchars($grp['grp_name']) . '</b>';

		print '<ul>';
		$res = mysql_query('select kwd_id, kwd_name, count(kwl_id) as kwi_count from keywords left join keyword_links on kwl_kwd_id=kwd_id where kwd_wg_id='.$grp['grp_id'].' group by kwd_id, kwl_kwd_id order by kwd_name');
		while ($kwd = mysql_fetch_assoc($res)) {
			$searchlink = HEURIST_URL_BASE.'search/heurist-search.html?q=keyword%3A%22'.$grp['grp_name'].'%5C'.$kwd['kwd_name'].'%22&w=all&stype=';
			if ($kwd['kwi_count'] == 0) $used = '';
			else $used = '<i>(<a target=_blank href="'.$searchlink.'">used '.($kwd['kwi_count'] == 1 ? 'once' : $kwd['kwi_count'].' times').'</a>)</i>';
?>
 <li><b><?= htmlspecialchars($kwd['kwd_name']) ?></b> <?= $used ?> [<a href="#" onclick="delete_keyword(<?= $kwd['kwd_id'] ?>); return false;">delete</a>]</li>
<?php
		}
		print ' <li><input type="text" class="tbox" name="new[' . htmlspecialchars($grp['grp_id']) . ']" onkeypress="return (event.which != 92  &&  event.keyCode != 92);" value="' . htmlspecialchars($_REQUEST['new'][$grp['grp_id']]) . '"></li>';
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


function add_keywords() {
	$insert_stmt = '';

	foreach ($_REQUEST['new'] as $key => $value) {
		$_REQUEST['new'][$key] = '';	// clear existing value
		$value = trim($value);
		if ($value == '') continue;

		if ($insert_stmt) $insert_stmt .= ', ';
		$insert_stmt .= '("' . addslashes($value) . '", ' . intval($key) . ')';
	}
	if (! $insert_stmt) return;

	$insert_stmt = 'insert into keywords (kwd_name, kwd_wg_id) values ' . $insert_stmt;
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


function delete_keyword() {
	$kwd_id = intval($_REQUEST['deleting']);
	mysql_connection_db_overwrite(DATABASE);
	mysql_query('delete from keywords where kwd_id = ' . $kwd_id . ' and kwd_wg_id is not null');
	if (mysql_affected_rows() >= 1) {	// overkill
		print '<div style="color: red;">1 tag deleted</div>';
	} else {
		print '<div style="color: red;">No tags deleted</div>';
	}

	mysql_query('delete from keyword_links where kwl_kwd_id = ' . $kwd_id);
}

?>
