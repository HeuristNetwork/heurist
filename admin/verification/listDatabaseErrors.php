<?php

/**
* listDatabaseErrors.php: Lists structural errors and records with errors:
* invalid term codes, field codes, record types in pointers
* pointer fields point to non-existent records or records of the wrong type
*   single value fields with multiple values
*   required fields with no value
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     3.1.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../common/php/Temporal.php');
require_once('valueVerification.php');

require_once('getFieldTypeDefinitionErrors.php');

if(@$_REQUEST['data']){
    $lists = json_decode($_REQUEST['data'], true);
}else{
    $lists = getInvalidFieldTypes(@$_REQUEST['rt']);
    if(!@$_REQUEST['show']){
        if(count($lists["terms"])==0 && count($lists["terms_nonselectable"])==0 && count($lists["rt_contraints"])==0){
            $lists = array();
        }
    }
}

$dtysWithInvalidTerms = @$lists["terms"];
$dtysWithInvalidNonSelectableTerms = @$lists["terms_nonselectable"];
$dtysWithInvalidRectypeConstraint = @$lists["rt_contraints"];

?>

<html>
    <head>

        <meta http-equiv="content-type" content="text/html; charset=utf-8">

        <script type=text/javascript>

            function open_selected_by_name(sname) {
                var cbs = document.getElementsByName(sname);
                if (!cbs  ||  ! cbs instanceof Array)
                    return false;
                var ids = '';
                for (var i = 0; i < cbs.length; i++) {
                    if (cbs[i].checked)
                        ids = ids + cbs[i].value + ',';
                }
                //var link = document.getElementById('selected_link');
                //if (link) return false;
                window.open('<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:' + ids, '_blank');
                return false;
            }
        </script>

        <script type=text/javascript>

            var Hul = top.HEURIST.util;

            function open_selected() {
                var cbs = document.getElementsByName('bib_cb');
                if (!cbs  ||  ! cbs instanceof Array)
                    return false;
                var ids = '';
                for (var i = 0; i < cbs.length; i++) {
                    if (cbs[i].checked)
                        ids = ids + cbs[i].value + ',';
                }
                var link = document.getElementById('selected_link');
                if (!link)
                    return false;
                link.href = '<?=HEURIST_BASE_URL?>?db=<?= HEURIST_DBNAME?>&w=all&q=ids:' + ids;
                return true;
            }

            function onEditFieldType(dty_ID){

                var url = top.HEURIST.baseURL + "admin/structure/fields/editDetailType.html?db=<?= HEURIST_DBNAME?>";
                if(dty_ID>0){
                    url = url + "&detailTypeID="+dty_ID; //existing
                }else{
                    return;
                }

                top.HEURIST.util.popupURL(top, url,
                    {   "close-on-blur": false,
                        "no-resize": false,
                        height: 680,
                        width: 840,
                        callback: function(context) {
                        }
                });
            }
        </script>

        <link rel="stylesheet" type="text/css" href="../../common/css/global.css">
        <link rel="stylesheet" type="text/css" href="../../common/css/admin.css">
        <style type="text/css">
            h3, h3 span {
                display: inline-block;
                padding:0 0 10px 0;
            }
            Table tr td {
                line-height:2em;
            }
        </style>

    </head>


    <body class="popup">

        <div class="banner">
            <h2>Check for invalid definitions and data (invalid pointers, terms, missing required, excess values etc.)</h2>
        </div>

        <div style="padding-top:20px">
            These checks look for errors in the structure of the database and errors in the data within the database. These are generally not serious, but are best eliminated.
            <br /> Click the hyperlinked record ID at the start of each row to open an edit form to change the data for that record.
            <br />Look for red warning texts or pointer fields in the record which do not display data or which display a warning.
            
            <hr style="margin-top:15px">
            <div id="linkbar" style="padding-top:10px">
                <label><b>Go to:</b></label>
                <a href="#field_type" style="white-space: nowrap;padding-right:10px">Field types</a>
                <a href="#pointer_targets" style="white-space: nowrap;padding-right:10px">Pointer targets</a>
                <a href="#target_types" style="white-space: nowrap;padding-right:10px">Target types</a>
                <a href="#empty_fields" style="white-space: nowrap;padding-right:10px">Empty fields</a>
                <a href="#date_values" style="white-space: nowrap;padding-right:10px">Date values</a>
                <a href="#term_values" style="white-space: nowrap;padding-right:10px">Term values</a>
                <a href="#expected_terms" style="white-space: nowrap;padding-right:10px">Expected terms</a>
                <a href="#single_value" style="white-space: nowrap;padding-right:10px">Single value fields</a>
                <a href="#required_fields" style="white-space: nowrap;padding-right:10px">Required fields</a>
                <a href="#nonstandard_fields" style="white-space: nowrap;padding-right:10px">Non-standard fields</a>
                <a href="#origin_differences" style="white-space: nowrap;padding-right:10px">Differences with Core Definitions</a>
            </div>
        </div>
        
        <div id="page-inner" style="top:110px">
            

            <!-- CHECK FOR FIELD TYPE ERRORS -->

            <script type="text/javascript" src="../../common/js/utilsLoad.js"></script>
            <script type="text/javascript" src="../../common/php/loadCommonInfo.php"></script>


            <?php
            flush_buffers();
            
            if (count(@$dtysWithInvalidTerms)>0 || 
                count(@$dtysWithInvalidNonSelectableTerms)>0 || 
                count(@$dtysWithInvalidRectypeConstraint)>0){
                ?>
                <script>
                    function repairFieldTypes(){

                        

                        var dt = [
                            <?php
                            $isfirst = true;
                            foreach ($dtysWithInvalidTerms as $row) {
                                print ($isfirst?"":",")."[".$row['dty_ID'].", 0, '".$row['validTermsString']."']";
                                $isfirst = false;
                            }
                            foreach ($dtysWithInvalidNonSelectableTerms as $row) {
                                print ($isfirst?"":",")."[".$row['dty_ID'].", 1, '".$row['validNonSelTermsString']."']";
                                $isfirst = false;
                            }
                            foreach ($dtysWithInvalidRectypeConstraint as $row) {
                                print ($isfirst?"":",")."[".$row['dty_ID'].", 2, '".$row['validRectypeConstraint']."']";
                                $isfirst = false;
                            }
                        ?>];

                        var str = JSON.stringify(dt);

                        var baseurl = top.HEURIST.baseURL + "admin/verification/repairFieldTypes.php";
                        //var callbackFunc = _callback;
                        var params = "db=<?= HEURIST_DBNAME?>&data=" + encodeURIComponent(str);
                        top.HEURIST.util.getJsonData(baseurl, function(context){
                            //console.log('result '+context);
                            if(top.HEURIST.util.isnull(context) || top.HEURIST.util.isnull(context['result'])){
                                top.HEURIST.util.showError(null);
                            }else{
                                if(top.HEURIST4 && top.HEURIST4.msg){
                                    top.HEURIST4.msg.showMsgDlg(context['result'], null, 'Auto repair');
                                }else{
                                    alert(context['result']);    
                                }
                            }
                        }, params);
                    }
                </script>


                <a name="field_type"/>
                <br/><p><br/></p><h3>Warning: Inconsistent field definitions</h3><br/>&nbsp;<br/>

                The following field definitions have inconsistent data (unknown codes for terms and/or record types). This is nothing to be concerned about, unless it reoccurs, in which case please advise Heurist developers<br/><br/>
                To fix the inconsistencies, please click here: <button onclick="repairFieldTypes()">Auto Repair</button>  <br/>&nbsp;<br/>
                You can also look at the individual field definitions by clicking on the name in the list below<br />&nbsp;<br/>
                <hr/>
                <?php 
                foreach ($dtysWithInvalidTerms as $row) {
                    ?>
                    <div class="msgline"><b><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_Name'] ?></a></b> field (code <?= $row['dty_ID'] ?>) has
                        <?= count($row['invalidTermIDs'])?> invalid term ID<?=(count($row['invalidTermIDs'])>1?"s":"")?>
                        (code: <?= implode(",",$row['invalidTermIDs'])?>)
                    </div>
                    <?php
                }//for
                foreach ($dtysWithInvalidNonSelectableTerms as $row) {
                    ?>
                    <div class="msgline"><b><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_Name'] ?></a></b> field (code <?= $row['dty_ID'] ?>) has
                        <?= count($row['invalidNonSelectableTermIDs'])?> invalid non selectable term ID<?=(count($row['invalidNonSelectableTermIDs'])>1?"s":"")?>
                        (code: <?= implode(",",$row['invalidNonSelectableTermIDs'])?>)
                    </div>
                    <?php
                }
                foreach ($dtysWithInvalidRectypeConstraint as $row) {
                    ?>
                    <div class="msgline"><b><a href="#" onclick='{ onEditFieldType(<?= $row['dty_ID'] ?>); return false}'><?= $row['dty_Name'] ?></a></b> field (code <?= $row['dty_ID'] ?>) has
                        <?= count($row['invalidRectypeConstraint'])?> invalid record type constraint<?=(count($row['invalidRectypeConstraint'])>1?"s":"")?>
                        (code: <?= implode(",",$row['invalidRectypeConstraint'])?>)
                    </div>
                    <?php
                }

            }else{
                print "<br/><p><br/></p><h3>All field type definitions are valid</h3>";
            }
            ?>




            <!-- CHECK DATA CONSISTENCY -->

            <?php

            mysql_connection_select(DATABASE);

            ?>


            <hr />


            <!-- Record pointers which point to non-existant records -->

            <?php

            $wasdeleted = 0;
            if(@$_REQUEST['fixpointers']=="1"){

                $query = 'delete d from recDetails d
                left join defDetailTypes dt on dt.dty_ID = d.dtl_DetailTypeID
                left join Records b on b.rec_ID = d.dtl_Value and b.rec_FlagTemporary!=1
                where dt.dty_Type = "resource"
                and b.rec_ID is null';
                $res = mysql_query( $query );
                if(! $res )
                {
                    print "<div class='error'>Cannot delete invalid pointers from Records.</div>";
                }else{
                    $wasdeleted = mysql_affected_rows();
                }
            }

            $res = mysql_query('select dtl_RecID, dty_Name, a.rec_Title
                from recDetails
                left join defDetailTypes on dty_ID = dtl_DetailTypeID
                left join Records a on a.rec_ID = dtl_RecID and a.rec_FlagTemporary!=1
                left join Records b on b.rec_ID = dtl_Value and b.rec_FlagTemporary!=1
                where dty_Type = "resource"
                and a.rec_ID is not null
            and b.rec_ID is null');
            $bibs = array();
            $ids = array();
            while ($row = mysql_fetch_assoc($res)) {
                array_push($bibs, $row);
                $ids[$row['dtl_RecID']] = 1;
            }

            print '<a name="pointer_targets"/>';
                
            if(count($bibs)==0){
                print "<div><h3>All record pointers point to a valid record</h3></div>";
                if($wasdeleted>1){
                    print "<div>$wasdeleted invalid pointer(s) were removed from database</div>";
                }
            }
            else
            {
                ?>

                <div>
                    <h3>Records with record pointers to non-existent records</h3>
                    <span>
                        <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link onClick="return open_selected_by_name('recCB');">(show selected as search)</a>
                    </span>
                    <div>To fix the inconsistencies, please click here:
                        <button onclick="window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixpointers=1','_self')">
                            Delete ALL faulty pointers</button>
                    </div>
                </div>
                <table>
                    <?php
                    foreach ($bibs as $row) {
                        ?>
                        <tr>
                            <td><input type=checkbox name="recCB" value=<?= $row['dtl_RecID'] ?>></td>
                            <td style="white-space: nowrap;"><a target=_new
                                    href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                    <?= $row['dtl_RecID'] ?>
                                    <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                </a></td>
                            <td><?= $row['rec_Title'] ?></td>
                            <td><?= $row['dty_Name'] ?></td>
                        </tr>
                        <?php
                    }
                    print "</table>\n";
                    ?>
                </table>
                [end of list]
                <?php
            }
            
            //Record pointers which point to the wrong type of record
            $res = mysql_query('select dtl_RecID, dty_Name, dty_PtrTargetRectypeIDs, rec_ID, rec_Title, rty_Name
                from defDetailTypes
                left join recDetails on dty_ID = dtl_DetailTypeID
                left join Records on rec_ID = dtl_Value and rec_FlagTemporary!=1
                left join defRecTypes on rty_ID = rec_RecTypeID
                where dty_Type = "resource"
                and dty_PtrTargetRectypeIDs > 0
            and (INSTR(concat(dty_PtrTargetRectypeIDs,\',\'), concat(rec_RecTypeID,\',\')) = 0)');
            // it does not work and rec_RecTypeID not in (dty_PtrTargetRectypeIDs)');
            $bibs = array();
            while ($row = mysql_fetch_assoc($res)){
                $bibs[$row['dtl_RecID']] = $row;
            }
            
            ?>

            <hr/>

            <!-- Record pointers which point to the wrong type of record  -->

            
            <div>
                <a name="target_types"/>
                <?php
                if (count($bibs == 0)) {
                    print "<h3>All record pointers point to the correct record type</h3>";
                }
                else
                {
                    ?>
                    <h3>Records with record pointers to the wrong record type</h3>
                    <span><a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($bibs)) ?>'>
                        (show results as search)</a></span>
                    <table>
                        <?php
                        foreach ($bibs as $row) {
                            ?>
                            <tr>
                                <td style="white-space: nowrap;"><a target=_new
href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'><?= $row['dtl_RecID'] ?>
                                        <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                    </a></td>
                                <td><?= $row['dty_Name'] ?></td>
                                <td>points to</td>
                                <td><?= $row['rec_ID'] ?> (<?= $row['rty_Name'] ?>) - <?= substr($row['rec_Title'], 0, 50) ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                    <?php
                }
                ?>
            </div>

            <hr />

            <?php
            // ----- Fields with EMPTY OR NULL values -------------------

            
            if(@$_REQUEST['fixempty']=="1"){
                mysql_query('SET SQL_SAFE_UPDATES=0');
                mysql_query('delete d.* from recDetails d, defDetailTypes, Records a '
.'where (dtl_ID>0) and (a.rec_ID = dtl_RecID) and (dty_ID = dtl_DetailTypeID) and (a.rec_FlagTemporary!=1)
            and (dty_Type!=\'file\') and ((dtl_Value=\'\') or (dtl_Value is null))');                
               
               $wascorrected = mysql_affected_rows();     
               mysql_query('SET SQL_SAFE_UPDATES=1');
            }else{
                $wascorrected = 0;
            }
            
            $total_count_rows = 0;
            
            //find all fields with faulty dates
            $res = mysql_query('select dtl_ID, dtl_RecID, a.rec_Title, dty_Name, dty_Type
                from recDetails, defDetailTypes, Records a
                where (a.rec_ID = dtl_RecID) and (dty_ID = dtl_DetailTypeID) and (a.rec_FlagTemporary!=1)
            and (dty_Type!=\'file\') and ((dtl_Value=\'\') or (dtl_Value is null))');

            
            $fres = mysql_query('select found_rows()');
            if($fres){
                $total_count_rows = mysql_fetch_row($fres);
                if(count($total_count_rows)>0){
                    $total_count_rows = $total_count_rows[0];    
                }
            }
            
            
            print '<a name="empty_fields"/>';
            if($total_count_rows<1){
                 print '<div><h3>All records don\'t have empty fields</h3></div>';
            }
            if($wascorrected>1){
                 print "<div>$wascorrected empty fields were deleted</div>";
            }
            
            if($total_count_rows>0){
                ?>

                <div>
                    <h3>Records with empty fields</h3>
                    <span>
                        <a target=_new href="javascript:void(0)" onclick="{document.getElementById('link_empty_values').click(); return false;}">(show results as search)</a>
                        <a target=_new href='#' id=selected_link onClick="return open_selected_by_name('recCB6');">(show selected as search)</a>
                    </span>
                   
                    <div>To REMOVE empty fields, please click here:
                        <button
onclick="{document.getElementById('page-inner').style.display = 'none';window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixempty=1','_self')}">
                            Remove all null values</button>
                    </div>
                </div>

                <table>
                <?php

                
                $ids = array();
                
                while ($row = mysql_fetch_assoc($res)){
                    ?>
                    <tr>
                        <td><input type=checkbox name="recCB6" value=<?= $row['dtl_RecID'] ?>></td>
                        <td style="white-space: nowrap;"><a target=_new
                                href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                <?= $row['dtl_RecID'] ?>
                                <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                            </a></td>
                        <td><?= substr($row['rec_Title'],0,50) ?></td>
                        <td><?= $row['dty_Name'] ?></td>
                    </tr>
                    <?php
                    $ids[$row['dtl_RecID']] = 1;

                }
                print '</table><br>';
                
                echo '<span><a target=_new id="link_empty_values" href='.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME
                            .'&w=all&q=ids:'.implode(',', array_keys($ids)).'>(show results as search)</a></span>';
                
            }
            ?>
            
            <hr>

            <?php
            // ----- Fields of type "Date" with  wrong values -------------------

            //find all fields with faulty dates
            $res = mysql_query('select dtl_ID, dtl_RecID, dtl_Value, a.rec_Title
                from recDetails, defDetailTypes, Records a
                where (a.rec_ID = dtl_RecID) and (dty_ID = dtl_DetailTypeID) and (a.rec_FlagTemporary!=1)
            and (dty_Type = "date") and (dtl_Value is not null)');

            $wascorrected = 0;
            $bibs = array();
            $ids  = array();
            $dtl_ids = array();
            while ($row = mysql_fetch_assoc($res)){

                if(!($row['dtl_Value']==null || $row['dtl_Value']=='')){ //empty dates are not allowed
                    //parse and validate value
                    $row['new_value'] = validateAndConvertToISO($row['dtl_Value']);
                    if($row['new_value']=='Temporal'){
                        continue;
                    }else if($row['new_value']==trim($row['dtl_Value'])){
                        continue;
                    }
                }else{
                    $row['new_value'] = null;
                }

                //remove wrong dates
                if(@$_REQUEST['fixdates']=="1"){

                    if($row['new_value']){
                        mysql_query('update recDetails set dtl_Value="'.$row['new_value'].'" where dtl_ID='.$row['dtl_ID']);
                    }else{
                        mysql_query('delete from recDetails where dtl_ID='.$row['dtl_ID']);
                    }

                    $wascorrected++;
                }else{
                    array_push($bibs, $row);
                    $ids[$row['dtl_RecID']] = 1;
                    array_push($dtl_ids, $row['dtl_ID']);
                }
            }


            print '<a name="date_values"/>';
            if(count($bibs)==0){
                print '<div><h3>All records have recognisable Date values</h3></div>';
                if($wascorrected>1){
                    print "<div>$wascorrected Date fields were corrected</div>";
                }
            }
            else
            {
                ?>

                <div>
                    <h3>Records with incorrect Date fields</h3>
                    <span>
                        <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link onClick="return open_selected_by_name('recCB5');">(show selected as search)</a>
                    </span>
                    <div>To fix faulty date values as suggested, mark desired records and please click here:
                        <button
onclick="{document.getElementById('page-inner').style.display = 'none';window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixdates=1','_self')}">
                            Correct</button>
                    </div>
                </div>

                <table>
                <?php
                foreach ($bibs as $row) {
                    ?>
                    <tr>
                        <td><?php if($row['new_value']) 
                             print '<input type=checkbox name="recCB5" value='.$row['dtl_RecID'].'>';
                            ?>
                        </td>
                        <td style="white-space: nowrap;"><a target=_new
                                href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                <?= $row['dtl_RecID'] ?>
                                <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                            </a></td>
                        <td><?= substr($row['rec_Title'],0,50) ?></td>
                        <td><?= @$row['dtl_Value']?$row['dtl_Value']:'empty' ?></td>
                        <td><?= $row['new_value']?('=>&nbsp;&nbsp;'.$row['new_value']):'' ?></td>
                    </tr>
                    <?php
                }
                print '</table>';
            }
            ?>

            <hr>



            <?php
            //  Records with term field values which do not exist in the database
            $wasdeleted = 0;

            //remove wrong term IDs
            if(@$_REQUEST['fixterms']=="1"){
                $query = 'delete d from recDetails d
                left join defDetailTypes dt on dt.dty_ID = d.dtl_DetailTypeID
                left join defTerms b on b.trm_ID = d.dtl_Value
                where dt.dty_Type = "enum" or  dt.dty_Type = "relmarker"
                and b.trm_ID is null';
                $res = mysql_query( $query );
                if(! $res )
                {
                    print "<div class='error'>Cannot delete invalid term values from Records. SQL error: ".mysql_error()."</div>";
                }else{
                    $wasdeleted = mysql_affected_rows();
                }
            }

            //find non existing term values
            $res = mysql_query('select dtl_ID, dtl_RecID, dty_Name, a.rec_Title
                from recDetails
                left join defDetailTypes on dty_ID = dtl_DetailTypeID
                left join Records a on a.rec_ID = dtl_RecID
                left join defTerms b on b.trm_ID = dtl_Value
                where (dty_Type = "enum" or dty_Type = "relmarker") and dtl_Value is not null
                and a.rec_ID is not null
            and b.trm_ID is null');
            $bibs = array();
            $ids  = array();
            $dtl_ids = array();
            while ($row = mysql_fetch_assoc($res)){
                array_push($bibs, $row);
                $ids[$row['dtl_RecID']] = 1;
                array_push($dtl_ids, $row['dtl_ID']);
            }

            print '<a name="term_values"/>';
            if(count($bibs)==0){
                print "<div><h3>All records have recognisable term values</h3></div>";
                if($wasdeleted>1){
                    print "<div>$wasdeleted invalid term value(s) were removed from database</div>";
                }
            }
            else
            {
                ?>

                <div>
                    <h3>Records with non-existent term values</h3>
                    <span>
                        <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link onClick="return open_selected_by_name('recCB1');">(show selected as search)</a>
                    </span>
                    <div>To fix the inconsistencies, please click here:
                        <button onclick="window.open('listDatabaseErrors.php?db=<?= HEURIST_DBNAME?>&fixterms=1','_self')">
                            Delete ALL faulty term values</button>
                    </div>
                </div>

                <table>
                <?php
                foreach ($bibs as $row) {
                    ?>
                    <tr>
                        <td><input type=checkbox name="recCB1" value=<?= $row['dtl_RecID'] ?>></td>
                        <td style="white-space: nowrap;"><a target=_new
                                href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                <?= $row['dtl_RecID'] ?>
                                <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                            </a></td>
                        <td><?= substr($row['rec_Title'],0,50) ?></td>
                        <td><?= $row['dty_Name'] ?></td>
                    </tr>
                    <?php
                }
                print '</table>';
            }
            ?>

            <hr>
            <div>


            <!--  Records containing fields with terms not in the list of terms specified for the field   -->

            <a name="expected_terms"/>
            <?php

            $res = mysql_query('select dtl_ID, dtl_RecID, dty_Name, dtl_Value, dty_ID, dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, rec_Title, rec_RecTypeID, rty_Name, trm_Label
                from Records, recDetails left join defTerms on dtl_Value=trm_ID, defDetailTypes, defRecTypes
                where rec_ID = dtl_RecID and dty_ID = dtl_DetailTypeID and (dty_Type = "enum" or  dty_Type = "relmarker")
                and dtl_Value is not null and rec_RecTypeID=rty_ID and rec_FlagTemporary!=1
            order by dtl_DetailTypeID');
            /*
            'select dtl_RecID, dty_Name, dty_JsonTermIDTree, dty_TermIDTreeNonSelectableIDs, rec_Title, dtl_Value, dty_ID
            from defDetailTypes
            left join recDetails on dty_ID = dtl_DetailTypeID
            left join Records on rec_ID = dtl_RecID
            where dty_Type = "enum" or  dty_Type = "relmarker"
            order by dtl_DetailTypeID'*/
            $bibs = array();
            $ids = array();
            $is_first = true;
            while ($row = mysql_fetch_assoc($res)){ 
                //verify value
                if(  !in_array($row['dtl_ID'], $dtl_ids) &&  //already non existant
                trim($row['dtl_Value'])!="" &&
                isInvalidTerm($row['dty_JsonTermIDTree'], $row['dty_TermIDTreeNonSelectableIDs'], $row['dtl_Value'], $row['dty_ID'] ))
                {
                    if($is_first){
                        $is_first = false;
                    ?>
                    <h3 style="padding-left:2px">Records with terms not in the list of terms specified for the field</h3>
                    <span><a target=_new href="javascript:void(0)" onclick="{document.getElementById('link_wrongterms').click(); return false;}">(show results as search)</a></span>
                    <table>
                    <tr>
                        <th style="width: 30px;text-align:left">Record</th>
                        <th style="width: 15ex;">Field</th>
                        <th style="width: 25ex;">Term</th>
                        <th>Record title</th>
                    </tr>

                    <?php
                    }
                    ?>
                        <tr>
                            <td style="width:50px;">
                                <a target=_new  title='Click to edit record'
                                    href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
<img class="rft" style="background-image:url(<?php echo HEURIST_ICON_URL.$row['rec_RecTypeID']?>.png)" 
title="<?php echo $row['rty_Name']?>" 
src="<?php echo HEURIST_BASE_URL.'common/images/16x16.gif'?>">&nbsp;<?= $row['dtl_RecID'] ?>
                                </a>
                            </td>
                            <td style="width:15ex;padding-left:5px;"><?= $row['dty_Name'] ?></td>
                            <!-- >Artem TODO: Need to render the value as the term label, not the numeric value -->
                            <td style="width: 23ex;padding-left: 5px;"><?= $row['dtl_Value'].'&nbsp;'.$row['trm_Label'] ?></td>
                            <td style="padding-left: 25px;"><?= substr($row['rec_Title'], 0, 500) ?></td>
                        </tr>
                    <?php
                    //array_push($bibs, $row);    // MEMORY EXHAUSTION happens here
                    $ids[$row['dtl_RecID']] = 1;  
                }

            }

                if (count($ids) == 0) {
                    print "<h3>All records have valid terms (terms are as specified for each field)</h3>";
                }else{
                    echo '</table><br>';   
                    echo '<span><a target=_new id="link_wrongterms" href='.HEURIST_BASE_URL.'?db='.HEURIST_DBNAME
                            .'&w=all&q=ids:'.implode(',', array_keys($ids)).'>(show results as search)</a></span>';
                }
                ?>
            </div>



            <hr />



            <!--  single value fields containing excess values  -->


            <a name="single_value"/>
            <?php

            $res = mysql_query('select dtl_RecID, rec_RecTypeID, dtl_DetailTypeID, rst_DisplayName, rec_Title, count(*)
                from recDetails, Records, defRecStructure
                where rec_ID = dtl_RecID  and rec_FlagTemporary!=1 
                and rst_RecTypeID = rec_RecTypeID and rst_DetailTypeID = dtl_DetailTypeID
                and rst_MaxValues=1
                GROUP BY dtl_RecID, rec_RecTypeID, dtl_DetailTypeID, rst_DisplayName, rec_Title
            HAVING COUNT(*) > 1');

            $bibs = array();
            $ids = array();
            while ($row = mysql_fetch_assoc($res)){
                array_push($bibs, $row);
                $ids[$row['dtl_RecID']] = 1;
            }

            if(count($bibs)==0){
                print "<h3>No single value fields exceed 1 value</h3>";
            }
            else
            {
                ?>

                <div>
                    <h3>Single value fields with multiple values</h3>
                    <span>
                        <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link2 onClick="return open_selected_by_name('recCB2');">(show selected as search)</a>
                    </span>
                </div>

                <table>
                    <?php
                    $rec_id = null;
                    foreach ($bibs as $row) {
                        if($rec_id!=$row['dtl_RecID']) {
                            ?>
                            <tr>
                                <td>
                                    <input type=checkbox name="recCB2" value=<?= $row['dtl_RecID'] ?>>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a target=_blank href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['dtl_RecID'] ?>'>
                                    <?= $row['dtl_RecID'] ?></a>
                                    <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                </td>
                                <td><?= $row['rec_Title'] ?>
                                </td>
                                <?php
                                $rec_id = $row['dtl_RecID'];
                            }else{
                                print '<tr><td colspan="3"></td>';
                            }
                            ?>
                            <td><?= $row['rst_DisplayName'] ?>
                            </td>
                        </tr>
                        <?php
                    }
                    print "</table>";
                    ?>
                </table>

                <?php
            }
            ?>

            <hr/>



            <!--  records with missing required values  -->
            <a name="required_fields"/>
            <?php

            $res = mysql_query("select rec_ID, rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, dtl_Value, rec_Title, dty_Type
                from Records
                left join defRecStructure on rst_RecTypeID = rec_RecTypeID
                left join recDetails on rec_ID = dtl_RecID and rst_DetailTypeID = dtl_DetailTypeID
                left join defDetailTypes on dty_ID = rst_DetailTypeID
                where rec_FlagTemporary!=1 and rst_RequirementType='required' and (dtl_Value is null or dtl_Value='')
                and dtl_UploadedFileID is null and dtl_Geo is null and dty_Type!='separator'
            order by rec_ID");

            $bibs = array();
            $ids = array();
            while ($row = mysql_fetch_assoc($res)){
                array_push($bibs, $row);
                $ids[$row['rec_ID']] = $row;
            }

            if(count($bibs)==0){
                print "<div><h3>No required fields with missing or empty values</h3></div>";
            }
            else
            {
                ?>

                <div>
                    <h3>Records with missing or empty required values</h3>
                    <span>
                        <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&amp;w=all&amp;q=ids:<?= implode(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link3 onClick="return open_selected_by_name('recCB3');">(show selected as search)</a>
                    </span>
                </div>

                <table>
                    <?php
                    $rec_id = null;
                    foreach ($bibs as $row) {
                        if($rec_id!=$row['rec_ID']) {
                            ?>
                            <tr>
                                <td>
                                    <input type=checkbox name="recCB3" value=<?= $row['rec_ID'] ?>>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a target=_new
                                        href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['rec_ID'] ?>'>
                                        <?= $row['rec_ID'] ?>
                                        <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                    </a>
                                </td>
                                <td>
                                <?= $row['rec_Title'] ?></td>
                                <?php
                                $rec_id = $row['rec_ID'];
                            }else{
                                print '<tr><td colspan="3"></td>';
                            }
                            ?>
                            <td><?= $row['rst_DisplayName'] ?></td>
                        </tr>
                        <?php
                    }
                    print "</table>";
                    ?>
                </table>
                <?php
            }
            ?>


            <hr/>



            <!--  Records with non-standard fields (not listed in recstructure)  -->
            <a name="nonstandard_fields"></a>
            <?php

            $res = mysql_query("select rec_ID, rec_RecTypeID, dty_ID, dty_Name, dtl_Value, rec_Title
                from Records
                left join recDetails on rec_ID = dtl_RecID
                left join defDetailTypes on dty_ID = dtl_DetailTypeID
                left join defRecStructure on rst_RecTypeID = rec_RecTypeID and rst_DetailTypeID = dtl_DetailTypeID
                where rec_FlagTemporary!=1 and rst_ID is null
            ");

            $bibs = array();
            $ids = array();
            while ($row = mysql_fetch_assoc($res)){
                array_push($bibs, $row);
                $ids[$row['rec_ID']] = $row;
            }

            ?>

            <div>
                <?php
                if (count($bibs == 0)) {
                    print "<h3>No extraneous fields (fields not defined in the list for the record type)</h3>";
                }
                else
                {
                    ?>
                    <h3>Records with extraneous fields (not defined in the list of fields for the record type)</h3>
                    <span>
                        <a target=_new href='<?=HEURIST_BASE_URL.'?db='.HEURIST_DBNAME?>&w=all&q=ids:<?= implode(',', array_keys($ids)) ?>'>
                            (show results as search)</a>
                        <a target=_new href='#' id=selected_link4 onClick="return open_selected_by_name('recCB4');">(show selected as search)</a>
                    </span>
                    <table>
                        <?php
                        $rec_id = null;
                        foreach ($bibs as $row) {
                            if($rec_id==null || $rec_id!=$row['rec_ID']) {
                                ?>
                                <tr>
                                    <td><input type=checkbox name="recCB4" value=<?= $row['rec_ID'] ?>>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <a target=_new
                                            href='<?=HEURIST_BASE_URL?>?fmt=edit&db=<?= HEURIST_DBNAME?>&recID=<?= $row['rec_ID'] ?>'>
                                            <?= $row['rec_ID'] ?>
                                            <img src='../../common/images/external_link_16x16.gif' title='Click to edit record'>
                                        </a>
                                    </td>
                                    <!-- td><?= $row['rec_RecTypeID'] ?></td -->
                                    <td width="400px"><?= substr($row['rec_Title'],0,100)?>
                                    </td>
                                    <?php
                                    $rec_id = $row['rec_ID'];
                                }else{
                                    print '<tr><td colspan="3"></td>';
                                }
                                ?>

                                <td><?= $row['dty_ID'] ?></td>
                                <td><?= $row['dty_Name'] ?></td>
                                <td><?= $row['dtl_Value'] ?></td>
                            </tr>
                            <?php
                        }
                        print "</table>";
                        ?>
                    </table>
                    <?php
                }
                ?>
            </div>

            <hr/>
            
            <a name="origin_differences"></a>
            <div>
                <h3>The database structure is cross-checked against the core and bibliographic definitions curated by the Heurist team</h3>
                <?php
                    $_REQUEST['verbose'] = 1;
                    $_REQUEST['filter_exact']  = DATABASE;
                    include(dirname(__FILE__).'/verifyForOrigin.php');
                ?>
            </div>
            
            <hr/>

        </div>
    </body>
</html>
