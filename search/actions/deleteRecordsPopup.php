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
* brief description of file
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
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/


define('SAVE_URI', 'disabled');

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
//require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../records/edit/deleteRecordInfo.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');

if (! is_logged_in()) {
	header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php');
	return;
}
mysql_connection_overwrite(DATABASE);
?>
<html>
<head>
  <title>HEURIST: Delete records</title>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/global.css">
  <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>

  <style type=text/css>
  span { font-weight:bold; }
   a img { border: none; }
   .greyed, .greyed div { color: gray; }
		p {line-height:11px;margin:6px 0}
		.deleteButton {color: #BB0000;float: right;font-weight: bold;height: 20px;margin: 10px 0;text-transform: uppercase; width: 60px;}
  </style>
  
    <script type="text/javascript">
            
            function deleteSubmit(){
                var hWin = window.hWin?window.hWin:top.hWin;
                
                if(hWin && hWin.HEURIST4.msg){
                    
                    hWin.HEURIST4.msg.showMsgDlg('<span class="ui-icon ui-icon-alert" style="display:inline-block">&nbsp;</span>&nbsp;'+
                    'Please confirm that you really wish to delete the selected records, <br/>along with all associated bookmarks?', function(){
                        document.forms[0].submit();
                        $(document.forms[0]).hide();
                    },'Confirm')
                    
                    
                }else{
                   if(confirm('Are you sure you wish to delete the selected records, along with all associated bookmarks?')){
                        document.forms[0].submit();   
                   }
                }
            }
    
            function onDocumentReady(){
                //resize parent dialog
                setTimeout(function(){

                    var body = document.body,
                        html = document.documentElement;

                    var desiredHeight = Math.max( 250, body.scrollHeight, body.offsetHeight, 
                                           html.clientHeight, html.scrollHeight, html.offsetHeight )+50;                    
                                                              
                    if(typeof doDialogResize != 'undefined' && doDialogResize.call && doDialogResize.apply) {
                        doDialogResize(0, desiredHeight);              
                    }
                }, 500);
            }
    </script>
  
</head>

<body class="popup" width=450 height=350 onload="onDocumentReady()">
<?php

	if(!@$_REQUEST['ids'] && @$_REQUEST['delete']!=1){

	    print '<form method="post"><input id="ids" name="ids" type="hidden" /></form>';

	}else if (@$_REQUEST['delete'] == 1) { //ACTION!!!!

		$recs_count = 0;
		$bkmk_count = 0;
		$rels_count = 0;
		$errors = array();

        if(is_array(@$_REQUEST['bib'])){
		    $total_cnt = count($_REQUEST['bib']);
        }else{
            $total_cnt = 0;
        }
		$processed_count = 0;
?>
<script type="text/javascript">
function update_counts(processed, deleted, relations, bookmarks, errors)
{
	document.getElementById('processed_count').innerHTML = processed;
	document.getElementById('deleted').innerHTML = deleted;
	document.getElementById('relations').innerHTML = relations;
	document.getElementById('bookmarks').innerHTML = bookmarks;
	document.getElementById('errors').innerHTML = errors;
<?php 
    echo "document.getElementById('percent').innerHTML = Math.round(1000 * processed / ".$total_cnt." ) / 10;";
?>
}
</script>
<?php

print '<div><span id=total_count>'.$total_cnt.'</span> records in total to be deleted</div>';
print '<div><span id=processed_count>0</span> processed so far  <span id=percent>0</span>%</div>';
print '<div><span id=deleted>0</span> deleted</div>';
print '<div><span id=relations>0</span> relationships</div>';
print '<div><span id=bookmarks>0</span> associated bookmarks</div>';
print '<div><span id=errors>0</span> errors</div>';

        $needDeleteFile = (@$_REQUEST['delfile']=="1");

        if($total_cnt>0){
            
		    foreach ($_REQUEST['bib'] as $rec_id) {

			    mysql_query("start transaction");

			    $res = deleteRecord($rec_id, $needDeleteFile);
			    //$res = array("bkmk_count"=>0, "rel_count"=>0);

			    if( array_key_exists("error", $res) ){

				    mysql_query("rollback");

				    array_push($errors, "Rec#".$rec_id."  ".$res["error"]);

			    }else{
				    mysql_query("commit");

				    $recs_count++;
				    $bkmk_count += $res["bkmk_count"];
				    $rels_count += $res["rel_count"];
			    }

			    $processed_count++;

			    if ($rec_id % 10 == 0) {
				    print '<script type="text/javascript">update_counts('.$processed_count.','.$recs_count.','.$rels_count.','.$bkmk_count.','.count($errors).')</script>'."\n";
				    @ob_flush();
				    @flush();
			    }
		    }//for
            
        
        }   // $total_cnt>0

		print '<script type="text/javascript">update_counts('.$processed_count.','.$recs_count.','.$rels_count.','.$bkmk_count.','.count($errors).')</script>'."\n";

		if($recs_count>0){
			$rtUsage = updateRecTypeUsageCount();
			print '<script type="text/javascript">if(top.HEURIST.rectypes)top.HEURIST.rectypes.usageCount = '.json_format($rtUsage).';if(top.HEURIST.search){top.HEURIST.search.createUsedRectypeSelector(true);};</script>'."\n";
		}

		//print '<p><b>' . $recs_count . '</b> records, <b>' . $rels_count . '</b> relationships and <b>' . $bkmk_count . '</b> associated bookmarks deleted</p>';

		if(count($errors)>0){
			print '<p color="#ff0000"><b>Errors</b></p><p>'.implode("<br>",$errors).'</p>';
		}

		print '<br/><input type="button" value="close" onclick="window.close('.($recs_count>0?'\'reload\'':'').');">';

	} else {
?>
<script type="text/javascript">
var cnt_checked= 0;
function toggleSelection(ele){
	var bibs = document.getElementsByTagName("input");//  .getElementsByName("bib");
	var i;
	cnt_checked = 0;
	for (i=0; i<bibs.length; i++){
		if(bibs[i].name=="bib[]" && !bibs[i].disabled){
			bibs[i].checked = ele.checked;
			if(ele.checked) cnt_checked++;
		}
	}
	document.getElementById('spSelected').innerHTML = cnt_checked;
	document.getElementById('spSelected2').innerHTML = cnt_checked;
}
function onSelect(ele){
if(document.getElementById('spSelected')){
 if(ele.checked){
 	cnt_checked++;
 }else{
 	cnt_checked--;
 }
 document.getElementById('spSelected').innerHTML = cnt_checked;
 document.getElementById('spSelected2').innerHTML = cnt_checked;
}
}
</script>
<form method="post">
<!-- input type=text name="DBGSESSID" value="424657986609500001;d=1,p=0,c=0" -->
<div>
    <input type="checkbox" name="delfile" id="delfile" value="1" checked />&nbsp;&nbsp;<label for="delfile">Delete uploaded associated files</label>
</div>
<?php
	$bib_ids = explode(',', $_REQUEST['ids']);
    $cnt_bibs = count($bib_ids);
	if($cnt_bibs > 8){
?>
<div style="height:45px;">
This is a fairly slow process, taking several minutes per 1000 records, please be patient…
<input class="deleteButton" type="button" style="color:red !important" value="delete" onClick="deleteSubmit()">
</div>
<?php
	}
    
//We have to verify that records to be deleted have any back reference and show warning in this case
    if($cnt_bibs >0){
        $res = recordSearchRelated($bib_ids, true, -1);
        
        if(is_array($res) && count(@$res['reverse']['source'])>0){
      
            $cnt_target = count(@$res['reverse']['target']);
            $cnt_source = count(@$res['reverse']['source']);
            $merged_ids = array_unique(array_merge($res['reverse']['target'], $res['reverse']['source']), SORT_NUMERIC);
        
            if($cnt_bibs==1){
                $sMsg = 'This record is';
            }else if($cnt_target==1){
                $sMsg = 'One of '.$cnt_bibs.' selected records is';
            }else if($cnt_target<$cnt_bibs){
                $sMsg = $cnt_target.' records of '.$cnt_bibs.' selected records are';
            }else{
                $sMsg = 'These '.$cnt_bibs.' selected records are';
            }
            $sMsg = '<div style="color:red">'.$sMsg
            .' pointed to by '.$cnt_source.' other records. If you delete '.($cnt_target==1?'it':'them')
            .' you will leave '.(($cnt_source>1)?'these records':'this record').' with invalid data. '
            .'Invalid records can be found through the Manage > Structure > Verify function.</div>';

            print $sMsg;
            print '<div style="padding-bottom:10px">You may look at relationships '
            .'<a target=_new href="'.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME
            .'&tab=connections&q=ids:'.implode(',',$merged_ids).'">here.<img src='.HEURIST_BASE_URL.'common/images/external_link_16x16.gif></a></div>';
        
        }
    }
    
    
	if(count($bib_ids)>3){
?>
	<div>Total count of records: <b><?=count($bib_ids)?></b>.   Selected to be deleted <span id="spSelected">0</span>.<br/><br/><input id="cbToggle" checked type="checkbox" onclick="toggleSelection(this)" />&nbsp;<label for="cbToggle">Select All/None</label></div>
<?php
	}
	if (is_admin()) {
		print '<a style="float: right;" target=_new href=../../admin/verification/combineDuplicateRecords.php?bib_ids='.$_REQUEST['ids'].'>fix duplicates</a>';
	} else {
		print '<p style="color:#BB0000; font-size:12px; line-height:14px"><strong>NOTE:</strong>You may not delete records you did not create, or records that have been bookmarked by other users</p><div class="separator_row"></div>';
	}
	$cnt_checked = 0;
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

		$is_checked = ($bkmk_count <= 1  &&  $refs == 0  &&  $allowed);

		if($is_checked) $cnt_checked++;

		print "<div".(! $allowed ? ' class=greyed' : '').">";
		print ' <p><input type="checkbox" name="bib[]" value="'.$rec_id.'"'.($is_checked ? ' checked' : '').(! $allowed ? ' disabled' : ' onchange="onSelect(this)"').'>';
		print ' ' . $rec_id . '<a target=_new href="'.HEURIST_BASE_URL.'records/edit/editRecord.html?db='.HEURIST_DBNAME.'&recID='.$rec_id.'"><img src='.HEURIST_BASE_URL.'common/images/external_link_16x16.gif></a>';
		print ' ' . $rec_title ."</p>";

		print ' <p style="margin-left: 20px;"><b>' . $bkmk_count . '</b> bookmark' . ($bkmk_count == 1 ? '' : 's') . ($bkmk_count > 0 ? ':' : '') . "  ";
		print join(', ', $bkmk_users);
		print " </p>";

		if ($refs) {
			print ' <p style="margin-left: 20px;">Referenced by: ';
			while ($row = mysql_fetch_assoc($refs_res)) {
				print '  <a target=_new href="'.HEURIST_BASE_URL.'records/edit/editRecord.html?db='.HEURIST_DBNAME.'&recID='.$row['dtl_RecID'].'">'.$row['dtl_RecID'].'</a>';
			}
			print "</p>";
		}

		print "<div class='separator_row'></div></div>";
	}
?>
<input type="hidden" name="delete" value="1">
<div style="padding-top:5px;">
<?php echo (count($bib_ids)>20)?"This is a fairly slow process, taking several minutes per 1000 records, please be patient…":""; ?>
<input class="deleteButton" type="button" style="color:red !important" value="delete" onClick="deleteSubmit()">
</div>
</form>
<?php
	if(count($bib_ids)>3){
?>
	<div>Total count of records: <b><?php echo count($bib_ids);?></b>.   Selected to be deleted <span id="spSelected2">0</span></div>
<script>
<?php
  echo "cnt_checked = $cnt_checked ;";                                  
?> 
 document.getElementById('spSelected').innerHTML = "<?=$cnt_checked?>";
 document.getElementById('spSelected2').innerHTML = "<?=$cnt_checked?>";
</script>
<?php
	}
	}
?>

</body>
</html>