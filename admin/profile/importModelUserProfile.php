<?php

/*
* Copyright (C) 2005-2015 University of Sydney
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
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/

/* too complicated to do with t-1000 */

define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/t1000/.ht_stdefs');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
	return;
}

if (is_modeluser()) {
//	header('Location: ' . HEURIST_BASE_URL . 'legacy/popup_usercopy.php');	//FIXME: no popup_usercopy.php
	return;
}

define('MODEL_USER_ID', ($_REQUEST['model_user_id'] ? $_REQUEST['model_user_id'] : 96));

mysql_connection_overwrite(DATABASE);

$updated = 0;
if (@$_REQUEST['submit']) $updated = update_my_settings();

?>

<html>
    <head>
     <title>Update settings from model user</title>
     <meta http-equiv="content-type" content="text/html; charset=utf-8">
     <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/global.css">
    <style type="text/css">
    <!--
    table.normal {margin:10px 0}
    -->
    </style>
    </head>

    <body class="popup" width=600 height=500 style="font-size: 11px;">
        <table border="0" cellpadding="3" cellspacing="0" width="100%" class="normal">
            <tr>
                <td align="left"><h2>Update my settings</h2></td>
                <td align="right">&nbsp;</td>
            </tr>

            <tr>
                <td><p>Listed below are tags, URLs and saved searches which you may wish to add to your profile.<br></p></td>
            </tr>

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
                    <select name="model_user_id" onChange="form.submit();">
                        <?php
	                        $res = mysql_query("select usr.ugr_ID, concat(usr.ugr_FirstName,' ',usr.ugr_LastName) as realname from ".USERS_DATABASE.".sysUGrps usr where usr.ugr_IsModelUser=1");
	                        while ($row = mysql_fetch_assoc($res)) { ?>
                          <option value="<?=$row['ugr_ID']?>"<?=($row['ugr_ID']==MODEL_USER_ID ? ' selected' : '')?>><?=$row['realname']?></option>
                        <?php	} ?>
                    </select>
               </form>
               </td>
          </tr>

          <tr>
            <td colspan="2">
        <form method="post">
            <input type="hidden" name="submit" value="1">

            <div class="separator_row" style="margin-top:20px"></div>

            <table border="0" class="normal" style="text-align: left;">
                <?php
	                $res = tag_query();
	                if (mysql_num_rows($res)) {
                ?>
                <tr><td colspan="3" style="font-weight: bold;" id="tag_section">Tags</td></tr>
                <?php
	                } else {
                ?>
                <tr><td colspan="3" id="tag_section">(no new tags)</td></tr>
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
                   <td style="width: 16px;"><input type="checkbox" name="tag[<?= $row['tag_ID'] ?>]" value="1" checked class="tag"></td>
                   <td><?= htmlspecialchars($row['tag_Text']) ?></td>
                  </tr>
                <?php
	                }
	                if (mysql_num_rows($res)) {
                ?>
                  <tr><td colspan="3">
                    <span class="small">
                        <input type="button" value="Select all" onClick="for (i in form.elements) if (form.elements[i].className=='tag') form.elements[i].checked=true;">
                        <input type="button" value="Select none" onClick="for (i in form.elements) if (form.elements[i].className=='tag') form.elements[i].checked=false;">
	                </span>
                  </td></tr>
                <?php
	                }
                ?>
            </table>

            <div class="separator_row"></div>

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
                   <td><a href="<?= $row['rec_URL'] ?>" target="_testwindow"><?= htmlspecialchars($row['rec_Title']) ?></a></td>
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

            <div class="separator_row"></div>

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
                    <td style="width: 16px;"><input type="checkbox" name="ssearch[<?= $row['svs_ID'] ?>]" value="1" checked class="ssearch"></td>
                    <td><a href="<?= $row['ss_url'] ?>" target="_testwindow"><?= htmlspecialchars($row['svs_Name']) ?></a></td>
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

            <div class="separator_row"></div>

            <table border="0" cellpadding="3" cellspacing="0" width="100%">
                <tr>
                    <td align="right"><span class="small"><input type="button" value="Add selected items" style="font-weight: bold;"></span>&nbsp;&nbsp;</td>
                </tr>
            </table>
        </form>
    </body>
</html>
<?php
/* ----- END OF OUTPUT ----- */

function update_my_settings() {
	$updated = 0;

	$keys = array_map('intval', array_keys($_REQUEST['tag']));
	$bkmks = array_map('intval', array_keys($_REQUEST['bkmk']));
	$ssearches = array_map('intval', array_keys($_REQUEST['ssearch']));

	$keys = mysql__select_array('usrTags', 'tag_ID', 'tag_UGrpID= '.MODEL_USER_ID.' and tag_ID in (0, ' . join(', ', $keys) . ')');	//saw CHECK: is 0 ok for all of these
	$bkmks = mysql__select_array('usrBookmarks', 'bkm_ID', 'bkm_UGrpID = '.MODEL_USER_ID.' and bkm_ID in (0, ' . join(', ', $bkmks) . ')');
	$ssearches = mysql__select_array('usrSavedSearches', 'svs_ID', 'svs_UGrpID = '.MODEL_USER_ID.' and svs_ID in (0, ' . join(', ', $ssearches) . ')');

	if ($keys) {
		$res = mysql_query('select tag_Text from usrTags where tag_ID in ('.join(',',$keys).')');
		$values = '';
		while ($row = mysql_fetch_row($res)) {
			if ($values) $values .= ', ';
			$values .= '("'.mysql_real_escape_string($row[0]).'",'.get_user_id().')';
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

			mysql__insert('usrBookmarks', $row);	//saw CHECK: for case where user already has bookmarks.
			$updated = 1;
		}

		/* for each of the model user's usrRecTagLinks entries, make a corresponding entry for the new user */
		/* hold onto your hats, folks: this is a five-table join across three tables! */
		$res = mysql_query(
'select NEWUSER_KWD.tag_ID, MODUSER_KWDL.rtl_Order, MODUSER_KWDL.rtl_RecID
   from usrBookmarks NEWUSER_BKMK left join usrBookmarks MODUSER_BKMK on NEWUSER_BKMK.bkm_recID=MODUSER_BKMK.bkm_recID
                                                               and MODUSER_BKMK.bkm_ID in ('.join(',',$bkmks).')
                               left join usrRecTagLinks MODUSER_KWDL on MODUSER_KWDL.rtl_RecID=MODUSER_BKMK.bkm_RecID
                               left join usrTags MODUSER_KWD on MODUSER_KWD.tag_ID=MODUSER_KWDL.rtl_TagID
                               left join usrTags NEWUSER_KWD on NEWUSER_KWD.tag_Text=MODUSER_KWD.tag_Text
                                                             and NEWUSER_KWD.tag_UGrpID='.get_user_id().'
  where NEWUSER_BKMK.bkm_UGrpID='.get_user_id().' and NEWUSER_KWD.tag_ID is not null'
		);
		$insert_pairs = array();
		while ($row = mysql_fetch_row($res))
			array_push($insert_pairs, '(' . intval($row[0]) . ',' . intval($row[1]) . ',' . intval($row[2]) . ')');
		if ($insert_pairs)
			mysql_query('insert into usrRecTagLinks ( rtl_TagID, rtl_Order, rtl_RecID) values ' . join(',', $insert_pairs));

	}

	if ($ssearches) {
		$res = mysql_query('select * from usrSavedSearches where svs_ID in ('.join(',',$ssearches).')');
		while ($row = mysql_fetch_assoc($res)) {
			// add a new custombookmark for each of the selected saved-searches
			// (all fields the same except for user id)

			unset($row['svs_ID']);

			$row['svs_UGrpID'] = get_user_id();
			$row['svs_Added'] = date('Y-m-d H:i:s');
			$row['svs_Modified'] = date('Y-m-d H:i:s');

			mysql__insert('usrSavedSearches', $row);
			$updated = 1;
		}
	}

	return $updated;
}


function tag_query() {	// get all model user tags that are not used by the user.
	return mysql_query("select A.tag_ID as tag_ID, A.tag_Text as tag_Text from usrTags A
	                           left join usrTags B on A.tag_Text=B.tag_Text and B.tag_UGrpID=".get_user_id()."
	                     where A.tag_UGrpID= ".MODEL_USER_ID." and B.tag_ID is null");
}

function bkmk_query() {	// get all model user bookmarks on records that are not bookmarked by the user.
	return mysql_query("select A.bkm_ID, rec_URL, rec_Title from usrBookmarks A
	                           left join Records on rec_ID = A.bkm_recID
	                           left join usrBookmarks B on A.bkm_recID = B.bkm_recID and B.bkm_UGrpID=".get_user_id()."
	                     where A.bkm_UGrpID=".MODEL_USER_ID." and B.bkm_ID is null
	                     order by A.bkm_ID");
}

function saved_search_query() {	// get all model user saved searches that are not used by the user.
	return mysql_query("select A.svs_ID, A.svs_Name, A.ss_url from usrSavedSearches A
	                           left join usrSavedSearches B on A.ss_url = B.ss_url and B.svs_UGrpID=".get_user_id()."
	                     where A.svs_UGrpID=".MODEL_USER_ID." and B.svs_ID is null
	                     order by A.svs_ID");
}
