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


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/utilsTitleMask.php');
require_once(dirname(__FILE__).'/../../admin/verification/fetchRecordDetails.php');

if (!is_admin()) return;

session_start();

$do_merge_details = false;

if (@$_REQUEST['keep']  &&  @$_REQUEST['duplicate']){  //user has select master and dups- time to merge details
    $do_merge_details = true;
    $_REQUEST['bib_ids'] = join(',',array_merge($_REQUEST['duplicate'],array($_REQUEST['keep']))); //copy only the selected items

}elseif(@$_REQUEST['commit']){
    do_fix_dupe();
    return;
}

$finished_merge = false;
if (@$_SESSION['finished_merge']){
    unset($_SESSION['finished_merge']);
    $finished_merge = true;
}

if (@$_REQUEST['keep'])  $_SESSION['master_rec_id'] = $master_rec_id = $_REQUEST['keep']; //store the master record id in the session

if (! @$_REQUEST['bib_ids']) return;

mysql_connection_select(DATABASE);
//mysql_connection_select("`heuristdb-nyirti`");   //for debug

$bdts = mysql__select_assoc('defDetailTypes', 'dty_ID', 'dty_Name', '1');
$reference_bdts = mysql__select_assoc('defDetailTypes', 'dty_ID', 'dty_Name', 'dty_Type="resource"');
$enum_bdts = mysql__select_assoc('defDetailTypes', 'dty_ID', 'dty_Name', '(dty_Type="relationtype") OR (dty_Type="enum")');

?>

<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <style type="text/css">
            * { font-size: 100%; font-family: verdana;}
            body { font-size: 0.7em; }
            td { vertical-align: top; }
        </style>

        <script type="text/javascript">
            <!--
            function keep_bib(rec_id) {
                e = document.getElementById('tb');
                if (!e) return;
                for (var i = 0; i < e.childNodes.length; ++i) {
                    row = e.childNodes[i];
                    if (row.nodeName == "TR" && row.id){
                        id = row.id.replace(/row/,'');
                        d = document.getElementById('duplicate' + id);
                        if (id == rec_id){
                            row.style.backgroundColor = '#EEE';
                            if(d){
                                d.style.display = "none";
                                d.nextSibling.style.display = "none";
                            }
                        }else{
                            if (d) {
                                d.style.display = "block";
                                d.nextSibling.style.display = "block";
                            }
                            if (d && d.checked){
                                row.style.backgroundColor = '#EEE';
                            }else{
                                row.style.backgroundColor = '';
                            }
                        }
                    }
                }
                e = document.getElementById('keep'+rec_id);
                if (e) e.checked = true;
                e = document.getElementById('duplicate'+rec_id);
                if (e) e.checked = false;
            }
            function delete_bib(rec_id) {
                e = document.getElementById('row'+rec_id);
                if (e) e.style.backgroundColor = '#EEE';
            }
            function undelete_bib(rec_id) {
                e = document.getElementById('row'+rec_id);
                if (e) e.style.backgroundColor = '';
            }
            -->
        </script>
        <link rel=stylesheet href="../../common/css/global.css" media="all">
        <link rel=stylesheet href="../../common/css/admin.css" media="all">
        <title>Merging records</title>
    </head>

    <body class="popup" width="600" height="400">
        <div style="max-width:600px;">
            <form>
                <div>
                    <?php
                    if (! @$do_merge_details){
                        print 'This function combines duplicate records. One record MUST be selected as a master record'.
                        ' and there must be at least one duplicate selected. Processing duplicates allows you to merge,'.
                        'data with the master record.<br/><br/>'.
                        'Bookmarks, Tags and Relationships from deleted records are added to the master record.<br/><br/>'.
                        'None of these data are duplicated if they already exist in the master record.';
                    } else{
                        print 'Select the data items which should be retained, added or replaced in the master records.'.
                        ' Repeatable (multi-valued)fields are indicated by checkboxes and single value fields are '.
                        ' indicated by radio buttons. Pressing the "commit changes" button will start the process to'.
                        ' save the changes to the master record. You will be able to view the master record to verify '.
                        'the changes.';
                    }
                    ?>
                </div><br/><hr/>

                <table><tbody id="tb">
                        <?php
                        print '<input type="hidden" name="bib_ids" value="'.$_REQUEST['bib_ids'].'">';

                        $rtyNameLookup = array();
                        $res = mysql_query('select rty_ID, rty_Name from Records left join defRecTypes on rty_ID=rec_RecTypeID where rec_ID in ('.$_REQUEST['bib_ids'].')');
                        //TODO: print cross-type matching header " Cross Type : Author Editor with Person with Book"
                        while ($row = mysql_fetch_assoc($res)) $rtyNameLookup[$row['rty_ID']]= $row['rty_Name'];

                        $temptypes = '';
                        if (count($rtyNameLookup) > 0) {
                            foreach ($rtyNameLookup as $rtyID => $rtyName){
                                if (!$temptypes) {
                                    $temptypes = $rtyName;
                                    $firstRtyID = $rtyID;
                                }else{
                                    $temptypes .= '/'.$rtyName;
                                }
                            }
                            print '<tr><td colspan="3" style="text-align: center; font-weight: bold;">'.$temptypes.'</td></tr>';
                        }
                        //save rec type for merging code
                        if (!@$_SESSION['rty_ID']) $_SESSION['rty_ID'] = @$firstRtyID;
                        //get requirements for details
                        $res = mysql_query('select rst_RecTypeID,rst_DetailTypeID, rst_DisplayName, rst_RequirementType, rst_MaxValues from defRecStructure where rst_RecTypeID in ('.join(',',array_keys($rtyNameLookup)).')');
                        $rec_requirements =  array();

                        while ($req = mysql_fetch_assoc($res)) $rec_requirements[$req['rst_RecTypeID']][$req['rst_DetailTypeID']]= $req;

                        $query2 = 'select * from Records where rec_ID in ('.$_REQUEST['bib_ids'].') order by find_in_set(rec_ID, "'.$_REQUEST['bib_ids'].'")';
                        $res = mysql_query($query2);
                        $records = array();
                        $counts = array();
                        $rec_references = array();
                        $invalid_rec_references = array();
                        while ($rec = mysql_fetch_assoc($res)) $records[$rec['rec_ID']] = $rec;

                        foreach($records as $index => $record){
                            $rec_references = mysql__select_array('recDetails', 'dtl_RecID', 'dtl_Value='.$records[$index]['rec_ID'].' and dtl_DetailTypeID in ('.join(',', array_keys($reference_bdts)).')');
                            if ($rec_references){
                                // only store the references that are actually records
                                $records[$index]["refs"] =  mysql__select_array('Records', 'rec_ID', 'rec_ID in ('.join(',', $rec_references).')');
                                $records[$index]["ref_count"] = count($records[$index]["refs"]);
                                $counts[$index] = $records[$index]["ref_count"];
                                $invalid_rec_references += array_diff($rec_references,$records[$index]["refs"]);
                            } else{
                                array_push($counts,0);
                            }
                            $details = array();
                            $res = mysql_query('select dtl_DetailTypeID, dtl_Value, dtl_ID, dtl_UploadedFileID, if(dtl_Geo is not null, AsWKT(dtl_Geo), null) as dtl_Geo, trm_Label
                                from recDetails  left join defTerms on trm_ID = dtl_Value
                                where dtl_RecID = ' . $records[$index]['rec_ID'] . '
                            order by dtl_DetailTypeID, dtl_ID');
                            $records[$index]['details'] = array();
                            while ($row = mysql_fetch_assoc($res)) {

                                $type = $row['dtl_DetailTypeID'];

                                if (! array_key_exists($type, $records[$index]['details'])) {
                                    $records[$index]['details'][$type] = array();
                                }
                                
                                if(!in_array($type, array_keys($enum_bdts))){
                                       $row['trm_Label'] = null;
                                }
                                
                                array_push($records[$index]['details'][$type], $row);
                            }
                        }
                        //FIXME place results into array and output record with most references and/or date rule first - not sure what to do here
                        $rec_keys = array_keys($records);
                        if (! @$master_rec_id){
                            array_multisort($counts,SORT_NUMERIC, SORT_DESC, $rec_keys );
                            $master_rec_id = $rec_keys[0];
                        }
                        if (! @$do_merge_details){  // display a page to user for selecting which record should be the master record
                            //    foreach($records as $index) {
                            foreach($records as $record) {
                                //  $record = $records[$index];
                                $is_master = ($record['rec_ID']== $master_rec_id);
                                print '<tr'. ($is_master && !$finished_merge ? ' style="background-color: #EEE;" ': '').' id="row'.$record['rec_ID'].'">';
                                $checkKeep =  $is_master? "checked" : "";
                                $disableDup = $is_master? "none" : "block";
                                if (!$finished_merge) print '<td><input type="checkbox" name="duplicate[]" '.
                                    ' value="'.$record['rec_ID'].
                                    '" title="Check to mark this as a duplicate record for deletion"'.
                                    '  id="duplicate'.$record['rec_ID'].'" style="display:'.$disableDup.
                                    '" onclick="if (this.checked) delete_bib('.$record['rec_ID'].'); else undelete_bib('.$record['rec_ID'].
                                    ');"><div style="font-size: 70%; display:'.$disableDup.';">DUPLICATE</div></td>';
                                print '<td style="width: 500px;">';
                                if (!$finished_merge) print '<input type="radio" name="keep" '.$checkKeep.
                                    ' value="'.$record['rec_ID'].
                                    '" title="Click to select this record as the Master record"'.
                                    ' id="keep'.$record['rec_ID'].
                                    '" onclick="keep_bib('.$record['rec_ID'].');">';
                                print '<span style="font-size: 120%;"><a target="edit" href="'.HEURIST_BASE_URL.'?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$record['rec_ID'].'">'.$record['rec_ID'] . ' ' . '<b>'.$record['rec_Title'].'</b></a> - <span style="background-color: #EEE;">'. $rtyNameLookup[$record['rec_RecTypeID']].'</span></span>';
                                print '<table>';
                                foreach ($record['details'] as $rd_type => $detail) {
                                    if (! $detail) continue;    //FIXME  check if required and mark it as missing and required
                                    if(!@$rec_requirements[$record['rec_RecTypeID']][$rd_type]) continue;
                                    $reqmnt = $rec_requirements[$record['rec_RecTypeID']][$rd_type]['rst_RequirementType'];
                                    $color = ($reqmnt == 'required' ? 'red': ($reqmnt == 'recommended'? 'black':'grey'));
                                    print '<tr><td style=" color: '.$color .';">'.$bdts[$rd_type].'</td>';
                                    print '<td style="padding-left:10px;">';
                                    foreach($detail as $i => $rg){

                                        
                                        if ($rg['dtl_UploadedFileID']) {
                                            $rd_temp = mysql_fetch_array(mysql_query('select ulf_OrigFileName from recUploadedFiles where ulf_ID ='.$rg['dtl_UploadedFileID']));
                                            $rd_temp = $rd_temp[0];
                                        }else {
                                            
                                            if ($rg['dtl_Geo']) {
                                                $rd_temp = $rg['dtl_Geo'];
                                            }
                                            else if ($rg['trm_Label']){
                                                $rd_temp = $rg['trm_Label']." (".$rg['dtl_Value'].")";
                                                //$temp = $rg['dtl_Value']; //trm_ID
                                            }
                                            else {
                                                $rd_temp = $rg['dtl_Value'];
                                            }
                                                                                     
                                        }
                                        if(! @$temp) $temp=$rd_temp;
                                        elseif(!is_array($temp)){
                                            $temp = array($temp,$rd_temp);
                                        }else array_push($temp,$rd_temp);
                                    }
                                    $detail = detail_str($rd_type, $temp);
                                    unset($temp);
                                    if (is_array($detail)) {
                                        $repeatCount = intval($rec_requirements[$record['rec_RecTypeID']][$rd_type]['rst_MaxValues']);
                                        if ($repeatCount==0){
                                            foreach ($detail as $val) {
                                                print '<div>'. $val . '</div>';
                                            }
                                        } else{
                                            for ($i = 0; $i < $repeatCount; $i++) {
                                                print '<div>'. $detail[$i] . '</div>';
                                            }
                                            //FIXME  add code to remove the extra details that are not supposed to be there
                                        }
                                    } else{
                                        print '<div>'. $detail . '</div>';
                                    }

                                    print '</td>';
                                }

                                if ($record['rec_URL']) print '<tr><td>URL</td><td><a href="'.$record['rec_URL'].'">'.$record['rec_URL'].'</a></td></tr>';

                                if ($record['rec_Added']) print '<tr><td>Added</td><td style="padding-left:10px;">'.substr($record['rec_Added'], 0, 10).'</td></tr>';
                                if ($record['rec_Modified']) print '<tr><td>Modifed</td><td style="padding-left:10px;">'.substr($record['rec_Modified'], 0, 10).'</td></tr>';


                                print '</table></td><td>';

                                print '<table>';

                                if (array_key_exists("refs",$record)) {
                                    print '<tr><td>References</td><td>';
                                    $i = 1;
                                    foreach ($record["refs"] as $ref) {  //FIXME  check for reference to be a valid record else mark detail for delete and don't print
                                        print '<a target="edit" href="'.HEURIST_BASE_URL.'?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$ref.'">'.$i++.'</a> ';
                                    }
                                    print '</td></tr>';
                                }

                                $bkmk_count = mysql_fetch_array(mysql_query('select count(distinct bkm_ID) from usrBookmarks where bkm_RecID='.$record['rec_ID']));
                                if ($bkmk_count[0]) print '<tr><td>Bookmarks</td><td>'.$bkmk_count[0].'</td></tr>';
                                $kwd_count = mysql_fetch_array(mysql_query('select count(distinct rtl_ID) from usrBookmarks left join usrRecTagLinks on rtl_RecID=bkm_recID where bkm_RecID='.$record['rec_ID'].' and rtl_ID is not null'));
                                if ($kwd_count[0]) print '<tr><td>Tags</td><td>'.$kwd_count[0].'</td></tr>';

                                $res2 = mysql_query('select concat(usr.'.USERS_FIRSTNAME_FIELD.'," ",usr.'.USERS_LASTNAME_FIELD.') as name, rem_Freq, rem_StartDate from usrReminders left join '.USERS_DATABASE.'.'.USERS_TABLE.' usr on usr.'.USERS_ID_FIELD.'=rem_OwnerUGrpID where ugr_Type = "User" and rem_RecID='.$record['rec_ID']);
                                $rems = Array();
                                while ($rem = mysql_fetch_assoc($res2))
                                    $rems[] = $rem['name'].' '.$rem['rem_Freq'].($rem['rem_Freq']=='once' ? ' on ' : ' from ').$rem['rem_StartDate'];
                                if (count($rems))
                                    print '<tr><td>Reminders</td><td>' . join(', ', $rems) . '</td></tr>';

                                print '</table>';

                                print '</td></tr>';
                                print '<tr><td colspan=3><hr /></td></tr>';
                                print "</tr>\n\n";
                            }
                        }else{  //display page for the user to select the set of details to keep for this record  - this is the basic work for the merge
                            $master_index = array_search($master_rec_id, $rec_keys);
                            if ($master_index === FALSE){  // no master selected we can't do a merge
                                return;
                            } elseif ($master_index > 0){  // rotate the keys so the master is first
                                $temp = array_slice($rec_keys, 0,$master_index);
                                $rec_keys = array_merge(array_slice($rec_keys,$master_index),$temp);
                                $master_rec_type = $records[$master_rec_id]['rec_RecTypeID'];
                            }
                            foreach($rec_keys as $index) {
                                $record = $records[$index];
                                $is_master = ($record['rec_ID']== $master_rec_id);
                                print '<tr id="row'.$record['rec_ID'].'">';
                                if ($is_master) print '<td><div><b>MASTER</b></div></td>';
                                else print '<td><div><b>Duplicate</b></div></td>';
                                print '<td style="width: 500px;">';
                                print '<div style="font-size: 120%;"><a target="edit" href="'.HEURIST_BASE_URL.'?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$record['rec_ID'].'">'.$record['rec_ID'] . ' ' . '<b>'.$record['rec_Title'].'</b></a> - <span style="background-color: #EEE;">'. $rtyNameLookup[$record['rec_RecTypeID']].'</span></div>';
                                print '<table>';
                                if ($is_master) $_SESSION['master_details']=$record['details']; // save master details for processing - signals code to do_fix_dupe
                                foreach ($record['details'] as $rd_type => $detail) {
                                    if (! $detail) continue;    //FIXME  check if required and mark it as missing and required
                                    // check to see if the master record already has the same detail with the identical value ignoring leading and trailing spaces
                                    $removeIndices = array();
                                    if (! $is_master && @$_SESSION['master_details'][$rd_type]){
                                        $master_details =  $_SESSION['master_details'][$rd_type];
                                        foreach ($detail as $i => $d_detail){
                                            
                                            foreach ($master_details as $m_detail){
                                                
                                                if($m_detail['dtl_Geo']){
                                                    if(trim($d_detail['dtl_Geo']) == trim($m_detail['dtl_Geo'])){
                                                        //print $m_detail['dtl_Geo'].'  '.$d_detail['dtl_Geo'];
                                                        array_push($removeIndices,$i);
                                                    }
                                                }else if ($m_detail['dtl_UploadedFileID']) {
                                                    if(trim($d_detail['dtl_UploadedFileID']) == trim($m_detail['dtl_UploadedFileID'])){
                                                        array_push($removeIndices,$i);
                                                    }
                                                }else if ($m_detail['dtl_Value'] && trim($d_detail['dtl_Value']) == trim($m_detail['dtl_Value'])){
                                                        //mark this detail for removal
                                                        array_push($removeIndices,$i);
                                                }
                                            }
                                        }
                                    }
                                    foreach ($removeIndices as $i){
                                        unset($detail[$i]);
                                    }
                                    if (count($detail) == 0) continue;
                                    if(!@$rec_requirements[$master_rec_type][$rd_type]) continue;
                                    $reqmnt = $rec_requirements[$master_rec_type][$rd_type]['rst_RequirementType'];
                                    $color = ($reqmnt == 'required' ? 'red': ($reqmnt == 'recommended'? 'black':'grey'));
                                    print '<tr><td style=" color: '.$color .';">'.$bdts[$rd_type].'</td>';
                                    //FIXME place a keep checkbox on values for repeatable fields , place a radio button for non-repeatable fields with
                                    //keep_dt_### where ### is detail Type id and mark both "checked" for master record
                                    print '<td style="padding-left:10px;">';
                                    if(@$rec_requirements[$master_rec_type][$rd_type])
                                        $repeatCount = intval($rec_requirements[$master_rec_type][$rd_type]['rst_MaxValues']);
                                    else
                                        $repeatCount = 0;
                                    $detail = detail_get_html_input_str( $detail, $repeatCount, $is_master );
                                    if (is_array($detail)) {
                                        if ($repeatCount != 1){//repeatable
                                            foreach ($detail as $val) {
                                                print '<div>'. $val . '</div>';
                                            }
                                        } else{
                                            print '<div>'. $detail[0] . '</div>';
                                            //FIXME  add code to remove the extra details that are not supposed to be there
                                        }
                                    } else{
                                        print '<div>'. $detail . '</div>';
                                    }

                                    print '</td>';
                                }

                                if ($record['rec_URL']) print '<tr><td>URL</td><td><input type="radio" name="URL" '.($is_master?"checked=checked":"").
                                    ' title="'.($is_master?"Click to keep URL with Master record":"Click to replace URL in Master record (overwrite)").
                                    '" value="'.$record['rec_URL'].
                                    '" id="URL'.$record['rec_ID'].
                                    '"><a href="'.$record['rec_URL'].'">'.$record['rec_URL'].'</a></td></tr>';

                                if ($record['rec_Added']) print '<tr><td>Add &nbsp;&nbsp;&nbsp;'.substr($record['rec_Added'], 0, 10).'</td></tr>';
                                if ($record['rec_Modified']) print '<tr><td>Mod &nbsp;&nbsp;&nbsp;'.substr($record['rec_Modified'], 0, 10).'</td></tr>';


                                print '</table></td><td>';

                                print '<table>';

                                if (array_key_exists("refs",$record)) {
                                    print '<tr><td>References</td><td>';
                                    $i = 1;
                                    foreach ($record["refs"] as $ref) {
                                        print '<a target="edit" href="'.HEURIST_BASE_URL.'?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$ref.'">'.$i++.'</a> ';
                                    }
                                    print '</td></tr>';
                                }

                                $bkmk_count = mysql_fetch_array(mysql_query('select count(distinct bkm_ID) from usrBookmarks where bkm_recID='.$record['rec_ID']));
                                if ($bkmk_count[0]) print '<tr><td>Bookmarks</td><td>'.$bkmk_count[0].'</td></tr>';
                                $kwd_count = mysql_fetch_array(mysql_query('select count(distinct rtl_ID) from usrBookmarks left join usrRecTagLinks on rtl_RecID=bkm_recID where bkm_RecID='.$record['rec_ID'].' and rtl_ID is not null'));
                                if ($kwd_count[0]) print '<tr><td>Tags</td><td>'.$kwd_count[0].'</td></tr>';

                                $res2 = mysql_query('select concat(usr.'.USERS_FIRSTNAME_FIELD.'," ",usr.'.USERS_LASTNAME_FIELD.') as name, rem_Freq, rem_StartDate from usrReminders left join '.USERS_DATABASE.'.'.USERS_TABLE.' usr on usr.'.USERS_ID_FIELD.'=rem_OwnerUGrpID where rem_RecID='.$record['rec_ID']);
                                $rems = Array();
                                while ($rem = mysql_fetch_assoc($res2))
                                    $rems[] = $rem['name'].' '.$rem['rem_Freq'].($rem['rem_Freq']=='once' ? ' on ' : ' from ').$rem['rem_StartDate'];
                                if (count($rems))
                                    print '<tr><td>Reminders</td><td>' . join(', ', $rems) . '</td></tr>';

                                print '</table>';

                                print '</td></tr>';
                                print '<tr><td colspan=3><hr /></td></tr>';
                                print "</tr>\n\n";
                            }
                        }
                        ?>
                    </tbody></table>

                <?php
                if (! $finished_merge) {
                    print '<input type="submit" name="'.($do_merge_details? "commit":"merge").'" style="float:right;" value="'. ($do_merge_details? "commit&nbsp;changes":"merge&nbsp;duplicates").'" >';
                } else{
                    print '<div> Changes were commited <br><br></div>';
                    print '<input type="button" name="close_window" id="close_window" value="Close Window"   title="Cick here to close this window" onclick="window.close();">';
                }
                ?>
                <input type="hidden" name="db" id="db" value="<?php echo HEURIST_DBNAME;?>">
            </form>
        </div>
    </body>
</html>

<?php
session_commit();
function detail_get_html_input_str( $detail, $repeatCount, $is_master ) {
    $is_type_repeatable = $repeatCount != 1;
    foreach($detail as $rg){
        $detail_id = $rg['dtl_ID'];
        $detail_type = $rg['dtl_DetailTypeID'];
        
        if ($rg['dtl_Value']) {
            
            if ($rg['dtl_Geo']) {
                $detail_val = $rg['dtl_Geo'];   
            } else if ($rg['trm_Label']){
                $detail_val = $rg['trm_Label']." (".$rg['dtl_Value'].")";
                //$temp = $rg['dtl_Value']; //trm_ID
            }else{
                $detail_val = $rg['dtl_Value'];
            }
            
            
        }elseif ($rg['dtl_UploadedFileID']) {
            $rd_temp = mysql_fetch_array(mysql_query('select ulf_OrigFileName from recUploadedFiles where ulf_ID ='.$rg['dtl_UploadedFileID']));
            $detail_val = $rd_temp[0];
        }
       
        $input = '<input type="'.($is_type_repeatable? "checkbox":"radio").
        '" name="'.($is_type_repeatable? ($is_master?"keep":"add").$detail_type.'[]':"update".$detail_type).
        '" title="'.($is_type_repeatable?($is_master?"check to Keep value in Master record - uncheck to Remove value from Master record":"Check to Add value to Master record"):
            ($is_master?  "Click to Keep value in Master record": "Click to Replace value in Master record")).  
        '" '.($is_master?"checked=checked":"").
        ' value="'.($is_type_repeatable?  $detail_id :($is_master? "master":$detail_id)).
        '" id="'.($is_type_repeatable? ($is_master?"keep_detail_id":"add_detail_id"):"update").$detail_id.
        '">'.detail_str($detail_type, $detail_val).'';
        
        
        
        $rv[]= $input;
    }
    return $rv;
}
/*      master
print '<input type="radio"    name="update'.$detail_type.'"   checked=checked value="master" id="update'.$detail_id.'><div style="font-size: 70%;">'.detail_str($detail_type,$detail_val).'</div>';
rep     print '<input type="checkbox" name="keep'.$detail_type.'[]" checked=checked value="'.$detail_id.'" id="keep_detail_id'.$detail_id.'" ><div style="font-size: 70%;">'.detail_str($detail_type,$detail_val).'</div>';
dup
print '<td><input type="radio" name="update" '.$detail_type.' value="'.$detail_id.'" id="update'.$detail_id.'"><div style="font-size: 70%;">'.detail_str($detail_type,$detail_val).'</div>';
rep        print '<input type="checkbox" name="add'.$detail_type.'[]"  value="'.$detail_id.'" id="add_detail_id'.$detail_id.'"><div style="font-size: 70%;">'.detail_str($detail_type,$detail_val).'</div></td>';
*
*"<input type=\"checkbox\" name=\"keep158[]\"checked=checkedvalue=\"250491\" id=\"keep_detail_id158[]\"><a target=\"edit\" href=\"../edit?recID=61985\">Fajsák, G</a>"
"<input type=\"checkbox\" name=\"keep158[]\"checked=checkedvalue=\"250492\" id=\"keep_detail_id158[]\"><a target=\"edit\" href=\"../edit?recID=61986\">Renner, G</a>"
"<input type=\"radio\" name=\"update159\"checked=checkedvalue=\"250494\" id=\"update159\">1996"

*/
//ART
//
// $rd_type - detail type id or term id
//
function detail_str($rd_type, $rd_val) {
    global $reference_bdts, $enum_bdts;
    if (in_array($rd_type, array_keys($reference_bdts))) { //valid detail type
        if (is_array($rd_val)) {
            foreach ($rd_val as $val){
                $title = mysql_fetch_assoc(mysql_query('select rec_Title from Records where rec_ID ='.$val));
                $rv[] = '<a target="edit" href="'.HEURIST_BASE_URL.'?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$val.'">'.$title['rec_Title'].'</a>';
            }
            return $rv;
        }
        else if($rd_val>0) {
            $title = mysql_fetch_assoc(mysql_query('select rec_Title from Records where rec_ID ='.$rd_val)); //ART HERE
            return '<a target="edit" href="'.HEURIST_BASE_URL.'?fmt=edit&db='.HEURIST_DBNAME.'&recID='.$rd_val.'">'.$title['rec_Title'].'</a>';
        }
    }
    /*
    else if ($rd_type == 158) {
    if (is_array($rd_val[0])) {
    foreach ($rd_val as $val)
    $rv[] = $val['per_citeas'];
    return $rv;
    }
    else {
    return $rd_val['per_citeas'];
    }
    }
    */
    else if (in_array($rd_type, array_keys($enum_bdts))) {
        if(Is_integer($rd_val)){
            $res = mysql_query('select trm_Label from defTerms where trm_ID ='.$rd_val);
            if($res){
                $title = mysql_fetch_assoc($res);
                return $title['trm_Label'];
            }else  {
                return $rd_val;
            }

        }else{
            return $rd_val;
        }


    } else
        return $rd_val;
}

// ---------------------------------------------- //
// function to actually fix stuff on form submission
function do_fix_dupe() {
    $master_rec_id = $_SESSION['master_rec_id'];
    $master_details = $_SESSION['master_details'];
    unset($_SESSION['master_details']); //clear master_details so we don't re-enter this code
    unset($_SESSION['master_rec_id']);
    $_SESSION['finished_merge'] = 1;  // set state variable for next loop
    $dup_rec_ids=array();
    if(in_array($master_rec_id,explode(',',$_REQUEST['bib_ids']))){
        $dup_rec_ids = array_diff(explode(',',$_REQUEST['bib_ids']),array($master_rec_id) );
    }
    $dup_rec_list = '(' . join(',', $dup_rec_ids) . ')';
    $add_dt_ids = array();   // array of detail ids to insert for the master record grouped by detail type is
    $update_dt_ids = array(); // array of detail ids to get value for updating the master record
    $keep_dt_ids = array();   // array of master record repeatable detail ids to keep grouped by detail type id- used to find master details to remove
    //parse form data
    foreach($_REQUEST as $key => $value){
        preg_match('/(add|update|keep)(\d+)/',$key,$matches);
        if (! $matches) continue;

        switch (strtolower($matches[1])){
            case 'add':
                $add_dt_ids[$matches[2]] = $value;
                break;
            case 'update':
                if ($value != "master")$update_dt_ids[$matches[2]] = $value;
                break;
            case 'keep':
                $keep_dt_ids[$matches[2]] = $value;
                break;
        }
    }

    //   mysql_connection_overwrite("`heuristdb-nyirti`");   //for debug
    mysql_connection_overwrite(DATABASE);
    //    mysql_query('set @suppress_update_trigger:=1'); // shut off update triggers to let us munge the records with out worrying about the archive.

    // set modified on master so the changes will stick  aslo update url if there is one.
    $now = date('Y-m-d H:i:s');
    $pairs =(@$_REQUEST['URL']? array("rec_URL" =>$_REQUEST['URL'], "rec_Modified" => $now): array("rec_Modified" => $now));
    mysql__update("Records", "rec_ID=$master_rec_id", $pairs );
    //process keeps - which means find repeatables in master record to delete  all_details - keeps = deletes
    //get array of repeatable detail ids for master
    $master_rep_dt_ids = array();
    $res = mysql_query('select rst_DetailTypeID from defRecStructure where rst_MaxValues != 1 and rst_RecTypeID = '.$_SESSION['rty_ID']);
    while ($row = mysql_fetch_array( $res)) {
        array_push($master_rep_dt_ids, $row[0]);
    }
    $master_rep_detail_ids = array();
    foreach($master_rep_dt_ids as $rep_dt_id ){
        if (array_key_exists($rep_dt_id,$master_details)){
            foreach ($master_details[$rep_dt_id]as $detail){
                array_push($master_rep_detail_ids, $detail['dtl_ID']);
            }
        }
    }

    //get flat array of keep detail ids
    if ($keep_dt_ids && count($keep_dt_ids)){
        $master_keep_ids = array();
        foreach($keep_dt_ids as $dt_id => $details){
            foreach($details as $detail)
                array_push($master_keep_ids,$detail);
        }
    }
    //diff the arrays  don't delet yet as the user might be adding an existing value
    $master_delete_dt_ids = array();
    if($master_rep_detail_ids) $master_delete_dt_ids = array_diff($master_rep_detail_ids,$master_keep_ids); //ART HERE   $master_keep_ids
    //FIXME add code to remove any none repeatable extra details
    //for each update
    if ($update_dt_ids){
        $update_detail=array();
        foreach($update_dt_ids as $rdt_id => $rd_id){
            //look up data for detail and
            $update_detail = mysql_fetch_assoc(mysql_query('select * from recDetails where dtl_ID='.$rd_id));
            // if exist in master details  update val
            if(in_array($rdt_id,array_keys($master_details))){
                mysql__update("recDetails", "dtl_ID=".$master_details[$rdt_id][0]['dtl_ID'], array( "dtl_Value" => $update_detail['dtl_Value']));

                // else  insert the data as detail for master record
            }else {
                unset($update_detail['dtl_ID']);         //get rid of the detail id the insert will create a new one.
                $update_detail['dtl_RecID'] = $master_rec_id;   // set this as a detail of the master record
                mysql__insert('recDetails',$update_detail);
            }
        }//foreach
    }
    //process adds
    if($add_dt_ids){
        $add_detail = array();
        // for each add detail
        foreach($add_dt_ids as $key => $detail_ids){
            foreach($detail_ids as $detail_id){
                // since adds are only for repeatables check if it exist in delete array ?yes - remove from delete list if there
                if ($key_remove = array_search($detail_id, $master_delete_dt_ids)!== FALSE){      //FIXME need to compare teh value not the dtl_ID (they will always be diff)
                    //remove from array
                    unset($master_delete_dt_ids[$key_remove]);
                }else{ //no  then lookup data for detail and insert the data as detail under the master rec id
                    $add_detail = mysql_fetch_assoc(mysql_query('select * from recDetails where dtl_ID='.$detail_id));
                    unset($add_detail['dtl_ID']); //the id is auto set during insert
                    $add_detail['dtl_RecID'] = $master_rec_id;
                    mysql__insert('recDetails',$add_detail);
                #000000
            }
        }
    }
    }
    
    foreach ($dup_rec_ids as $dup_rec_id) {
        //saw FIXME we should be updating the chain of links
        //find all references to $dup_rec_id that will be removed
        mysql_query('update recForwarding set rfw_NewRecID='.$master_rec_id.' where rfw_NewRecID='.$dup_rec_id);
        
        mysql_query('insert into recForwarding (rfw_OldRecID, rfw_NewRecID) values ('.$dup_rec_id.', '.$master_rec_id.')');
        //saw FIXME  we should update the relationship table on both rr_rec_idxxx  fields
    }

    // move dup bookmarks and tags to master unless they are already there
    //get bookmarkid =>userid for bookmarks of master record
    $master_bkm_UGrpIDs = mysql__select_assoc('usrBookmarks', 'bkm_ID','bkm_UGrpID', 'bkm_recID = '.$master_rec_id);
    //get kwd_ids for  all bookmarks of master record
    $master_tag_ids = mysql__select_array('usrRecTagLinks', 'rtl_TagID', 'rtl_RecID = '.$master_rec_id);
    //get bookmarkid => userid of bookmarks for dup records
    $dup_bkm_UGrpIDs = mysql__select_assoc('usrBookmarks','bkm_ID', 'bkm_UGrpID', 'bkm_recID in'. $dup_rec_list);


    // if dup userid already has a bookmark on master record then add dup bkm_ID to delete_bkm_IDs_list else add to  update_bkm_IDs
    $update_bkm_IDs  = array();
    $delete_bkm_IDs = array();
    $dup_delete_bkm_ID_to_master_bkm_id = array();
    //for every user or group that bookmarks a dup record if it already bookmarks the master then mark it for deletion
    // otherwise mark it for update to point to the master record
    foreach ($dup_bkm_UGrpIDs as $dup_bkm_ID => $dup_bkm_UGrpID){
        if (count(@$master_bkm_UGrpIDs) && $matching_master_bkm_ID = array_search($dup_bkm_UGrpID,$master_bkm_UGrpIDs)){
            array_push($delete_bkm_IDs, $dup_bkm_ID);
            $dup_delete_bkm_ID_to_master_bkm_id[$dup_bkm_ID] = $matching_master_bkm_ID;
        }else{
            array_push($update_bkm_IDs, $dup_bkm_ID);
            $master_bkm_UGrpIDs[$dup_bkm_ID] = $dup_bkm_UGrpID;
        }
    }
    //move duplicate record bookmarks for users without bookmarks on the master record
    $update_bkm_IDs_list  = '('.join(',',$update_bkm_IDs). ")";
    $delete_bkm_IDs_list  = '('.join(',',$delete_bkm_IDs). ")";

    if (strlen($update_bkm_IDs_list)>2) { // update the bookmarks and tags that are not in the master
        mysql_query('update usrBookmarks set bkm_recID='.$master_rec_id.' where bkm_ID in '.$update_bkm_IDs_list);
        //        mysql_query('update usrRecTagLinks set rtl_RecID='.$master_rec_id.' where kwl_pers_id in '.$update_bkm_IDs_list);
    }
    // process to be deleted dup bookmarks
    foreach ($delete_bkm_IDs as $delete_dup_bkm_ID) {
        //copy soon to be deleted dup bookmark data to master record bookmark  by concat notes and pwd_reminder, max of ratings and copy zotero if non existant
        $master_bkm_ID = @$dup_delete_bkm_ID_to_master_bkm_id[$delete_dup_bkm_ID];
        $res1 = mysql_query('select * from usrBookmarks where bkm_ID='.$master_bkm_ID);
        $res2 = mysql_query('select * from usrBookmarks where bkm_ID='.$delete_dup_bkm_ID);
        if(!($res1 && $res2)) continue;
        $master_pers_record = mysql_fetch_assoc($res1);
        $delete_dup_pers_record = mysql_fetch_assoc($res2);
        //        $master_pers_record['pers_notes'] .= $delete_dup_pers_record['pers_notes'];
        if(strlen(@$delete_dup_pers_record['bkm_PwdReminder'])>0){
            $master_pers_record['bkm_PwdReminder'] = $master_pers_record['bkm_PwdReminder']."; ". $delete_dup_pers_record['bkm_PwdReminder'];    
        }
        
        $master_pers_record['bkm_Rating'] = max($master_pers_record['bkm_Rating'],$delete_dup_pers_record['bkm_Rating']);
        if (!$master_pers_record['bkm_ZoteroID']) $master_pers_record['bkm_ZoteroID']= $delete_dup_pers_record['bkm_ZoteroID'];
        unset($master_pers_record['bkm_ID']);
        mysql__update('usrBookmarks','bkm_ID='.$master_bkm_ID,$master_pers_record);
    }
    //for every delete dup tag link whoses tag id is not already linked to the master record change the record id to master
    //get tag links for the soon to be deleted dup records
    $delete_dup_rtl_ids = mysql__select_assoc('usrRecTagLinks','rtl_ID', 'rtl_TagID', 'rtl_RecID in'. $dup_rec_list);
    foreach ($delete_dup_rtl_ids as $rtl_ID => $tag_id) {
        if (count($master_tag_ids) && array_search($tag_id,$master_tag_ids)){ //if it's already linked to the master delete it
            mysql_query('delete from usrRecTagLinks where rtl_ID = '.$rtl_ID);  //FIXME add error code
        }else{ // otherwise point it to the master record
            mysql_query('update usrRecTagLinks set rtl_RecID='.$master_rec_id.', where rtl_ID = '.$rtl_ID);
            array_push($master_tag_ids,$tag_id);// add to the array of tagids already on the master record
        }
    }

    // move reminders to master
    mysql_query('update usrReminders set rem_RecID='.$master_rec_id.' where rem_RecID in '.$dup_rec_list);   //?FIXME  do we need to check reminders like we checked usrBookmarks
    //delete master details
    if($master_delete_dt_ids && count($master_delete_dt_ids)){
        $master_detail_delete_list = '('.join(',',$master_delete_dt_ids).')';
        mysql_query('delete from recDetails where dtl_ID in '.$master_detail_delete_list);  //FIXME add error code
    }
    //delete dup details
    mysql_query('delete from recDetails where dtl_RecID in '.$dup_rec_list);
    //delete dup usrBookmarks
    if (strlen($delete_bkm_IDs_list)>2) {
        mysql_query('delete from usrBookmarks where bkm_ID in '.$delete_bkm_IDs_list);
    }

    // move dup record pointers to master record
    mysql_query('update recDetails left join defDetailTypes on dty_ID=dtl_DetailTypeID set dtl_Value='.$master_rec_id.
        ' where dtl_Value in '.$dup_rec_list.' and dty_Type="resource"');

    //delete dups
    mysql_query('delete from Records where rec_ID in '.$dup_rec_list);
//DEBUG error_log('delete from Records where rec_ID in '.$dup_rec_list);
    
    //delete unwanted details in master
    //if ($master_delete_dt_ids && $master_delete_dt_ids[0]){
    //    $master_delete_dt_ids_list = '('.join(',',$master_delete_dt_ids). ')' ;
    //    mysql_query('delete from recDetails where dtl_ID in '.$master_delete_dt_ids_list);
    // }

    //try to get the record to update title and hash
    // calculate title, do an update
    $type =  $_SESSION['rty_ID'];
    $mask = mysql__select_array("defRecTypes", "rty_TitleMask", "rty_ID=".$type);
    if ( $mask && count($mask) > 0) {
        $mask = $mask[0];
        $title = fill_title_mask($mask, $master_rec_id, $type);
        if ($title) {
            mysql_query("update Records set rec_Title = '" . mysql_real_escape_string($title) . "' where rec_ID = $master_rec_id");
        }
    }
    mysql_query('update Records set rec_Hash = hhash(rec_ID) where rec_ID='.$master_rec_id);


    header('Location: combineDuplicateRecords.php?db='.HEURIST_DBNAME.'&bib_ids='.$_REQUEST['bib_ids']);

}
?>