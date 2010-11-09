<?php
/* too complicated to do with t-1000 */

define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../common/connect/db.php');
require_once(dirname(__FILE__).'/../common/connect/cred.php');
require_once(dirname(__FILE__).'/../common/t1000/.ht_stdefs');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
	return;
}

if (is_modeluser()) {
//	header('Location: ' . BASE_PATH . 'legacy/popup_usercopy.php');	//FIXME: no popup_usercopy.php
	return;
}

define('MODEL_USER_ID', ($_REQUEST['model_user_id'] ? $_REQUEST['model_user_id'] : 96));

mysql_connection_db_overwrite(DATABASE);

$updated = 0;
if (@$_REQUEST['submit']) $updated = update_my_settings();

?>
<html>
<head>
 <title>Update settings from model user</title>
 <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/import.css">

</head>

<body width=600 height=500 style="font-size: 11px;">

<table border="0" cellpadding="3" cellspacing="0" width="100%" class="normal">
  <tr>
   <td align="left"><h2>Update my settings</h2></td>
   <td align="right">&nbsp;</td>
  </tr>

  <tr><td>
    <p>Listed below are tags, URLs and saved searches which you may wish to add to your profile.<br></p>
  </td></tr>

<?php	if ($updated) { ?>
  <tr>
   <td colspan="2">
    <span class="normalgr"><b>Settings have been updated</b></span>
   </td>
  </tr>
<?php	} ?>

  <tr>
  <td>
   <form method="get">
    Show new data for:
    <select name="model_user_id" onchange="form.submit();">
<?php
	$res = mysql_query("select Id, concat(firstname,' ',lastname) as realname from ".USERS_DATABASE.".Users where IsModelUser=1");
	while ($row = mysql_fetch_assoc($res)) { ?>
  <option value="<?=$row['Id']?>"<?=($row['Id']==MODEL_USER_ID ? ' selected' : '')?>><?=$row['realname']?></option>
<?php	} ?>
    </select>
   </form>
   </td>
  </tr>

  <tr>
   <td colspan="2">
<form method="post">
<input type="hidden" name="submit" value="1">

<table border="0" class="normal" style="text-align: left;">
<?php
	$res = kwd_query();
	if (mysql_num_rows($res)) {
?>
<tr><td colspan="3" style="font-weight: bold;" id="keyword_section">Tags</td></tr>
<?php
	} else {
?>
<tr><td colspan="3" id="keyword_section">(no new tags)</td></tr>
<style type="text/css">
<!--
.keyword_link { display: none; }
-->
</style>
<?php
	}
	while ($row = mysql_fetch_assoc($res)) {
?>
  <tr>
   <td style="width: 16px;">&nbsp;</td>
   <td style="width: 16px;"><input type="checkbox" name="kwd[<?= $row['tag_ID'] ?>]" value="1" checked class="kwd"></td>
   <td><?= htmlspecialchars($row['tag_Text']) ?></td>
  </tr>
<?php
	}
	if (mysql_num_rows($res)) {
?>
  <tr><td colspan="3">
    <span class="small">
    <input type="button" value="Select all" onClick="for (i in form.elements) if (form.elements[i].className=='kwd') form.elements[i].checked=true;">
    <input type="button" value="Select none" onClick="for (i in form.elements) if (form.elements[i].className=='kwd') form.elements[i].checked=false;">
	</span>
  </td></tr>
<?php
	}
?>
</table>
<hr size="1px">
<table border="0" class="normal" style="text-align: left;">
<?php

	$res = bkmk_query();
	if (mysql_num_rows($res)) {
?>
<tr><td colspan="3" style="font-weight: bold;" id="urls_section">URLs</td></tr>
<?php
	} else {
?>
<tr><td colspan="3" id="urls_section">(no new URLs)</td></tr>
<style type="text/css">
<!--
.urls_link { display: none; }
-->
</style>
<?php
	}
	while ($row = mysql_fetch_assoc($res)) {
?>
  <tr>
   <td style="width: 16px;">&nbsp;</td>
   <td style="width: 16px;"><input type="checkbox" name="bkmk[<?= $row['bkm_ID'] ?>]" value="1" checked class="bkmk"></td>
   <td><a href="<?= $row['rec_url'] ?>" target="_testwindow"><?= htmlspecialchars($row['rec_title']) ?></a></td>
  </tr>
<?php
	}
	if (mysql_num_rows($res)) {
?>
  <tr><td colspan="3">
    <span class="small">
    <input type="button" value="Select all" onClick="for (i in form.elements) if (form.elements[i].className=='bkmk') form.elements[i].checked=true;">
    <input type="button" value="Select none" onClick="for (i in form.elements) if (form.elements[i].className=='bkmk') form.elements[i].checked=false;">
	</span>
  </td></tr>
<?php
	}
?>
</table>


<hr size="1px">
<table border="0" class="normal" style="text-align: left;">
<?php

	$res = saved_search_query();
	if (mysql_num_rows($res)) {
?>
<tr><td colspan="3" style="font-weight: bold;" id="ssearch_section">Saved searches</td></tr>
<?php
	} else {
?>
<tr><td colspan="3" id="urls_section">(no new saved searches)</td></tr>
<style type="text/css">
<!--
.ssearch_link { display: none; }
-->
</style>
<?php
	}
	while ($row = mysql_fetch_assoc($res)) {
?>
  <tr>
   <td style="width: 16px;">&nbsp;</td>
   <td style="width: 16px;"><input type="checkbox" name="ssearch[<?= $row['ss_id'] ?>]" value="1" checked class="ssearch"></td>
   <td><a href="<?= $row['ss_url'] ?>" target="_testwindow"><?= htmlspecialchars($row['ss_name']) ?></a></td>
  </tr>
<?php
	}
	if (mysql_num_rows($res)) {
?>
  <tr><td colspan="3">
    <span class="small">
    <input type="button" value="Select all" onClick="for (i in form.elements) if (form.elements[i].className=='ssearch') form.elements[i].checked=true;">
    <input type="button" value="Select none" onClick="for (i in form.elements) if (form.elements[i].className=='ssearch') form.elements[i].checked=false;">
	</span>
  </td></tr>
<?php
	}
?>
</table>

  </td>
  </tr>
</table>

<table border="0" cellpadding="3" cellspacing="0" width="100%">
  <tr>
    <td align="right"><span class="small"><input type="submit" value="Add selected items" style="font-weight: bold;"></span>&nbsp;&nbsp;</td>
  </tr>
</table>
</form>

</body>
</html>
<?php
/* ----- END OF OUTPUT ----- */

function update_my_settings() {
	$updated = 0;

	$keys = array_map('intval', array_keys($_REQUEST['kwd']));
	$bkmks = array_map('intval', array_keys($_REQUEST['bkmk']));
	$ssearches = array_map('intval', array_keys($_REQUEST['ssearch']));

	$keys = mysql__select_array('usrTags', 'tag_ID', 'tag_UGrpID= '.MODEL_USER_ID.' and tag_ID in (0, ' . join(', ', $keys) . ')');
	$bkmks = mysql__select_array('usrBookmarks', 'bkm_ID', 'bkm_UGrpID = '.MODEL_USER_ID.' and bkm_ID in (0, ' . join(', ', $bkmks) . ')');
	$ssearches = mysql__select_array('saved_searches', 'ss_id', 'ss_usr_id = '.MODEL_USER_ID.' and ss_id in (0, ' . join(', ', $ssearches) . ')');

	if ($keys) {
		$res = mysql_query('select tag_Text from usrTags where tag_ID in ('.join(',',$keys).')');
		$values = '';
		while ($row = mysql_fetch_row($res)) {
			if ($values) $values .= ', ';
			$values .= '("'.addslashes($row[0]).'",'.get_user_id().')';
		}

		if ($values) {
			mysql_query("insert into usrTags (tag_Text, tag_UGrpID) values $values");
			$updated = 1;
		}
	}

	if ($bkmks) {
		$res = mysql_query('select * from usrBookmarks where bkm_ID in ('.join(',',$bkmks).')');
		while ($row = mysql_fetch_assoc($res)) {
			// add a new bookmark for each of the selected usrBookmarks
			// (all fields the same except for user id)

			unset($row['bkm_ID']);

			$row['bkm_UGrpID'] = get_user_id();
			$row['bkm_Added'] = date('Y-m-d H:i:s');
			$row['bkm_Modified'] = date('Y-m-d H:i:s');

			mysql__insert('usrBookmarks', $row);
			$updated = 1;
		}

		/* for each of the model user's keyword_links entries, make a corresponding entry for the new user */
		/* hold onto your hats, folks: this is a five-table join across three tables! */
		$res = mysql_query(
'select NEWUSER_BKMK.bkm_ID, NEWUSER_KWD.tag_ID, MODUSER_KWDL.kwl_order, MODUSER_KWDL.kwl_rec_id
   from usrBookmarks NEWUSER_BKMK left join usrBookmarks MODUSER_BKMK on NEWUSER_BKMK.bkm_recID=MODUSER_BKMK.bkm_recID
                                                               and MODUSER_BKMK.bkm_ID in ('.join(',',$bkmks).')
                               left join keyword_links MODUSER_KWDL on MODUSER_KWDL.kwl_pers_id=MODUSER_BKMK.bkm_ID
                               left join usrTags MODUSER_KWD on MODUSER_KWD.tag_ID=MODUSER_KWDL.kwl_kwd_id
                               left join usrTags NEWUSER_KWD on NEWUSER_KWD.tag_Text=MODUSER_KWD.tag_Text
                                                             and NEWUSER_KWD.tag_UGrpID='.get_user_id().'
  where NEWUSER_BKMK.bkm_UGrpID='.get_user_id().' and NEWUSER_KWD.tag_ID is not null'
		);
		$insert_pairs = array();
		while ($row = mysql_fetch_row($res))
			array_push($insert_pairs, '(' . intval($row[0]) . ',' . intval($row[1]) . ',' . intval($row[2]) . ',' . intval($row[3]) . ')');
		if ($insert_pairs)
			mysql_query('insert into keyword_links (kwl_pers_id, kwl_kwd_id, kwl_order, kwl_rec_id) values ' . join(',', $insert_pairs));

	}

	if ($ssearches) {
		$res = mysql_query('select * from saved_searches where ss_id in ('.join(',',$ssearches).')');
		while ($row = mysql_fetch_assoc($res)) {
			// add a new custombookmark for each of the selected saved-searches
			// (all fields the same except for user id)

			unset($row['ss_id']);

			$row['ss_usr_id'] = get_user_id();
			$row['ss_added'] = date('Y-m-d H:i:s');
			$row['ss_modified'] = date('Y-m-d H:i:s');

			mysql__insert('saved_searches', $row);
			$updated = 1;
		}
	}

	return $updated;
}


function kwd_query() {
	return mysql_query("select A.tag_ID as tag_ID, A.tag_Text as tag_Text from usrTags A
	                           left join usrTags B on A.tag_Text=B.tag_Text and B.tag_UGrpID=".get_user_id()."
	                     where A.tag_UGrpID= ".MODEL_USER_ID." and B.tag_ID is null");
}

function bkmk_query() {
	return mysql_query("select A.bkm_ID, rec_url, rec_title from usrBookmarks A
	                           left join records on rec_id = A.bkm_recID
	                           left join usrBookmarks B on A.bkm_recID = B.bkm_recID and B.bkm_UGrpID=".get_user_id()."
	                     where A.bkm_UGrpID=".MODEL_USER_ID." and B.bkm_ID is null
	                     order by A.bkm_ID");
}

function saved_search_query() {
	return mysql_query("select A.ss_id, A.ss_name, A.ss_url from saved_searches A
	                           left join saved_searches B on A.ss_url = B.ss_url and B.ss_usr_id=".get_user_id()."
	                     where A.ss_usr_id=".MODEL_USER_ID." and B.ss_id is null
	                     order by A.ss_id");
}
