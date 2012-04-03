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

define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../records/edit/deleteRecordInfo.php');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	return;
}

mysql_connection_db_overwrite(DATABASE);

?>

<html>
<head>
  <title>HEURIST: Delete records</title>
  <link rel="icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="<?=HEURIST_SITE_PATH?>favicon.ico" type="image/x-icon">
	<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">

  <style type=text/css>
   a img { border: none; }
   .greyed, .greyed div { color: gray; }
		p {line-height:11px;margin:6px 0}
		.deleteButton {color: #BB0000;float: right;font-weight: bold;height: 20px;margin: 10px 0;text-transform: uppercase; width: 60px;}
  </style>
</head>

<body class="popup" width=450 height=350>

<?php

	if (@$_REQUEST['delete'] == 1) {
		$recs_count = 0;
		$bkmk_count = 0;
		$rels_count = 0;
		$errors = array();


		foreach ($_REQUEST['bib'] as $rec_id) {

			mysql_query("start transaction");

			$res = deleteRecord($rec_id);

			if( array_key_exists("error", $res) ){

				mysql_query("rollback");

				array_push($errors, "Rec#".$rec_id."  ".$res["error"]);

			}else{
				mysql_query("commit");

				$recs_count++;
				$rels_count += $res["bkmk_count"];
				$bkmk_count += $res["rel_count"];
			}
		}


		print '<p><b>' . $recs_count . '</b> records, <b>' . $rels_count . '</b> relationships and <b>' . $bkmk_count . '</b> associated bookmarks deleted</p>';

		if(count($errors)>0){
			print '<p color="#ff0000"><b>Errors</b></p><p>'.implode("<br>",$errors).'</p>';
		}

		print '<input type="button" value="close" onclick="top.location.reload(true);">';

	} else {
?>

<form method="post">
<?php
	$bib_ids = explode(',', $_REQUEST['ids']);
	if(count($bib_ids)>20){
?>
<div style="height:45px;">
This is a fairly slow process, taking several minutes per 1000 records, please be patient…
<input class="deleteButton" type="submit" style="color:red !important" value="delete" onClick="return confirm('ARE YOU SURE YOU WISH TO DELETE THE SELECTED RECORDS, ALONG WITH ALL ASSOCIATED BOOKMARKS?')  &&  confirm('REALLY REALLY SURE?');">
</div>
<?php
	}
	if (is_admin()) {
		print '<a style="float: right;" target=_new href=../../admin/verification/combineDuplicateRecords.php?bib_ids='.$_REQUEST['ids'].'>fix duplicates</a>';
	} else {
		print '<p style="color:#BB0000; font-size:12px; line-height:14px"><strong>NOTE:</strong>You may not delete records you did not create, or records that have been bookmarked by other users</p><div class="separator_row"></div>';
	}
	foreach ($bib_ids as $rec_id) {
		if (! $rec_id) continue;
		$res = mysql_query('select rec_Title,rec_AddedByUGrpID from Records where rec_ID = ' . $rec_id);
		$row = mysql_fetch_assoc($res);
		$rec_title = $row['rec_Title'];
		$owner = $row['rec_AddedByUGrpID'];
		$res = mysql_query('select '.USERS_USERNAME_FIELD.' from Records left join usrBookmarks on bkm_recID=rec_ID left join '.USERS_DATABASE.'.'.USERS_TABLE.' on '.USERS_ID_FIELD.'=bkm_UGrpID where rec_ID = ' . $rec_id);
		$bkmk_count = mysql_num_rows($res);
		$bkmk_users = array();
		while ($row = mysql_fetch_assoc($res)) array_push($bkmk_users, $row[USERS_USERNAME_FIELD]);
		$refs_res = mysql_query('select dtl_RecID from recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID where  dty_Type="resource and dtl_Value='.$rec_id.' "');
		$refs = mysql_num_rows($refs_res);

		$allowed = is_admin()  ||
				   ($owner == get_user_id()  &&
				   ($bkmk_count == 0  ||
				   ($bkmk_count == 1  &&  $bkmk_users[0] == get_user_username())));

		print "<div".(! $allowed ? ' class=greyed' : '').">";
		print ' <p><input type="checkbox" name="bib[]" value="'.$rec_id.'"'.($bkmk_count <= 1  &&  $refs == 0  &&  $allowed ? ' checked' : '').(! $allowed ? ' disabled' : '').'>';
		print ' ' . $rec_id . '<a target=_new href="'.HEURIST_SITE_PATH.'records/edit/editRecord.html?recID='.$rec_id.'"><img src='.HEURIST_SITE_PATH.'common/images/external_link_16x16.gif></a>';
		print ' ' . $rec_title ."</p>";

		print ' <p style="margin-left: 20px;"><b>' . $bkmk_count . '</b> bookmark' . ($bkmk_count == 1 ? '' : 's') . ($bkmk_count > 0 ? ':' : '') . "  ";
		print join(', ', $bkmk_users);
		print " </p>";

		if ($refs) {
			print ' <p style="margin-left: 20px;">Referenced by: ';
			while ($row = mysql_fetch_assoc($refs_res)) {
				print '  <a target=_new href="'.HEURIST_SITE_PATH.'records/edit/editRecord.html?recID='.$row['dtl_RecID'].'">'.$row['dtl_RecID'].'</a>';
			}
			print "</p>";
		}

		print "<div class='separator_row'></div></div>";
	}
?>

<input type="hidden" name="delete" value="1">
<div style="padding-top:5px;">
<?=(count($bib_ids)>20)?"This is a fairly slow process, taking several minutes per 1000 records, please be patient…":""?>
<input class="deleteButton" type="submit" style="color:red !important" value="delete" onClick="return confirm('ARE YOU SURE YOU WISH TO DELETE THE SELECTED RECORDS, ALONG WITH ALL ASSOCIATED BOOKMARKS?')  &&  confirm('REALLY REALLY SURE?');">
</div>

</form>

<?php
	}
?>

</body>
</html>

